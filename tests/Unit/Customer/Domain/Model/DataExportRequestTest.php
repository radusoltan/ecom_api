<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\ExportStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DataExportRequestTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
    }

    public function testCreate(): void
    {
        $request = DataExportRequest::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId
        );

        self::assertInstanceOf(DataExportRequestId::class, $request->id());
        self::assertTrue($this->customerId->equals($request->customerId()));
        self::assertTrue($this->tenantId->equals($request->tenantId()));
        self::assertEquals(ExportStatus::PENDING, $request->status());
        self::assertNull($request->filePath());
        self::assertNull($request->downloadToken());
        self::assertNull($request->expiresAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->createdAt());
        self::assertNull($request->completedAt());
        self::assertNull($request->errorMessage());
    }

    public function testMarkProcessing(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        $request->markProcessing();

        self::assertEquals(ExportStatus::PROCESSING, $request->status());
    }

    public function testMarkProcessingThrowsWhenNotPending(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot transition from Processing to PROCESSING');

        $request->markProcessing();
    }

    public function testMarkReady(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $filePath = '/var/exports/test.json';
        $downloadToken = 'secure-token-123';
        $expiresAt = new \DateTimeImmutable('+24 hours');

        $request->markReady($filePath, $downloadToken, $expiresAt);

        self::assertEquals(ExportStatus::READY, $request->status());
        self::assertEquals($filePath, $request->filePath());
        self::assertEquals($downloadToken, $request->downloadToken());
        self::assertEquals($expiresAt, $request->expiresAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->completedAt());
        self::assertNull($request->errorMessage());
    }

    public function testMarkReadyThrowsWhenNotProcessing(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot transition from Pending to READY');

        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));
    }

    public function testMarkReadyThrowsWhenFilePathEmpty(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path cannot be empty');

        $request->markReady('', 'token', new \DateTimeImmutable('+24 hours'));
    }

    public function testMarkReadyThrowsWhenDownloadTokenEmpty(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Download token cannot be empty');

        $request->markReady('/path/file.json', '', new \DateTimeImmutable('+24 hours'));
    }

    public function testMarkReadyThrowsWhenExpirationInPast(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expiration date must be in the future');

        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('-1 hour'));
    }

    public function testMarkFailed(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $errorMessage = 'Failed to generate export: disk full';

        $request->markFailed($errorMessage);

        self::assertEquals(ExportStatus::FAILED, $request->status());
        self::assertEquals($errorMessage, $request->errorMessage());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->completedAt());
    }

    public function testMarkFailedThrowsWhenErrorMessageEmpty(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error message cannot be empty');

        $request->markFailed('');
    }

    public function testMarkExpired(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));

        $request->markExpired();

        self::assertEquals(ExportStatus::EXPIRED, $request->status());
    }

    public function testMarkExpiredThrowsWhenNotReady(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot transition from Pending to EXPIRED');

        $request->markExpired();
    }

    public function testIsExpiredReturnsFalseWhenNotReady(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        self::assertFalse($request->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenNotExpired(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));

        self::assertFalse($request->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpired(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $request->markReady('/path/file.json', 'token', $expiresAt);

        // Check expiry by passing a time after the expiration
        $afterExpiry = $expiresAt->modify('+1 second');
        self::assertTrue($request->isExpired($afterExpiry));
    }

    public function testIsDownloadableReturnsTrueWhenReady(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));

        self::assertTrue($request->isDownloadable());
    }

    public function testIsDownloadableReturnsFalseWhenNotReady(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        self::assertFalse($request->isDownloadable());
    }

    public function testIsDownloadableReturnsFalseWhenExpired(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $request->markReady('/path/file.json', 'token', $expiresAt);

        // isDownloadable() calls isExpired() which uses current time
        // Pass a time after expiry to verify behavior
        $afterExpiry = $expiresAt->modify('+1 second');
        self::assertFalse($request->isDownloadable($afterExpiry));
    }

    public function testReconstituteFromPersistence(): void
    {
        $id = DataExportRequestId::generate();
        $filePath = '/path/file.json';
        $downloadToken = 'token-123';
        $expiresAt = new \DateTimeImmutable('+24 hours');
        $createdAt = new \DateTimeImmutable('-1 hour');
        $completedAt = new \DateTimeImmutable();
        $errorMessage = 'Some error';

        $request = DataExportRequest::reconstituteFromPersistence(
            id: $id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: ExportStatus::FAILED,
            filePath: $filePath,
            downloadToken: $downloadToken,
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            completedAt: $completedAt,
            errorMessage: $errorMessage
        );

        self::assertTrue($id->equals($request->id()));
        self::assertEquals(ExportStatus::FAILED, $request->status());
        self::assertEquals($filePath, $request->filePath());
        self::assertEquals($downloadToken, $request->downloadToken());
        self::assertEquals($expiresAt, $request->expiresAt());
        self::assertEquals($createdAt, $request->createdAt());
        self::assertEquals($completedAt, $request->completedAt());
        self::assertEquals($errorMessage, $request->errorMessage());
    }
}
