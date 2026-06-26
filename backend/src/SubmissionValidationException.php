<?php

declare(strict_types=1);

namespace AbqDog;

use DomainException;

final class SubmissionValidationException extends DomainException
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        private readonly array $fields,
        public readonly int $status = 422,
    ) {
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
