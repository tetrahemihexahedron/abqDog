<?php

declare(strict_types=1);

namespace AbqDog;

final class Router
{
    /**
     * @param array<string, array<string, callable(): void>> $routes
     */
    public function __construct(private readonly array $routes)
    {
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (!isset($this->routes[$path])) {
            Http::jsonError('Not found.', 404);
            return;
        }

        $handlersByMethod = $this->routes[$path];

        if (!isset($handlersByMethod[$method])) {
            Http::methodNotAllowed(array_keys($handlersByMethod));
            return;
        }

        $handlersByMethod[$method]();
    }
}
