<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Consent History DTO.
 *
 * Data transfer object for consent history audit records.
 */
final readonly class ConsentHistoryDTO
{
    public function __construct(
        public string $id,
        public string $consentType,
        public string $consentTypeLabel,
        public bool $granted,
        public ?string $ipAddress,
        public ?string $userAgent,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
