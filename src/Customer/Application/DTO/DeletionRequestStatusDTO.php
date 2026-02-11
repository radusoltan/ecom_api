<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

use App\Customer\Domain\Model\DeletionRequest;

final readonly class DeletionRequestStatusDTO
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $status,
        public string $statusLabel,
        public ?string $reason,
        public ?string $holdReason,
        public string $scheduledFor,
        public ?string $confirmedAt,
        public ?string $completedAt,
        public string $createdAt,
        public bool $canBeCancelled,
        public bool $isOnHold,
        public bool $canBeExecuted
    ) {
    }

    public static function fromDomainModel(DeletionRequest $request): self
    {
        return new self(
            id: $request->id()->toString(),
            customerId: $request->customerId()->toString(),
            status: $request->status()->value,
            statusLabel: $request->status()->label(),
            reason: $request->reason(),
            holdReason: $request->holdReason(),
            scheduledFor: $request->scheduledFor()->format(\DateTimeInterface::ATOM),
            confirmedAt: $request->confirmedAt()?->format(\DateTimeInterface::ATOM),
            completedAt: $request->completedAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $request->createdAt()->format(\DateTimeInterface::ATOM),
            canBeCancelled: $request->canBeCancelled(),
            isOnHold: $request->isOnHold(),
            canBeExecuted: $request->canBeExecuted()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'status' => $this->status,
            'statusLabel' => $this->statusLabel,
            'reason' => $this->reason,
            'holdReason' => $this->holdReason,
            'scheduledFor' => $this->scheduledFor,
            'confirmedAt' => $this->confirmedAt,
            'completedAt' => $this->completedAt,
            'createdAt' => $this->createdAt,
            'canBeCancelled' => $this->canBeCancelled,
            'isOnHold' => $this->isOnHold,
            'canBeExecuted' => $this->canBeExecuted,
        ];
    }
}
