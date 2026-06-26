<?php

declare(strict_types=1);

namespace AbqDog;

final readonly class Dog
{
    public function __construct(
        public string $dogName,
        public string $description,
        public DogPhoto $photo,
        public string $ownerName,
        public string $ownerEmail,
        public ?string $neighborhood,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromSubmission(
        DogSubmission $submission,
        DogPhoto $photo,
    ): self {
        return self::pending(
            $submission->dogName,
            $submission->description,
            $photo,
            $submission->ownerName,
            $submission->ownerEmail,
            $submission->neighborhood,
        );
    }

    private static function pending(
        string $dogName,
        string $description,
        DogPhoto $photo,
        string $ownerName,
        string $ownerEmail,
        ?string $neighborhood,
    ): self {
        $now = Database::now();

        return new self(
            $dogName,
            $description,
            $photo,
            $ownerName,
            $ownerEmail,
            $neighborhood,
            'pending',
            $now,
            $now,
        );
    }
}
