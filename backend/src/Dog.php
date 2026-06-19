<?php

declare(strict_types=1);

namespace AbqDog;

final readonly class Dog
{
    public function __construct(
        public string $dogName,
        public string $description,
        public string $photoFilename,
        public string $ownerName,
        public string $ownerEmail,
        public ?string $neighborhood,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromDogSubmission(
        DogSubmission $submission
    ): self {
        $now = self::utcTimestamp();

        return new self(
            $submission->dogName,
            $submission->description,
            $submission->photo->filename,
            $submission->ownerName,
            $submission->ownerEmail,
            $submission->neighborhood,
            'pending',
            $now,
            $now,
        );
    }

    private static function utcTimestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
