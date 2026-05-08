<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

final class EntityManagerFactory
{
    public static function create(): EntityManager
    {
        $paths = [__DIR__ . '/../../Domain'];
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, (bool) ($_ENV['APP_DEBUG'] ?? true));

        $driver = $_ENV['DB_DRIVER'] ?? 'pdo_pgsql';
        $connectionParams = ['driver' => $driver];
        if ($driver === 'pdo_sqlite') {
            $connectionParams['path'] = $_ENV['DB_PATH'] ?? ($_ENV['DB_NAME'] ?? ':memory:');
        } else {
            $connectionParams['host'] = $_ENV['DB_HOST'] ?? 'db';
            $connectionParams['port'] = (int) ($_ENV['DB_PORT'] ?? 5432);
            $connectionParams['user'] = $_ENV['DB_USER'] ?? 'app';
            $connectionParams['password'] = $_ENV['DB_PASSWORD'] ?? 'app';
            $connectionParams['dbname'] = $_ENV['DB_NAME'] ?? 'products';
        }

        $connection = DriverManager::getConnection($connectionParams, $config);

        return new EntityManager($connection, $config);
    }
}
