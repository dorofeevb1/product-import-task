<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Actions\HealthAction;
use App\Application\Actions\AuthLoginAction;
use App\Application\Actions\AuthLogoutAction;
use App\Application\Actions\AuthRefreshAction;
use App\Application\Actions\AuthRegisterAction;
use App\Application\Actions\ImportAction;
use App\Application\Actions\ImportStatusAction;
use App\Application\Actions\OpenApiAction;
use App\Application\Actions\ProductDetailsAction;
use App\Application\Actions\ProductListAction;
use App\Application\Actions\SwaggerUiAction;
use App\Domain\Import\ImportDispatcher;
use App\Domain\Import\ImportDispatcherInterface;
use App\Domain\Import\ImportService;
use App\Domain\Product\Repository\ProductRepository;
use App\Infrastructure\Doctrine\EntityManagerFactory;
use App\Infrastructure\Queue\RabbitQueue;
use App\Infrastructure\Security\AuthUserStore;
use App\Infrastructure\Security\RefreshTokenStore;
use App\Infrastructure\Security\TokenService;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(): \Slim\App
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            \Doctrine\ORM\EntityManagerInterface::class => static fn () => EntityManagerFactory::create(),
            ProductRepository::class => static fn (ContainerInterface $c) => new ProductRepository($c->get(\Doctrine\ORM\EntityManagerInterface::class)),
            ImportService::class => static fn (ContainerInterface $c) => new ImportService($c->get(\Doctrine\ORM\EntityManagerInterface::class)),
            RabbitQueue::class => static fn () => new RabbitQueue(
                $_ENV['MESSENGER_TRANSPORT_DSN'] ?? 'amqp://guest:guest@rabbitmq:5672/%2f',
                $_ENV['IMPORT_QUEUE_NAME'] ?? 'import_products'
            ),
            TokenService::class => static fn () => new TokenService(
                (string) ($_ENV['APP_SECRET'] ?? 'change-me'),
                (int) ($_ENV['JWT_TTL_SECONDS'] ?? 3600),
                (string) ($_ENV['JWT_ISSUER'] ?? 'product-import-api'),
                (string) ($_ENV['JWT_AUDIENCE'] ?? 'product-import-frontend')
            ),
            AuthUserStore::class => static fn (ContainerInterface $c) => new AuthUserStore(
                $c->get(\Doctrine\ORM\EntityManagerInterface::class)
            ),
            RefreshTokenStore::class => static fn (ContainerInterface $c) => new RefreshTokenStore(
                $c->get(\Doctrine\ORM\EntityManagerInterface::class)
            ),
            ImportDispatcherInterface::class => static fn (ContainerInterface $c) => new ImportDispatcher($c->get(RabbitQueue::class)),
            HealthAction::class => static fn () => new HealthAction(),
            AuthLoginAction::class => static fn (ContainerInterface $c) => new AuthLoginAction(
                $c->get(TokenService::class),
                $c->get(AuthUserStore::class),
                $c->get(RefreshTokenStore::class),
                (int) ($_ENV['JWT_REFRESH_TTL_SECONDS'] ?? 1209600)
            ),
            AuthRegisterAction::class => static fn (ContainerInterface $c) => new AuthRegisterAction(
                $c->get(AuthUserStore::class),
                $c->get(TokenService::class),
                $c->get(RefreshTokenStore::class),
                (int) ($_ENV['JWT_REFRESH_TTL_SECONDS'] ?? 1209600)
            ),
            AuthRefreshAction::class => static fn (ContainerInterface $c) => new AuthRefreshAction(
                $c->get(TokenService::class),
                $c->get(RefreshTokenStore::class),
                (int) ($_ENV['JWT_REFRESH_TTL_SECONDS'] ?? 1209600)
            ),
            AuthLogoutAction::class => static fn (ContainerInterface $c) => new AuthLogoutAction(
                $c->get(TokenService::class),
                $c->get(RefreshTokenStore::class)
            ),
            OpenApiAction::class => static fn () => new OpenApiAction(),
            SwaggerUiAction::class => static fn () => new SwaggerUiAction(),
            ImportAction::class => static fn (ContainerInterface $c) => new ImportAction(
                $c->get(\Doctrine\ORM\EntityManagerInterface::class),
                $c->get(ImportDispatcherInterface::class)
            ),
            ImportStatusAction::class => static fn (ContainerInterface $c) => new ImportStatusAction($c->get(\Doctrine\ORM\EntityManagerInterface::class)),
            ProductListAction::class => static fn (ContainerInterface $c) => new ProductListAction($c->get(ProductRepository::class)),
            ProductDetailsAction::class => static fn (ContainerInterface $c) => new ProductDetailsAction($c->get(ProductRepository::class)),
        ]);

        $container = $containerBuilder->build();
        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $jsonErrorHandler = new JsonErrorHandler($app->getResponseFactory());
        $errorMiddleware->setDefaultErrorHandler(
            function (
                ServerRequestInterface $request,
                \Throwable $exception,
                bool $displayErrorDetails,
                bool $logErrors,
                bool $logErrorDetails
            ) use ($jsonErrorHandler) {
                return $jsonErrorHandler($exception);
            }
        );

        $app->get('/api/health', HealthAction::class);
        $app->post('/api/auth/login', AuthLoginAction::class);
        $app->post('/api/auth/register', AuthRegisterAction::class);
        $app->post('/api/auth/refresh', AuthRefreshAction::class);
        $app->post('/api/auth/logout', AuthLogoutAction::class);
        $app->get('/api/openapi.yaml', OpenApiAction::class);
        $app->get('/api/docs', SwaggerUiAction::class);
        $app->group('/api', function (\Slim\Routing\RouteCollectorProxy $group): void {
            $group->post('/import', ImportAction::class);
            $group->get('/import/{taskId}', ImportStatusAction::class);
            $group->get('/products', ProductListAction::class);
            $group->get('/products/{id}', ProductDetailsAction::class);
        })->add(function (ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) use ($app, $container): \Psr\Http\Message\ResponseInterface {
            $middleware = new AuthMiddleware(
                $container->get(TokenService::class),
                $app->getResponseFactory()
            );

            return $middleware->process($request, $handler);
        });

        return $app;
    }
}
