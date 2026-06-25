<?php

declare(strict_types=1);

namespace AbqDog;

final readonly class PhotoUpload
{
    private const int MAX_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private function __construct(
        public string $temporaryPath,
        public int $size,
        public string $mimeType,
    ) {
    }

    /**
     * @throws UploadException
     */
    public static function fromRequest(Request $request, string $fieldName = 'photo'): self
    {
        if (!array_key_exists($fieldName, $request->files)) {
            throw new UploadException('Please add a photo of your dog.', 422);
        }

        $file = $request->files[$fieldName];
        if (!is_array($file)) {
            throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
        }

        foreach (['tmp_name', 'size', 'error'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $file)) {
                throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
            }
        }

        if (is_array($file['tmp_name']) || is_array($file['size']) || is_array($file['error'])) {
            throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
        }

        $error = self::intValue($file['error']);
        if ($error === null) {
            throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
        }

        match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_NO_FILE => throw new UploadException('Please add a photo of your dog.', 422),
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new UploadException('That photo is too big. Please choose an image under 5 MB.', 413),
            UPLOAD_ERR_PARTIAL => throw new UploadException('The photo upload did not finish. Please try again.', 422),
            default => throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400),
        };

        $temporaryPath = $file['tmp_name'];
        if (!is_string($temporaryPath) || $temporaryPath === '') {
            throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
        }

        $size = self::intValue($file['size']);
        if ($size === null || $size < 0) {
            throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
        }

        if ($size === 0) {
            throw new UploadException('That photo appears to be empty. Please choose another photo.', 422);
        }

        if ($size > self::MAX_BYTES) {
            throw new UploadException('That photo is too big. Please choose an image under 5 MB.', 413);
        }

        $mimeType = self::detectMimeType($temporaryPath);
        if ($mimeType === null) {
            throw new UploadException('That photo upload was malformed. Please try choosing the file again.', 400);
        }

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new UploadException('That photo type is not supported. Please use a JPG, PNG, or WebP image.', 415);
        }

        return new self($temporaryPath, $size, $mimeType);
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
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = @$finfo->file($temporaryPath);

        if (!is_string($mimeType) || $mimeType === '') {
            return null;
        }

        return $mimeType;
    }
}
