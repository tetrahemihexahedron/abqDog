<?php

declare(strict_types=1);

namespace AbqDog;

final readonly class Request
{
    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public function __construct(
        public array $post,
        public array $files,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_POST, $_FILES);
    }
}
