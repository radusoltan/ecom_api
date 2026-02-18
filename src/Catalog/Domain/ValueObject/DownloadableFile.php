<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use Webmozart\Assert\Assert;

/**
 * DownloadableFile value object for virtual/digital products.
 *
 * Business Rules:
 * - filename: Required, 1-255 characters
 * - fileUrl: Required, valid URL, max 500 characters
 * - fileSizeBytes: Must be > 0, max 2GB (2147483648 bytes)
 * - downloadLimit: 0 = unlimited, 1-999 = limited downloads
 * - expiresAt: Optional, must be in future if set
 */
final readonly class DownloadableFile
{
    private const MAX_FILE_SIZE = 2147483648; // 2GB in bytes
    private const MAX_DOWNLOAD_LIMIT = 999;

    public function __construct(
        private string $filename,
        private string $fileUrl,
        private int $fileSizeBytes,
        private int $downloadLimit = 5,
        private ?\DateTimeImmutable $expiresAt = null,
    ) {
        Assert::stringNotEmpty($this->filename, 'Filename cannot be empty');
        Assert::maxLength($this->filename, 255, 'Filename cannot exceed 255 characters');

        Assert::stringNotEmpty($this->fileUrl, 'File URL cannot be empty');
        Assert::maxLength($this->fileUrl, 500, 'File URL cannot exceed 500 characters');
        if (!str_starts_with($this->fileUrl, 'http://') && !str_starts_with($this->fileUrl, 'https://')) {
            throw new \InvalidArgumentException('File URL must be a valid HTTP(S) URL');
        }

        Assert::greaterThan($this->fileSizeBytes, 0, 'File size must be greater than 0');
        Assert::lessThanEq(
            $this->fileSizeBytes,
            self::MAX_FILE_SIZE,
            sprintf('File size cannot exceed 2GB (%d bytes)', self::MAX_FILE_SIZE)
        );

        Assert::greaterThanEq($this->downloadLimit, 0, 'Download limit cannot be negative');
        Assert::lessThanEq(
            $this->downloadLimit,
            self::MAX_DOWNLOAD_LIMIT,
            sprintf('Download limit cannot exceed %d', self::MAX_DOWNLOAD_LIMIT)
        );

        if (null !== $this->expiresAt) {
            Assert::greaterThan(
                $this->expiresAt,
                new \DateTimeImmutable(),
                'Expiration date must be in the future'
            );
        }
    }

    public static function create(
        string $filename,
        string $fileUrl,
        int $fileSizeBytes,
        int $downloadLimit = 5,
    ): self {
        return new self($filename, $fileUrl, $fileSizeBytes, $downloadLimit, null);
    }

    public static function createWithExpiration(
        string $filename,
        string $fileUrl,
        int $fileSizeBytes,
        int $downloadLimit,
        \DateTimeImmutable $expiresAt,
    ): self {
        return new self($filename, $fileUrl, $fileSizeBytes, $downloadLimit, $expiresAt);
    }

    public static function createUnlimited(
        string $filename,
        string $fileUrl,
        int $fileSizeBytes,
    ): self {
        return new self($filename, $fileUrl, $fileSizeBytes, 0, null);
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function fileUrl(): string
    {
        return $this->fileUrl;
    }

    public function fileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function downloadLimit(): int
    {
        return $this->downloadLimit;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isUnlimited(): bool
    {
        return 0 === $this->downloadLimit;
    }

    public function hasExpiration(): bool
    {
        return null !== $this->expiresAt;
    }

    public function isExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function fileSizeInMB(): float
    {
        return round($this->fileSizeBytes / 1024 / 1024, 2);
    }

    public function fileSizeInGB(): float
    {
        return round($this->fileSizeBytes / 1024 / 1024 / 1024, 2);
    }

    public function getFileExtension(): string
    {
        $parts = explode('.', $this->filename);

        return count($parts) > 1 ? strtolower(end($parts)) : '';
    }

    public function equals(self $other): bool
    {
        return $this->filename === $other->filename
            && $this->fileUrl === $other->fileUrl
            && $this->fileSizeBytes === $other->fileSizeBytes
            && $this->downloadLimit === $other->downloadLimit
            && $this->expiresAt == $other->expiresAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'fileUrl' => $this->fileUrl,
            'fileSizeBytes' => $this->fileSizeBytes,
            'fileSizeMB' => $this->fileSizeInMB(),
            'downloadLimit' => $this->downloadLimit,
            'isUnlimited' => $this->isUnlimited(),
            'expiresAt' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'hasExpiration' => $this->hasExpiration(),
            'isExpired' => $this->isExpired(),
            'fileExtension' => $this->getFileExtension(),
        ];
    }
}
