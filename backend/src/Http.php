<?php

declare(strict_types=1);

namespace AbqDog;

final class Http
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function jsonResponse(array $payload, int $status = 200, array $headers = []): Response
    {
        return new Response($status, $payload, $headers);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string> $headers
     */
    public static function jsonError(string $message, int $status, array $details = [], array $headers = []): Response
    {
        return self::jsonResponse(['error' => $message] + $details, $status, $headers);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): Response
    {
        return self::jsonError('Method not allowed.', 405, [], [
            'Allow' => implode(', ', $allowedMethods),
        ]);
    }

    public static function send(Response $response): void
    {
        http_response_code($response->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($response->payload, JSON_THROW_ON_ERROR);
    }
}
