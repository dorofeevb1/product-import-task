<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitQueue
{
    private const MAX_RETRIES = 3;

    public function __construct(private readonly string $dsn, private readonly string $queueName)
    {
    }

    public function publish(array $payload): void
    {
        [$connection, $channel] = $this->open();
        try {
            $this->declareQueues($channel);
            $message = new AMQPMessage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                ['delivery_mode' => 2, 'content_type' => 'application/json']
            );
            $channel->basic_publish($message, '', $this->queueName);
        } finally {
            $channel->close();
            $connection->close();
        }
    }

    public function consume(callable $handler, ?callable $onFailure = null): void
    {
        [$connection, $channel] = $this->open();
        $this->declareQueues($channel);
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($this->queueName, '', false, false, false, false, function (AMQPMessage $message) use ($handler, $onFailure, $channel): void {
            try {
                $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $handler($payload);
                $message->ack();
            } catch (\Throwable $e) {
                $this->handleFailure($channel, $message, $e, $onFailure);
            }
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    /**
     * @return array{0: AMQPStreamConnection, 1: AMQPChannel}
     */
    private function open(): array
    {
        $parsed = parse_url($this->dsn);
        if (!is_array($parsed) || !isset($parsed['host'])) {
            throw new \RuntimeException('Invalid RabbitMQ DSN');
        }
        $host = $parsed['host'];
        $port = (int) ($parsed['port'] ?? 5672);
        $user = $parsed['user'] ?? 'guest';
        $pass = $parsed['pass'] ?? 'guest';
        $vhost = ltrim(urldecode($parsed['path'] ?? '/'), '/');
        $vhost = $vhost === '' ? '/' : $vhost;

        $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
        $channel = $connection->channel();

        return [$connection, $channel];
    }

    private function declareQueues(AMQPChannel $channel): void
    {
        $channel->queue_declare($this->queueName, false, true, false, false);
        $channel->queue_declare($this->queueName . '.dead', false, true, false, false);
    }

    private function handleFailure(AMQPChannel $channel, AMQPMessage $message, \Throwable $exception, ?callable $onFailure): void
    {
        $payload = json_decode($message->getBody(), true);
        if (!is_array($payload)) {
            $payload = ['raw' => $message->getBody()];
        }
        $retries = (int) ($payload['_retry'] ?? 0);
        $payload['_lastError'] = $exception->getMessage();
        if ($retries < self::MAX_RETRIES) {
            // Simple exponential backoff before republish to reduce hot-loop retries.
            usleep((int) (100000 * (2 ** $retries)));
            $payload['_retry'] = $retries + 1;
            $retryMessage = new AMQPMessage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                ['delivery_mode' => 2, 'content_type' => 'application/json']
            );
            $channel->basic_publish($retryMessage, '', $this->queueName);
        } else {
            $payload['_failedAt'] = date(DATE_ATOM);
            if ($onFailure !== null) {
                $onFailure($payload, $exception);
            }
            $deadMessage = new AMQPMessage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                ['delivery_mode' => 2, 'content_type' => 'application/json']
            );
            $channel->basic_publish($deadMessage, '', $this->queueName . '.dead');
        }
        $message->ack();
    }
}
