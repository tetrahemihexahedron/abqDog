<?php

declare(strict_types=1);

namespace AbqDog;

use Throwable;

final class Logger
{
    public static function error(string $message, ?Throwable $exception = null): void
    {
        if ($exception === null) {
            error_log($message);
            return;
        }

        error_log(sprintf(
            '%s exception=%s message=%s file=%s line=%d',
            $message,
            $exception::class,
            self::singleLine($exception->getMessage()),
            $exception->getFile(),
            $exception->getLine(),
        ));
    }

    private static function singleLine(string $value): string
    {
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
