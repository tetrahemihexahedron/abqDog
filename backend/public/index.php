<?php

declare(strict_types=1);

use AbqDog\Handlers\DogsHandler;
use AbqDog\Handlers\HealthHandler;
use AbqDog\Handlers\SubmissionsHandler;
use AbqDog\Http;
use AbqDog\Router;

require __DIR__ . '/../vendor/autoload.php';

$routes = [
    '/data/health' => [
        'GET' => [HealthHandler::class, 'check'],
    ],
    '/data/dogs' => [
        'GET' => [DogsHandler::class, 'getApproved'],
    ],
    '/data/submissions' => [
        'POST' => [SubmissionsHandler::class, 'create'],
    ],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    $response = (new Router($routes))->dispatch($method, $path);
} catch (Throwable) {
    $response = Http::jsonError('Internal server error.', 500);
}

Http::send($response);
