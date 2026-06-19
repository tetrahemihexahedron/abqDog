<?php

declare(strict_types=1);

namespace AbqDog;

use InvalidArgumentException;

final readonly class DogPhoto
{
    public function __construct(public string $filename)
    {
        if (!self::isValidFilename($filename)) {
            throw new InvalidArgumentException('Invalid dog photo filename.');
        }
    }

    public function path(): string
    {
        return Config::dogImageUploadDir() . '/' . $this->filename;
    }

    private static function isValidFilename(string $filename): bool
    {
        if ($filename === '' || basename($filename) !== $filename) {
            return false;
        }

        return preg_match('/\A[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)\z/', $filename) === 1;
    }
}
