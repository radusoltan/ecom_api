<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\DataExportRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\ExportStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataExportRequest::class)]
final class DataExportRequestExtendedTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
    }

    // -----------------------------------------------------------------------
    // create — with privacyRequestId
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesWithPrivacyRequestId(): void
    {
        $privacyId = 'priv-req-abc-456';

        $request = DataExportRequest::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            privacyRequestId: $privacyId,
        );

        self::assertSame($privacyId, $request->privacyRequestId());
        self::assertSame(ExportStatus::PENDING, $request->status());
    }

    #[Test]
    public function itCreatesWithNullPrivacyRequestId(): void
    {
        $request = DataExportRequest::create(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
        );

        self::assertNull($request->privacyRequestId());
    }

    // -----------------------------------------------------------------------
    // markFailed — directly from PENDING (valid transition)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCanMarkFailedFromPendingStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        $request->markFailed('Validation failed before processing');

        self::assertSame(ExportStatus::FAILED, $request->status());
        self::assertSame('Validation failed before processing', $request->errorMessage());
        self::assertInstanceOf(\DateTimeImmutable::class, $request->completedAt());
    }

    #[Test]
    public function itThrowsWhenMarkingFailedFromReadyStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot transition from Ready to FAILED');

        $request->markFailed('Some error');
    }

    #[Test]
    public function itThrowsWhenMarkingFailedFromExpiredStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));
        $request->markExpired();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot transition from Expired to FAILED');

        $request->markFailed('Some error');
    }

    #[Test]
    public function itThrowsWhenMarkingFailedFromAlreadyFailedStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markFailed('First failure');

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot transition from Failed to FAILED');

        $request->markFailed('Second failure');
    }

    // -----------------------------------------------------------------------
    // markProcessing — from non-pending status (invalid)
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenMarkingProcessingFromFailedStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markFailed('Error');

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot transition from Failed to PROCESSING');

        $request->markProcessing();
    }

    // -----------------------------------------------------------------------
    // markExpired — invalid transitions
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenMarkingExpiredFromProcessingStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot transition from Processing to EXPIRED');

        $request->markExpired();
    }

    #[Test]
    public function itThrowsWhenMarkingExpiredFromAlreadyExpiredStatus(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));
        $request->markExpired();

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Cannot transition from Expired to EXPIRED');

        $request->markExpired();
    }

    // -----------------------------------------------------------------------
    // markReady — validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenMarkReadyFilePathIsWhitespace(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('File path cannot be empty');

        $request->markReady('   ', 'token', new \DateTimeImmutable('+24 hours'));
    }

    #[Test]
    public function itThrowsWhenMarkReadyTokenIsWhitespace(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Download token cannot be empty');

        $request->markReady('/path/file.json', '   ', new \DateTimeImmutable('+24 hours'));
    }

    // -----------------------------------------------------------------------
    // isExpired — null expiresAt (READY but no expiry set)
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsNotExpiredWhenNoExpiresAtIsSet(): void
    {
        // Force a READY state with null expiresAt via reconstitute
        $request = DataExportRequest::reconstituteFromPersistence(
            id: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: ExportStatus::READY,
            filePath: '/path/file.json',
            downloadToken: 'token-abc',
            expiresAt: null,
            createdAt: new \DateTimeImmutable(),
            completedAt: new \DateTimeImmutable(),
            errorMessage: null,
        );

        self::assertFalse($request->isExpired());
    }

    // -----------------------------------------------------------------------
    // isDownloadable — various states
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsDownloadableWhenReadyAndNotExpired(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();
        $request->markReady('/path/file.json', 'token', new \DateTimeImmutable('+24 hours'));

        // isDownloadable uses current time by default (should not be expired)
        self::assertTrue($request->isDownloadable());
    }

    #[Test]
    public function itIsNotDownloadableWhenProcessing(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        self::assertFalse($request->isDownloadable());
    }

    #[Test]
    public function itIsNotDownloadableWhenFailed(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markFailed('Some error');

        self::assertFalse($request->isDownloadable());
    }

    #[Test]
    public function itIsNotDownloadableWhenExpired(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);
        $request->markProcessing();

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $request->markReady('/path/file.json', 'token', $expiresAt);
        $request->markExpired();

        self::assertFalse($request->isDownloadable());
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence — with privacyRequestId
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteWithPrivacyRequestId(): void
    {
        $id = DataExportRequestId::generate();
        $privacyId = 'priv-999';

        $request = DataExportRequest::reconstituteFromPersistence(
            id: $id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: ExportStatus::READY,
            filePath: '/exports/data.zip',
            downloadToken: 'secure-tok-456',
            expiresAt: new \DateTimeImmutable('+12 hours'),
            createdAt: new \DateTimeImmutable('-2 hours'),
            completedAt: new \DateTimeImmutable('-1 hour'),
            errorMessage: null,
            privacyRequestId: $privacyId,
        );

        self::assertTrue($id->equals($request->id()));
        self::assertSame($privacyId, $request->privacyRequestId());
        self::assertSame(ExportStatus::READY, $request->status());
        self::assertSame('/exports/data.zip', $request->filePath());
        self::assertSame('secure-tok-456', $request->downloadToken());
    }

    // -----------------------------------------------------------------------
    // Full workflow coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itCompletesFullExportWorkflow(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId, 'priv-workflow-1');

        self::assertSame(ExportStatus::PENDING, $request->status());

        $request->markProcessing();
        self::assertSame(ExportStatus::PROCESSING, $request->status());

        $expiresAt = new \DateTimeImmutable('+24 hours');
        $request->markReady('/path/export.zip', 'dl-token', $expiresAt);
        self::assertSame(ExportStatus::READY, $request->status());
        self::assertTrue($request->isDownloadable());

        // Simulate expiry
        $afterExpiry = $expiresAt->modify('+1 second');
        self::assertTrue($request->isExpired($afterExpiry));
        self::assertFalse($request->isDownloadable($afterExpiry));

        $request->markExpired();
        self::assertSame(ExportStatus::EXPIRED, $request->status());
        self::assertFalse($request->isDownloadable());
    }

    #[Test]
    public function itCompletesFailureWorkflow(): void
    {
        $request = DataExportRequest::create($this->customerId, $this->tenantId);

        $request->markProcessing();
        $request->markFailed('Connection timeout');

        self::assertSame(ExportStatus::FAILED, $request->status());
        self::assertSame('Connection timeout', $request->errorMessage());
        self::assertNotNull($request->completedAt());
    }

    #[Test]
    public function itMarkReadyClearsErrorMessage(): void
    {
        // Simulate an existing request with an error being reconstituted
        $request = DataExportRequest::reconstituteFromPersistence(
            id: DataExportRequestId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            status: ExportStatus::PROCESSING,
            filePath: null,
            downloadToken: null,
            expiresAt: null,
            createdAt: new \DateTimeImmutable(),
            completedAt: null,
            errorMessage: 'Previous error',
        );

        $request->markReady('/path/file.json', 'new-token', new \DateTimeImmutable('+24 hours'));

        // markReady sets errorMessage to null
        self::assertNull($request->errorMessage());
    }
}
