<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\DownloadableFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadableFile::class)]
final class DownloadableFileExtendedTest extends TestCase
{
    #[Test]
    public function itCreatesUnlimited(): void
    {
        $file = DownloadableFile::createUnlimited('free.pdf', 'https://cdn.example.com/free.pdf', 100000);

        self::assertSame(0, $file->downloadLimit());
        self::assertTrue($file->isUnlimited());
    }

    #[Test]
    public function itCreatesWithExpiration(): void
    {
        $expires = new \DateTimeImmutable('+30 days');
        $file = DownloadableFile::createWithExpiration('temp.pdf', 'https://cdn.example.com/temp.pdf', 200000, 3, $expires);

        self::assertTrue($file->hasExpiration());
        self::assertSame($expires, $file->expiresAt());
        self::assertFalse($file->isExpired());
    }

    #[Test]
    public function itCalculatesFileSizeInMB(): void
    {
        $file = DownloadableFile::create('big.zip', 'https://cdn.example.com/big.zip', 10485760);
        self::assertSame(10.0, $file->fileSizeInMB());
    }

    #[Test]
    public function itCalculatesFileSizeInGB(): void
    {
        $file = DownloadableFile::create('huge.zip', 'https://cdn.example.com/huge.zip', 1073741824);
        self::assertSame(1.0, $file->fileSizeInGB());
    }

    #[Test]
    public function itGetsFileExtension(): void
    {
        $pdf = DownloadableFile::create('doc.pdf', 'https://cdn.example.com/doc.pdf', 100);
        $zip = DownloadableFile::create('archive.ZIP', 'https://cdn.example.com/a.zip', 100);
        $noExt = DownloadableFile::create('README', 'https://cdn.example.com/r', 100);

        self::assertSame('pdf', $pdf->getFileExtension());
        self::assertSame('zip', $zip->getFileExtension());
        self::assertSame('', $noExt->getFileExtension());
    }

    #[Test]
    public function itChecksEquality(): void
    {
        $a = DownloadableFile::create('a.pdf', 'https://cdn.example.com/a.pdf', 100);
        $b = DownloadableFile::create('a.pdf', 'https://cdn.example.com/a.pdf', 100);
        $c = DownloadableFile::create('b.pdf', 'https://cdn.example.com/b.pdf', 200);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $file = DownloadableFile::create('doc.pdf', 'https://cdn.example.com/doc.pdf', 1048576);
        $array = $file->toArray();

        self::assertSame('doc.pdf', $array['filename']);
        self::assertSame(1048576, $array['fileSizeBytes']);
        self::assertSame(1.0, $array['fileSizeMB']);
        self::assertSame(5, $array['downloadLimit']);
        self::assertFalse($array['isUnlimited']);
        self::assertFalse($array['hasExpiration']);
        self::assertSame('pdf', $array['fileExtension']);
    }

    #[Test]
    public function itThrowsOnInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP(S) URL');
        new DownloadableFile('a.pdf', 'ftp://cdn.example.com/a.pdf', 100);
    }

    #[Test]
    public function itThrowsOnExcessiveDownloadLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DownloadableFile('a.pdf', 'https://cdn.example.com/a.pdf', 100, 1000);
    }

    #[Test]
    public function itAcceptsHttpUrl(): void
    {
        $file = new DownloadableFile('a.pdf', 'http://cdn.example.com/a.pdf', 100);
        self::assertSame('http://cdn.example.com/a.pdf', $file->fileUrl());
    }
}
