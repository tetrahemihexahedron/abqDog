<?php

declare(strict_types=1);

namespace AbqDog;

final class Http
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function jsonResponse(array $payload, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string> $headers
     */
    public static function jsonError(string $message, int $status, array $details = [], array $headers = []): void
    {
        self::jsonResponse(['error' => $message] + $details, $status, $headers);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): void
    {
        self::jsonError('Method not allowed.', 405, [], [
            'Allow' => implode(', ', $allowedMethods),
        ]);
    }
}
