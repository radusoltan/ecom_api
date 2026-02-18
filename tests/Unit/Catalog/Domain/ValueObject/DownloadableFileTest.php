<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\DownloadableFile;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\InvalidArgumentException;

final class DownloadableFileTest extends TestCase
{
    public function testCreateDownloadableFileSuccessfully(): void
    {
        $file = DownloadableFile::create(
            filename: 'ebook.pdf',
            fileUrl: 'https://cdn.example.com/files/ebook.pdf',
            fileSizeBytes: 5242880, // 5MB
            downloadLimit: 10
        );

        $this->assertSame('ebook.pdf', $file->filename());
        $this->assertSame('https://cdn.example.com/files/ebook.pdf', $file->fileUrl());
        $this->assertSame(5242880, $file->fileSizeBytes());
        $this->assertSame(10, $file->downloadLimit());
        $this->assertNull($file->expiresAt());
        $this->assertFalse($file->hasExpiration());
        $this->assertFalse($file->isExpired());
        $this->assertFalse($file->isUnlimited());
    }

    public function testCreateWithDefaultDownloadLimit(): void
    {
        $file = DownloadableFile::create(
            filename: 'software.zip',
            fileUrl: 'https://cdn.example.com/software.zip',
            fileSizeBytes: 104857600 // 100MB
        );

        $this->assertSame(5, $file->downloadLimit());
    }

    public function testCreateUnlimitedDownloads(): void
    {
        $file = DownloadableFile::createUnlimited(
            filename: 'whitepaper.pdf',
            fileUrl: 'https://cdn.example.com/whitepaper.pdf',
            fileSizeBytes: 2097152 // 2MB
        );

        $this->assertSame(0, $file->downloadLimit());
        $this->assertTrue($file->isUnlimited());
    }

    public function testCreateWithExpiration(): void
    {
        $expiresAt = new \DateTimeImmutable('+30 days');
        $file = DownloadableFile::createWithExpiration(
            filename: 'trial.exe',
            fileUrl: 'https://cdn.example.com/trial.exe',
            fileSizeBytes: 52428800, // 50MB
            downloadLimit: 3,
            expiresAt: $expiresAt
        );

        $this->assertSame($expiresAt, $file->expiresAt());
        $this->assertTrue($file->hasExpiration());
        $this->assertFalse($file->isExpired());
    }

    public function testEmptyFilenameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filename cannot be empty');

