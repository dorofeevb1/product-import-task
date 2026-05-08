<?php

declare(strict_types=1);

use App\Infrastructure\Http\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

$app = AppFactory::create();
$app->run();
