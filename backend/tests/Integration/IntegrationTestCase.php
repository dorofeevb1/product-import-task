<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Doctrine\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_DEBUG'] = '1';
        $_ENV['DB_DRIVER'] = 'pdo_sqlite';
        $_ENV['DB_PATH'] = ':memory:';
        $this->entityManager = EntityManagerFactory::create();
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema($metadata);
        $this->entityManager->getConnection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS import_rate_limits (ip VARCHAR(64) PRIMARY KEY, attempts INT NOT NULL, window_start INT NOT NULL)'
        );
    }
}
