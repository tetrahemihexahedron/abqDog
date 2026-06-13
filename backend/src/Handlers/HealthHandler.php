<?php

declare(strict_types=1);

namespace AbqDog\Handlers;

use AbqDog\Http;
use AbqDog\Response;

final class HealthHandler
{
    public static function show(): Response
    {
        return Http::jsonResponse(['ok' => true]);
    }
}
