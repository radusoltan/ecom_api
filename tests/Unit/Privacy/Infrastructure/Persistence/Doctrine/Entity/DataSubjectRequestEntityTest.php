<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DataSubjectRequestEntityTest extends TestCase
{
    private DataSubjectRequestId $requestId;
    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->requestId = DataSubjectRequestId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->customerId = CustomerId::generate();
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $submittedAt = new \DateTimeImmutable('2026-01-01 10:00:00');
        $deadline = new \DateTimeImmutable('2026-01-31 10:00:00');
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            RequestStatus::pending(),
            null,
            null,
            null,
            null,
            $submittedAt,
            null,
            $deadline,
            false,
            $createdAt,
            $updatedAt
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertSame($this->requestId->toString(), $entity->getId());
        self::assertSame($this->tenantId->toString(), $entity->getTenantId());
        self::assertSame($this->customerId->toString(), $entity->getCustomerId());
        self::assertSame('access', $entity->getRequestType());
        self::assertSame('pending', $entity->getStatus());
        self::assertNull($entity->getReason());
        self::assertNull($entity->getReviewNotes());
        self::assertNull($entity->getRejectionReason());
        self::assertNull($entity->getExportData());
        self::assertNull($entity->getCompletedAt());
        self::assertFalse($entity->isExtended());

        $restored = $entity->toDomainModel();
        self::assertSame($this->requestId->toString(), $restored->id()->toString());
        self::assertSame($this->tenantId->toString(), $restored->tenantId()->toString());
        self::assertSame($this->customerId->toString(), $restored->customerId()->toString());
        self::assertSame('access', $restored->requestType()->value());
        self::assertSame('pending', $restored->status()->value());
        self::assertFalse($restored->isExtended());
        self::assertNull($restored->reason());
        self::assertNull($restored->reviewNotes());
        self::assertNull($restored->rejectionReason());
        self::assertNull($restored->exportData());
        self::assertNull($restored->completedAt());
    }

    public function testFromDomainModelWithReason(): void
    {
        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::erasure(),
            RequestStatus::pending(),
            'I want my data deleted from this platform for privacy reasons.',
            null,
            null,
            null,
            new \DateTimeImmutable(),
            null,
            new \DateTimeImmutable('+30 days'),
            false,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertSame('I want my data deleted from this platform for privacy reasons.', $entity->getReason());
        self::assertSame('erasure', $entity->getRequestType());
    }

    public function testFromDomainModelWithCompletedStatus(): void
    {
        $completedAt = new \DateTimeImmutable('2026-01-20 14:00:00');

        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::rectification(),
            RequestStatus::completed(),
            null,
            'Reviewed and approved.',
            null,
            null,
            new \DateTimeImmutable('2026-01-01'),
            $completedAt,
            new \DateTimeImmutable('2026-01-31'),
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-20')
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertSame('completed', $entity->getStatus());
        self::assertSame('Reviewed and approved.', $entity->getReviewNotes());
        self::assertSame($completedAt->getTimestamp(), $entity->getCompletedAt()->getTimestamp());

        $restored = $entity->toDomainModel();
        self::assertSame('completed', $restored->status()->value());
        self::assertNotNull($restored->completedAt());
    }

    public function testFromDomainModelWithRejection(): void
    {
        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::restriction(),
            RequestStatus::rejected(),
            null,
            null,
            'Request rejected due to insufficient identity verification.',
            null,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-10'),
            new \DateTimeImmutable('2026-01-31'),
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-10')
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertSame('rejected', $entity->getStatus());
        self::assertSame('Request rejected due to insufficient identity verification.', $entity->getRejectionReason());
    }

    public function testFromDomainModelWithExportData(): void
    {
        $exportData = ['name' => 'John Doe', 'email' => 'john@example.com', 'orders' => [1, 2, 3]];

        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::portability(),
            RequestStatus::completed(),
            null,
            null,
            null,
            $exportData,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-20'),
            new \DateTimeImmutable('2026-01-31'),
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-20')
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertSame($exportData, $entity->getExportData());

        $restored = $entity->toDomainModel();
        self::assertSame($exportData, $restored->exportData());
    }

    public function testFromDomainModelWithExtendedDeadline(): void
    {
        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::objection(),
            RequestStatus::underReview(),
            null,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-01-01'),
            null,
            new \DateTimeImmutable('2026-04-01'),
            true,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-15')
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertTrue($entity->isExtended());
        self::assertSame('under_review', $entity->getStatus());

        $restored = $entity->toDomainModel();
        self::assertTrue($restored->isExtended());
    }

    public function testUpdateFromDomainModel(): void
    {
        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            RequestStatus::pending(),
            null,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-01-01'),
            null,
            new \DateTimeImmutable('2026-01-31'),
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-01')
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);
        self::assertSame('pending', $entity->getStatus());

        $updated = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            RequestStatus::underReview(),
            null,
            'Currently reviewing your request.',
            null,
            null,
            new \DateTimeImmutable('2026-01-01'),
            null,
            new \DateTimeImmutable('2026-01-31'),
            false,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-05')
        );

        $entity->updateFromDomainModel($updated);

        self::assertSame('under_review', $entity->getStatus());
        self::assertSame('Currently reviewing your request.', $entity->getReviewNotes());
    }

    public function testGettersReturnCorrectScalarTypes(): void
    {
        $submittedAt = new \DateTimeImmutable('2026-03-01 08:00:00');
        $deadline = new \DateTimeImmutable('2026-03-31 08:00:00');
        $createdAt = new \DateTimeImmutable('2026-03-01 08:00:00');
        $updatedAt = new \DateTimeImmutable('2026-03-01 08:00:00');

        $domain = DataSubjectRequest::reconstituteFromPersistence(
            $this->requestId,
            $this->tenantId,
            $this->customerId,
            RequestType::access(),
            RequestStatus::pending(),
            null,
            null,
            null,
            null,
            $submittedAt,
            null,
            $deadline,
            false,
            $createdAt,
            $updatedAt
        );

        $entity = DataSubjectRequestEntity::fromDomainModel($domain);

        self::assertIsString($entity->getId());
        self::assertIsString($entity->getTenantId());
        self::assertIsString($entity->getCustomerId());
        self::assertIsString($entity->getRequestType());
        self::assertIsString($entity->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getSubmittedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getDeadline());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }
}
