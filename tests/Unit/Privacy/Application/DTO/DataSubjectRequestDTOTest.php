<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\DTO;

use App\Privacy\Application\DTO\DataSubjectRequestDTO;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('privacy')]
final class DataSubjectRequestDTOTest extends TestCase
{
    private DataSubjectRequestDTO $dto;

    protected function setUp(): void
    {
        $now = new \DateTimeImmutable('2026-01-15 10:00:00');
        $this->dto = new DataSubjectRequestDTO(
            id: '00000000-0000-4000-8000-000000000010',
            tenantId: '00000000-0000-4000-8000-000000000001',
            customerId: '00000000-0000-4000-8000-000000000002',
            requestType: 'data_export',
            status: 'pending',
            reason: 'GDPR request',
            reviewNotes: null,
            rejectionReason: null,
            exportData: ['name' => 'John'],
            submittedAt: $now,
            completedAt: null,
            deadline: $now->modify('+30 days'),
            isExtended: false,
            isOverdue: false,
            daysUntilDeadline: 30,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function testToArrayContainsAllFields(): void
    {
        $array = $this->dto->toArray();

        self::assertSame('00000000-0000-4000-8000-000000000010', $array['id']);
        self::assertSame('00000000-0000-4000-8000-000000000001', $array['tenantId']);
        self::assertSame('00000000-0000-4000-8000-000000000002', $array['customerId']);
        self::assertSame('data_export', $array['requestType']);
        self::assertSame('pending', $array['status']);
        self::assertSame('GDPR request', $array['reason']);
        self::assertNull($array['reviewNotes']);
        self::assertNull($array['rejectionReason']);
        self::assertSame(['name' => 'John'], $array['exportData']);
        self::assertNull($array['completedAt']);
        self::assertFalse($array['isExtended']);
        self::assertFalse($array['isOverdue']);
        self::assertSame(30, $array['daysUntilDeadline']);
    }

    public function testToArrayFormatsDatesAsIso(): void
    {
        $array = $this->dto->toArray();

        self::assertStringContainsString('2026-01-15', $array['submittedAt']);
        self::assertStringContainsString('2026-02-14', $array['deadline']);
        self::assertStringContainsString('2026-01-15', $array['createdAt']);
    }

    public function testToArrayWithCompletedDate(): void
    {
        $completed = new \DateTimeImmutable('2026-01-20 14:00:00');
        $dto = new DataSubjectRequestDTO(
            id: '00000000-0000-4000-8000-000000000010',
            tenantId: '00000000-0000-4000-8000-000000000001',
            customerId: '00000000-0000-4000-8000-000000000002',
            requestType: 'data_export',
            status: 'completed',
            reason: null,
            reviewNotes: 'Approved',
            rejectionReason: null,
            exportData: null,
            submittedAt: new \DateTimeImmutable('2026-01-15'),
            completedAt: $completed,
            deadline: new \DateTimeImmutable('2026-02-14'),
            isExtended: false,
            isOverdue: false,
            daysUntilDeadline: 25,
            createdAt: new \DateTimeImmutable('2026-01-15'),
            updatedAt: $completed,
        );

        $array = $dto->toArray();
        self::assertNotNull($array['completedAt']);
        self::assertStringContainsString('2026-01-20', $array['completedAt']);
    }

    public function testFromDomainModel(): void
    {
        $request = DataSubjectRequest::submit(
            \App\Privacy\Domain\ValueObject\DataSubjectRequestId::generate(),
            TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            \App\Customer\Domain\ValueObject\CustomerId::fromString('00000000-0000-4000-8000-000000000002'),
            \App\Privacy\Domain\ValueObject\RequestType::access(),
            'GDPR request',
        );

        $dto = DataSubjectRequestDTO::fromDomainModel($request);

        self::assertNotEmpty($dto->id);
        self::assertSame('access', $dto->requestType);
        self::assertSame('pending', $dto->status);
        self::assertSame('GDPR request', $dto->reason);
    }
}