        DownloadableFile::create(
            filename: '',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024
        );
    }

    public function testFilenameTooLongThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filename cannot exceed 255 characters');

        DownloadableFile::create(
            filename: str_repeat('a', 256),
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024
        );
    }

    public function testEmptyFileUrlThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File URL cannot be empty');

        DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: '',
            fileSizeBytes: 1024
        );
    }

    public function testInvalidFileUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File URL must be a valid HTTP(S) URL');

        DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'ftp://cdn.example.com/file.pdf',
            fileSizeBytes: 1024
        );
    }

    public function testFileUrlTooLongThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File URL cannot exceed 500 characters');

        DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/'.str_repeat('a', 500),
            fileSizeBytes: 1024
        );
    }

    public function testZeroFileSizeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File size must be greater than 0');

        DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 0
        );
    }

    public function testFileSizeExceedsMaximumThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File size cannot exceed 2GB');

        DownloadableFile::create(
            filename: 'huge-file.iso',
            fileUrl: 'https://cdn.example.com/huge-file.iso',
            fileSizeBytes: 2147483649 // 2GB + 1 byte
        );
    }

    public function testNegativeDownloadLimitThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Download limit cannot be negative');

        DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024,
            downloadLimit: -1
        );
    }

    public function testDownloadLimitExceedsMaximumThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Download limit cannot exceed 999');

        DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024,
            downloadLimit: 1000
        );
    }

    public function testPastExpirationDateThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expiration date must be in the future');

        DownloadableFile::createWithExpiration(
            filename: 'expired.pdf',
            fileUrl: 'https://cdn.example.com/expired.pdf',
            fileSizeBytes: 1024,
            downloadLimit: 5,
            expiresAt: new \DateTimeImmutable('-1 day')
        );
    }

    public function testIsExpiredReturnsTrueForExpiredFile(): void
    {
        // Create a file with an expiration date very close to now
        $file = new \ReflectionClass(DownloadableFile::class);
        $instance = $file->newInstanceWithoutConstructor();

        $expiresAtProperty = $file->getProperty('expiresAt');
        $expiresAtProperty->setValue($instance, new \DateTimeImmutable('-1 second'));

        $this->assertTrue($instance->isExpired());
    }

    public function testFileSizeInMB(): void
    {
        $file = DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 5242880 // 5MB
        );

        $this->assertSame(5.0, $file->fileSizeInMB());
    }

    public function testFileSizeInGB(): void
    {
        $file = DownloadableFile::create(
            filename: 'large-file.zip',
            fileUrl: 'https://cdn.example.com/large-file.zip',
            fileSizeBytes: 1073741824 // 1GB
        );

        $this->assertSame(1.0, $file->fileSizeInGB());
    }

    public function testGetFileExtension(): void
    {
        $file = DownloadableFile::create(
            filename: 'document.PDF',
            fileUrl: 'https://cdn.example.com/document.pdf',
            fileSizeBytes: 1024
        );

        $this->assertSame('pdf', $file->getFileExtension());
    }

    public function testGetFileExtensionWithMultipleDots(): void
    {
        $file = DownloadableFile::create(
            filename: 'archive.tar.gz',
            fileUrl: 'https://cdn.example.com/archive.tar.gz',
            fileSizeBytes: 1024
        );

        $this->assertSame('gz', $file->getFileExtension());
    }

    public function testGetFileExtensionWithNoExtension(): void
    {
        $file = DownloadableFile::create(
            filename: 'README',
            fileUrl: 'https://cdn.example.com/README',
            fileSizeBytes: 1024
        );

        $this->assertSame('', $file->getFileExtension());
    }

    public function testEquals(): void
    {
        $file1 = DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024,
            downloadLimit: 5
        );

        $file2 = DownloadableFile::create(
            filename: 'file.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024,
            downloadLimit: 5
        );

        $this->assertTrue($file1->equals($file2));
    }

    public function testNotEqualsDifferentFilename(): void
    {
        $file1 = DownloadableFile::create(
            filename: 'file1.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024
        );

        $file2 = DownloadableFile::create(
            filename: 'file2.pdf',
            fileUrl: 'https://cdn.example.com/file.pdf',
            fileSizeBytes: 1024
        );

        $this->assertFalse($file1->equals($file2));
    }

    public function testToArray(): void
    {
        $expiresAt = new \DateTimeImmutable('+30 days');
        $file = DownloadableFile::createWithExpiration(
            filename: 'software.exe',
            fileUrl: 'https://cdn.example.com/software.exe',
            fileSizeBytes: 52428800, // 50MB
            downloadLimit: 3,
            expiresAt: $expiresAt
        );

        $array = $file->toArray();

        $this->assertSame('software.exe', $array['filename']);
        $this->assertSame('https://cdn.example.com/software.exe', $array['fileUrl']);
        $this->assertSame(52428800, $array['fileSizeBytes']);
        $this->assertSame(50.0, $array['fileSizeMB']);
        $this->assertSame(3, $array['downloadLimit']);
        $this->assertFalse($array['isUnlimited']);
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $array['expiresAt']);
        $this->assertTrue($array['hasExpiration']);
        $this->assertFalse($array['isExpired']);
        $this->assertSame('exe', $array['fileExtension']);
    }

    public function testToArrayWithoutExpiration(): void
    {
        $file = DownloadableFile::createUnlimited(
            filename: 'open-source.zip',
            fileUrl: 'https://cdn.example.com/open-source.zip',
            fileSizeBytes: 10485760 // 10MB
        );

        $array = $file->toArray();

        $this->assertNull($array['expiresAt']);
        $this->assertFalse($array['hasExpiration']);
        $this->assertTrue($array['isUnlimited']);
    }
}
