<?php

declare(strict_types=1);

namespace AbqDog\Handlers;

use AbqDog\Http;
use AbqDog\Response;

final class SubmissionsHandler
{
    public static function create(): Response
    {
        return Http::jsonError('Submission handling is not implemented yet.', 501);
    }
}
