<?php

declare(strict_types=1);

namespace App\Customer\Application\DTO;

/**
 * Customer Consents DTO.
 *
 * Data transfer object for customer GDPR consents.
 */
final readonly class CustomerConsentsDTO
{
    public function __construct(
        public string $customerId,
        public bool $marketingEmail,
        public bool $marketingSms,
        public bool $thirdPartySharing,
        public bool $analyticsTracking,
        public \DateTimeImmutable $lastUpdatedAt,
    ) {
    }
}
