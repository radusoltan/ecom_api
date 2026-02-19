<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Consent Aggregate Root.
 *
 * Business Rules (GDPR Compliance):
 * - Consent must be freely given, specific, informed, and unambiguous (Article 4)
 * - Consent can be withdrawn at any time (Article 7)
 * - Withdrawal must be as easy as giving consent (Article 7)
 * - Consent history must be auditable (Article 5)
 * - Granular consent per purpose (marketing, analytics, profiling, etc.)
 * - IP address and user agent must be recorded for proof
 * - Consent version tracking for policy changes
 */
final class Consent extends AggregateRoot
{
    private ConsentId $id;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private ConsentPurpose $purpose;
    private bool $isGranted;
    private string $ipAddress;
    private string $userAgent;
    private string $consentText;
    private string $consentVersion;
    private ?\DateTimeImmutable $grantedAt;
    private ?\DateTimeImmutable $withdrawnAt;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function grant(
        ConsentId $id,
        TenantId $tenantId,
        CustomerId $customerId,
        ConsentPurpose $purpose,
        string $ipAddress,
        string $userAgent,
        string $consentText,
        string $consentVersion,
    ): self {
        self::validateIpAddress($ipAddress);
        self::validateUserAgent($userAgent);
        self::validateConsentText($consentText);
        self::validateConsentVersion($consentVersion);

        $consent = new self();
        $consent->id = $id;
        $consent->tenantId = $tenantId;
        $consent->customerId = $customerId;
        $consent->purpose = $purpose;
        $consent->isGranted = true;
        $consent->ipAddress = $ipAddress;
        $consent->userAgent = $userAgent;
        $consent->consentText = $consentText;
        $consent->consentVersion = $consentVersion;
        $consent->grantedAt = new \DateTimeImmutable();
        $consent->withdrawnAt = null;
        $consent->createdAt = new \DateTimeImmutable();
        $consent->updatedAt = new \DateTimeImmutable();

        $consent->recordEvent(new ConsentGranted(
            $consent->id,
            $consent->customerId,
            $consent->purpose,
            $consent->tenantId,
            $consent->grantedAt
        ));

        return $consent;
    }

    public static function reconstituteFromPersistence(
        ConsentId $id,
        TenantId $tenantId,
        CustomerId $customerId,
        ConsentPurpose $purpose,
        bool $isGranted,
        string $ipAddress,
        string $userAgent,
        string $consentText,
        string $consentVersion,
        ?\DateTimeImmutable $grantedAt,
        ?\DateTimeImmutable $withdrawnAt,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $consent = new self();
        $consent->id = $id;
        $consent->tenantId = $tenantId;
        $consent->customerId = $customerId;
        $consent->purpose = $purpose;
        $consent->isGranted = $isGranted;
        $consent->ipAddress = $ipAddress;
        $consent->userAgent = $userAgent;
        $consent->consentText = $consentText;
        $consent->consentVersion = $consentVersion;
        $consent->grantedAt = $grantedAt;
        $consent->withdrawnAt = $withdrawnAt;
        $consent->createdAt = $createdAt;
        $consent->updatedAt = $updatedAt;

        return $consent;
    }

    public function withdraw(): void
    {
        if (!$this->isGranted) {
            throw new \InvalidArgumentException(sprintf('Consent for purpose "%s" is already withdrawn', $this->purpose->value()));
        }

        $this->isGranted = false;
        $this->withdrawnAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ConsentWithdrawn(
            $this->id,
            $this->customerId,
            $this->purpose,
            $this->tenantId,
            $this->withdrawnAt
        ));
    }

    private static function validateIpAddress(string $ipAddress): void
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('Invalid IP address: "%s"', $ipAddress));
        }
    }

    private static function validateUserAgent(string $userAgent): void
    {
        $trimmed = trim($userAgent);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('User agent cannot be empty');
        }

        if (strlen($trimmed) > 500) {
            throw new \InvalidArgumentException(sprintf('User agent too long. Maximum 500 characters. Got: %d', strlen($trimmed)));
        }
    }

    private static function validateConsentText(string $consentText): void
    {
        $trimmed = trim($consentText);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Consent text cannot be empty');
        }

        if (strlen($trimmed) < 50) {
            throw new \InvalidArgumentException('Consent text too short. Must be at least 50 characters for GDPR compliance');
        }
    }

    private static function validateConsentVersion(string $consentVersion): void
    {
        $trimmed = trim($consentVersion);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Consent version cannot be empty');
        }

        // Semantic versioning format: v1.0.0
        if (!preg_match('/^v\d+\.\d+\.\d+$/', $trimmed)) {
            throw new \InvalidArgumentException(sprintf('Invalid consent version format. Expected: v1.0.0. Got: "%s"', $trimmed));
        }
    }

    // Getters
    public function id(): ConsentId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function purpose(): ConsentPurpose
    {
        return $this->purpose;
    }

    public function isGranted(): bool
    {
        return $this->isGranted;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function consentText(): string
    {
        return $this->consentText;
    }

    public function consentVersion(): string
    {
        return $this->consentVersion;
    }

    public function grantedAt(): ?\DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function withdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
