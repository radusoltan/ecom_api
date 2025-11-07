<?php

declare(strict_types=1);

namespace App\Privacy\Application\DTO;

use App\Privacy\Domain\Model\Consent;

final readonly class ConsentDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $customerId,
        public string $purpose,
        public bool $isGranted,
        public string $ipAddress,
        public string $userAgent,
        public string $consentText,
        public string $consentVersion,
        public ?\DateTimeImmutable $grantedAt,
        public ?\DateTimeImmutable $withdrawnAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt
    ) {
    }

    public static function fromDomainModel(Consent $consent): self
    {
        return new self(
            id: $consent->id()->toString(),
            tenantId: $consent->tenantId()->toString(),
            customerId: $consent->customerId()->toString(),
            purpose: $consent->purpose()->value(),
            isGranted: $consent->isGranted(),
            ipAddress: $consent->ipAddress(),
            userAgent: $consent->userAgent(),
            consentText: $consent->consentText(),
            consentVersion: $consent->consentVersion(),
            grantedAt: $consent->grantedAt(),
            withdrawnAt: $consent->withdrawnAt(),
            createdAt: $consent->createdAt(),
            updatedAt: $consent->updatedAt()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'customerId' => $this->customerId,
            'purpose' => $this->purpose,
            'isGranted' => $this->isGranted,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'consentText' => $this->consentText,
            'consentVersion' => $this->consentVersion,
            'grantedAt' => $this->grantedAt?->format('c'),
            'withdrawnAt' => $this->withdrawnAt?->format('c'),
            'createdAt' => $this->createdAt->format('c'),
            'updatedAt' => $this->updatedAt->format('c'),
        ];
    }
}
