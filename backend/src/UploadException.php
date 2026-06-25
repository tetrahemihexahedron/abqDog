<?php

declare(strict_types=1);

namespace AbqDog;

use DomainException;

final class UploadException extends DomainException
{
    public function __construct(
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }
}
