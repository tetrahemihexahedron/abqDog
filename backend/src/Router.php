<?php

declare(strict_types=1);

namespace AbqDog;

final class Router
{
    /**
     * @param array<string, array<string, callable(): Response>> $routes
     */
    public function __construct(private readonly array $routes) {}

    public function dispatch(string $method, string $path): Response
    {
        if (!isset($this->routes[$path])) {
            return Http::jsonError('Not found.', 404);
        }

        $handlersByMethod = $this->routes[$path];

        if (!isset($handlersByMethod[$method])) {
            return Http::methodNotAllowed(array_keys($handlersByMethod));
        }

        return $handlersByMethod[$method]();
    }
}
