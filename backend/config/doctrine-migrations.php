<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\EntityManagerFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

$entityManager = EntityManagerFactory::create();

return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],
    'migrations_paths' => [
        'DoctrineMigrations' => __DIR__ . '/../migrations',
    ],
    'em' => $entityManager,
];
