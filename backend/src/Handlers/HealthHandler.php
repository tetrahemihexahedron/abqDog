<?php

declare(strict_types=1);

namespace AbqDog\Handlers;

use AbqDog\Http;

final class HealthHandler
{
    public static function show(): void
    {
        Http::jsonResponse(['ok' => true]);
    }
}
