<?php

declare(strict_types=1);

namespace AbqDog;

use RuntimeException;

final class PhotoStorer
{
    /** @var array<string, string> */
    private const array EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
    ];

    public function store(PhotoUpload $photo): DogPhoto
    {
        $this->ensureUploadDirectory();

        $storedPhoto = new DogPhoto(bin2hex(random_bytes(16)) . $this->extensionForMimeType($photo->mimeType));

        if (!move_uploaded_file($photo->temporaryPath, $storedPhoto->path())) {
            throw new RuntimeException('Could not store uploaded photo.');
        }

        return $storedPhoto;
    }

    public function deleteIfPresent(?DogPhoto $photo): void
    {
        if ($photo === null) {
            return;
        }

        $path = $photo->path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return self::EXTENSIONS_BY_MIME_TYPE[$mimeType]
            ?? throw new RuntimeException('Unsupported stored photo type.');
    }

    private function ensureUploadDirectory(): void
    {
        $directory = Config::dogImageUploadDir();

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0o755, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not prepare photo storage.');
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('Could not prepare photo storage.');
        }
    }
}
