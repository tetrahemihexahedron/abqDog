<?php

declare(strict_types=1);

namespace AbqDog;

final readonly class Response
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $payload,
        public array $headers = [],
    ) {}
}
