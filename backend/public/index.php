<?php

declare(strict_types=1);

use AbqDog\Handlers\HealthHandler;
use AbqDog\Http;
use AbqDog\Router;

require __DIR__ . '/../vendor/autoload.php';

$routes = [
    '/api/health' => [
        'GET' => [HealthHandler::class, 'show'],
    ],
];

try {
    (new Router($routes))->dispatch();
} catch (Throwable) {
    Http::jsonError('Internal server error.', 500);
}
