<?php

declare(strict_types=1);

namespace AbqDog;

use InvalidArgumentException;

abstract readonly class Upload
{
    /** @var list<string> */
    protected const array ALLOWED_MIME_TYPES = [];

    protected function __construct(
        public string $fieldName,
        public string $temporaryPath,
        public int $size,
        public string $mimeType,
    ) {
    }

    /**
     * @throws UploadValidationException
     */
    protected static function fromRequest(
        Request $request,
        string $fieldName,
        int $maxBytes,
    ): static {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException('Maximum upload size must be positive.');
        }

        if (!array_key_exists($fieldName, $request->files)) {
            throw new UploadValidationException(static::missingUploadMessage(), 422);
        }

        $file = $request->files[$fieldName];
        if (!is_array($file)) {
            throw new UploadValidationException(static::malformedUploadMessage(), 400);
        }

        foreach (['tmp_name', 'size', 'error'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $file)) {
                throw new UploadValidationException(static::malformedUploadMessage(), 400);
            }
        }

        if (self::hasArrayValue($file['tmp_name']) || self::hasArrayValue($file['size']) || self::hasArrayValue($file['error'])) {
            throw new UploadValidationException(static::malformedUploadMessage(), 400);
        }

        $error = self::intValue($file['error']);
        if ($error === null) {
            throw new UploadValidationException(static::malformedUploadMessage(), 400);
        }

        match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_NO_FILE => throw new UploadValidationException(static::missingUploadMessage(), 422),
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new UploadValidationException(static::tooLargeMessage($maxBytes), 413),
            UPLOAD_ERR_PARTIAL => throw new UploadValidationException(static::incompleteUploadMessage(), 422),
            default => throw new UploadValidationException(static::malformedUploadMessage(), 400),
        };

        $temporaryPath = $file['tmp_name'];
        if (!is_string($temporaryPath) || $temporaryPath === '') {
            throw new UploadValidationException(static::malformedUploadMessage(), 400);
        }

        $size = self::intValue($file['size']);
        if ($size === null || $size < 0) {
            throw new UploadValidationException(static::malformedUploadMessage(), 400);
        }

        if ($size === 0) {
            throw new UploadValidationException(static::emptyUploadMessage(), 422);
        }

        if ($size > $maxBytes) {
            throw new UploadValidationException(static::tooLargeMessage($maxBytes), 413);
        }

        $mimeType = self::detectMimeType($temporaryPath);
        if ($mimeType === null) {
            throw new UploadValidationException(static::malformedUploadMessage(), 400);
        }

        $allowedMimeTypes = static::allowedMimeTypes();
        if ($allowedMimeTypes !== [] && !in_array($mimeType, $allowedMimeTypes, true)) {
            throw new UploadValidationException(static::unsupportedMimeTypeMessage(), 415);
        }

        return new static($fieldName, $temporaryPath, $size, $mimeType);
    }

    /**
     * @return list<string>
     */
    protected static function allowedMimeTypes(): array
    {
        return static::ALLOWED_MIME_TYPES;
    }

    private static function hasArrayValue(mixed $value): bool
    {
        return is_array($value);
    }

    private static function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private static function detectMimeType(string $temporaryPath): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        try {
            $mimeType = @finfo_file($finfo, $temporaryPath);
        } finally {
            finfo_close($finfo);
        }

        if (!is_string($mimeType) || $mimeType === '') {
            return null;
        }

        return $mimeType;
    }

    protected static function malformedUploadMessage(): string
    {
        return 'That file upload was malformed. Please try choosing the file again.';
    }

    protected static function missingUploadMessage(): string
    {
        return 'Please add a file to upload.';
    }

    protected static function emptyUploadMessage(): string
    {
        return 'That file appears to be empty. Please choose another file.';
    }

    protected static function incompleteUploadMessage(): string
    {
        return 'The file upload did not finish. Please try again.';
    }

    protected static function tooLargeMessage(int $maxBytes): string
    {
        $maxMegabytes = max(1, (int) floor($maxBytes / 1024 / 1024));

        return sprintf('That file is too big. Please choose a file under %d MB.', $maxMegabytes);
    }

    protected static function unsupportedMimeTypeMessage(): string
    {
        return 'That file type is not supported.';
    }
}
