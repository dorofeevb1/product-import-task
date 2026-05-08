<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Actions\HealthAction;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class HealthTest extends TestCase
{
    public function testHealthActionReturnsOkStatus(): void
    {
        $action = new HealthAction();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $action($request, new Response());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $payload['status']);
    }
}
