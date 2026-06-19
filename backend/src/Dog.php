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
        return self::pending(
            $submission->dogName,
            $submission->description,
            $submission->photo->filename,
            $submission->ownerName,
            $submission->ownerEmail,
            $submission->neighborhood,
        );
    }

    public static function placeholderForSubmission(): self
    {
        return self::pending(
            'Placeholder Dog',
            'Placeholder description until submission validation is implemented.',
            'placeholder.jpg',
            'Placeholder Owner',
            'placeholder-owner@example.test',
            null,
        );
    }

    private static function pending(
        string $dogName,
        string $description,
        string $photoFilename,
        string $ownerName,
        string $ownerEmail,
        ?string $neighborhood,
    ): self {
        $now = Database::now();

        return new self(
            $dogName,
            $description,
            $photoFilename,
            $ownerName,
            $ownerEmail,
            $neighborhood,
            'pending',
            $now,
            $now,
        );
    }
}
