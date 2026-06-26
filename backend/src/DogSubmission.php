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
        public PhotoUpload $photo,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $fields = [];

        $dogName = self::fieldValueAsString($request, 'dog_name');
        switch (true) {
            case self::isBlank($dogName):
                $fields['dog_name'] = "Please enter the pup's name.";
                break;
            case self::hasBadCharacters($dogName):
                $fields['dog_name'] = "There are some weird characters in that name. Has your dog chewed on your keyboard? Please try re-entering the name.";
                break;
            case self::isLongerThan($dogName, 80):
                $fields['dog_name'] = "Wow, that's long! We can't record or display a name that long. Please supply a shorter name, no more than 80 characters.";
                break;
        }

        $description = self::fieldValueAsString($request, 'description');
        switch (true) {
            case self::isBlank($description):
                $fields['description'] = "Every good dog deserves a little intro! Please write a short description.";
                break;
            case self::hasBadCharacters($description):
                $fields['description'] = 'There are some odd characters hiding in the description. Please try re-entering it.';
                break;
            case self::isShorterThan($description, 10):
                $fields['description'] = "We want to know more! Please write a description with at least 10 characters.";
                break;
            case self::isLongerThan($description, 500):
                $fields['description'] = "That's a whole tail-wagging biography! Please keep the description to 500 characters or fewer.";
                break;
        }

        $ownerName = self::fieldValueAsString($request, 'owner_name');
        switch (true) {
            case self::isBlank($ownerName):
                $fields['owner_name'] = "Please enter the name of the dog's human.";
                break;
            case self::hasBadCharacters($ownerName):
                $fields['owner_name'] = 'There are some strange characters in that name. Please re-enter it.';
                break;
            case self::isLongerThan($ownerName, 120):
                $fields['owner_name'] = "That's a very impressive name! Maybe the human has a shorter nickname, 120 characters or fewer?";
                break;
        }

        $ownerEmail = self::fieldValueAsString($request, 'owner_email');
        switch (true) {
            case self::isBlank($ownerEmail):
                $fields['owner_email'] = "Please enter your email.";
                break;
            case self::hasBadCharacters($ownerEmail):
                $fields['owner_email'] = 'There are some odd characters in the email address. Please re-enter it.';
                break;
            case self::isLongerThan($ownerEmail, 254):
                $fields['owner_email'] = 'That email address is too long for our records. Please enter an email address with 254 characters or fewer.';
                break;
            case filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) === false:
                $fields['owner_email'] = 'Please enter a valid email address.';
                break;
        }

        $neighborhood = self::fieldValueAsString($request, 'neighborhood');
        $neighborhoodValue = self::isBlank($neighborhood) ? null : $neighborhood;
        if ($neighborhoodValue !== null) {
            switch (true) {
                case self::hasBadCharacters($neighborhoodValue):
                    $fields['neighborhood'] = 'Please re-enter the name of the neighborhood. There are some odd characters in it.';
                    break;
                case self::isLongerThan($neighborhoodValue, 120):
                    $fields['neighborhood'] = 'That neighborhood name is surprisingly long. Please keep it to 120 characters or fewer.';
                    break;
            }
        }

        $photo = null;
        $status = 422;

        try {
            $photo = PhotoUpload::fromRequest($request);
        } catch (UploadException $exception) {
            $fields['photo'] = $exception->getMessage();
            $status = $exception->status;
        }

        if ($fields !== []) {
            throw new SubmissionValidationException($fields, $status);
        }

        // Shouldn't happen: helps static analysis tools know that $photo is definitely a PhotoUpload.
        if ($photo === null) {
            throw new \LogicException('Validated photo missing.');
        }

        return new self($dogName, $description, $ownerName, $ownerEmail, $neighborhoodValue, $photo);
    }

    private static function fieldValueAsString(Request $request, string $field): string
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

    private static function hasBadCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
    }

    private static function length(string $value): int
    {
        return mb_strlen($value);
    }
}
