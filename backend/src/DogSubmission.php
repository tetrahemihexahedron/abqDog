<?php

declare(strict_types=1);

namespace AbqDog;

final readonly class DogSubmission
{
    private function __construct(
        public string $dogName,
        public string $description,
        public string $ownerName,
        public string $ownerEmail,
        public ?string $neighborhood,
        public DogPhoto $photo,
    ) {
    }

    public static function fromRequest(Request $request, DogPhoto $photo): self
    {
        $fields = [];

        $dogName = self::stringField($request, 'dog_name');
        switch (true) {
            case self::isBlank($dogName):
                $fields['dog_name'] = 'Dog name is required.';
                break;
            case self::isLongerThan($dogName, 80):
                $fields['dog_name'] = 'Dog name must be 80 characters or fewer.';
                break;
            case self::hasDisallowedControlCharacters($dogName):
                $fields['dog_name'] = 'Dog name contains unsupported characters.';
                break;
        }

        $description = self::stringField($request, 'description');
        switch (true) {
            case self::isBlank($description):
                $fields['description'] = 'Description is required.';
                break;
            case self::isShorterThan($description, 10):
                $fields['description'] = 'Description must be at least 10 characters.';
                break;
            case self::isLongerThan($description, 500):
                $fields['description'] = 'Description must be 500 characters or fewer.';
                break;
            case self::hasDisallowedControlCharacters($description):
                $fields['description'] = 'Description contains unsupported characters.';
                break;
        }

        $ownerName = self::stringField($request, 'owner_name');
        switch (true) {
            case self::isBlank($ownerName):
                $fields['owner_name'] = 'Owner name is required.';
                break;
            case self::isLongerThan($ownerName, 120):
                $fields['owner_name'] = 'Owner name must be 120 characters or fewer.';
                break;
            case self::hasDisallowedControlCharacters($ownerName):
                $fields['owner_name'] = 'Owner name contains unsupported characters.';
                break;
        }

        $ownerEmail = self::stringField($request, 'owner_email');
        switch (true) {
            case self::isBlank($ownerEmail):
                $fields['owner_email'] = 'Owner email is required.';
                break;
            case self::isLongerThan($ownerEmail, 254):
                $fields['owner_email'] = 'Owner email must be 254 characters or fewer.';
                break;
            case filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false:
                $fields['owner_email'] = 'Owner email must be a valid email address.';
                break;
            case self::hasDisallowedControlCharacters($ownerEmail):
                $fields['owner_email'] = 'Owner email contains unsupported characters.';
                break;
        }

        $neighborhood = self::stringField($request, 'neighborhood');
        $neighborhoodValue = self::isBlank($neighborhood) ? null : $neighborhood;
        if ($neighborhoodValue !== null) {
            switch (true) {
                case self::isLongerThan($neighborhoodValue, 120):
                    $fields['neighborhood'] = 'Neighborhood must be 120 characters or fewer.';
                    break;
                case self::hasDisallowedControlCharacters($neighborhoodValue):
                    $fields['neighborhood'] = 'Neighborhood contains unsupported characters.';
                    break;
            }
        }

        if ($fields !== []) {
            throw new SubmissionValidationException($fields);
        }

        return new self($dogName, $description, $ownerName, $ownerEmail, $neighborhoodValue, $photo);
    }

    private static function stringField(Request $request, string $field): string
    {
        $value = $request->post[$field] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private static function isBlank(string $value): bool
    {
        return $value === '';
    }

    private static function isShorterThan(string $value, int $minLength): bool
    {
        return self::length($value) < $minLength;
    }

    private static function isLongerThan(string $value, int $maxLength): bool
    {
        return self::length($value) > $maxLength;
    }

    private static function hasDisallowedControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
    }

    private static function length(string $value): int
    {
        return mb_strlen($value);
    }
}
