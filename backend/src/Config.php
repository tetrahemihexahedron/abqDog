<?php

declare(strict_types=1);

namespace AbqDog;

final class Config
{
    public static function databasePath(): string
    {
        return getenv('DATABASE_PATH') ?: '/data/abqdog.sqlite';
    }

    public static function dogImageUploadDir(): string
    {
        return rtrim(getenv('DOG_IMAGE_UPLOAD_DIR') ?: '/uploads/dogs', '/');
    }

    public static function dogImageUrlBase(): string
    {
        return rtrim(getenv('DOG_IMAGE_URL_BASE') ?: '/img/dogs', '/');
    }
}
