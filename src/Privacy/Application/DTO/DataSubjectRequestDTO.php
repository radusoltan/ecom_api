<?php

declare(strict_types=1);

namespace App\Privacy\Application\DTO;

use App\Privacy\Domain\Model\DataSubjectRequest;

final readonly class DataSubjectRequestDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $customerId,
        public string $requestType,
        public string $status,
        public ?string $reason,
        public ?string $reviewNotes,
        public ?string $rejectionReason,
        public ?array $exportData,
        public \DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $completedAt,
        public \DateTimeImmutable $deadline,
        public bool $isExtended,
        public bool $isOverdue,
        public int $daysUntilDeadline,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromDomainModel(DataSubjectRequest $request): self
    {
        return new self(
            id: $request->id()->toString(),
            tenantId: $request->tenantId()->toString(),
            customerId: $request->customerId()->toString(),
            requestType: $request->requestType()->value(),
            status: $request->status()->value(),
            reason: $request->reason(),
            reviewNotes: $request->reviewNotes(),
            rejectionReason: $request->rejectionReason(),
            exportData: $request->exportData(),
            submittedAt: $request->submittedAt(),
            completedAt: $request->completedAt(),
            deadline: $request->deadline(),
            isExtended: $request->isExtended(),
            isOverdue: $request->isOverdue(),
            daysUntilDeadline: $request->daysUntilDeadline(),
            createdAt: $request->createdAt(),
            updatedAt: $request->updatedAt()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'customerId' => $this->customerId,
            'requestType' => $this->requestType,
            'status' => $this->status,
            'reason' => $this->reason,
            'reviewNotes' => $this->reviewNotes,
            'rejectionReason' => $this->rejectionReason,
            'exportData' => $this->exportData,
            'submittedAt' => $this->submittedAt->format('c'),
            'completedAt' => $this->completedAt?->format('c'),
            'deadline' => $this->deadline->format('c'),
            'isExtended' => $this->isExtended,
            'isOverdue' => $this->isOverdue,
            'daysUntilDeadline' => $this->daysUntilDeadline,
            'createdAt' => $this->createdAt->format('c'),
            'updatedAt' => $this->updatedAt->format('c'),
        ];
    }
}
