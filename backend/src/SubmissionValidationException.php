<?php

declare(strict_types=1);

namespace AbqDog;

use InvalidArgumentException;

final class SubmissionValidationException extends InvalidArgumentException
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(private readonly array $fields)
    {
        parent::__construct('Validation failed.');
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
