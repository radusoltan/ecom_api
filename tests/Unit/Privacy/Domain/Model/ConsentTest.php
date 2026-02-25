<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\Model;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Consent::class)]
final class ConsentTest extends TestCase
{
    private ConsentId $consentId;
    private TenantId $tenantId;
    private CustomerId $customerId;
    private string $validConsentText;

    protected function setUp(): void
    {
        $this->consentId = ConsentId::generate();
        $this->tenantId = TenantId::generate();
        $this->customerId = CustomerId::generate();
        $this->validConsentText = 'I consent to the processing of my personal data for the purpose of marketing communications as described in the privacy policy.';
    }

    #[Test]
    public function grantCreatesConsentWithCorrectState(): void
    {
        $consent = $this->createGrantedConsent();

        self::assertTrue($consent->id()->equals($this->consentId));
        self::assertTrue($consent->tenantId()->equals($this->tenantId));
        self::assertTrue($consent->customerId()->equals($this->customerId));
        self::assertTrue($consent->purpose()->equals(ConsentPurpose::marketing()));
        self::assertTrue($consent->isGranted());
        self::assertSame('192.168.1.1', $consent->ipAddress());
        self::assertSame('Mozilla/5.0', $consent->userAgent());
        self::assertSame($this->validConsentText, $consent->consentText());
        self::assertSame('v1.0.0', $consent->consentVersion());
        self::assertNotNull($consent->grantedAt());
        self::assertNull($consent->withdrawnAt());
    }

    #[Test]
    public function grantRecordsConsentGrantedEvent(): void
    {
        $consent = $this->createGrantedConsent();

        $events = $consent->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsentGranted::class, $events[0]);
        self::assertTrue($events[0]->consentId->equals($this->consentId));
        self::assertTrue($events[0]->customerId->equals($this->customerId));
        self::assertTrue($events[0]->purpose->equals(ConsentPurpose::marketing()));
        self::assertTrue($events[0]->tenantId->equals($this->tenantId));
    }

    #[Test]
    public function withdrawSetsConsentAsWithdrawn(): void
    {
        $consent = $this->createGrantedConsent();
        $consent->popEvents();

        $consent->withdraw();

        self::assertFalse($consent->isGranted());
        self::assertNotNull($consent->withdrawnAt());
    }

    #[Test]
    public function withdrawRecordsConsentWithdrawnEvent(): void
    {
        $consent = $this->createGrantedConsent();
        $consent->popEvents();

        $consent->withdraw();

        $events = $consent->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsentWithdrawn::class, $events[0]);
        self::assertTrue($events[0]->consentId->equals($this->consentId));
        self::assertTrue($events[0]->customerId->equals($this->customerId));
        self::assertTrue($events[0]->purpose->equals(ConsentPurpose::marketing()));
        self::assertTrue($events[0]->tenantId->equals($this->tenantId));
    }

    #[Test]
    public function withdrawAlreadyWithdrawnConsentThrowsException(): void
    {
        $consent = $this->createGrantedConsent();
        $consent->withdraw();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already withdrawn');

        $consent->withdraw();
    }

    #[Test]
    public function grantWithInvalidIpAddressThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IP address');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::marketing(),
            'not-an-ip',
            'Mozilla/5.0',
            $this->validConsentText,
            'v1.0.0'
        );
    }

    #[Test]
    public function grantWithEmptyUserAgentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User agent cannot be empty');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::analytics(),
            '192.168.1.1',
            '',
            $this->validConsentText,
            'v1.0.0'
        );
    }

    #[Test]
    public function grantWithTooLongUserAgentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User agent too long');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::analytics(),
            '192.168.1.1',
            str_repeat('x', 501),
            $this->validConsentText,
            'v1.0.0'
        );
    }

    #[Test]
    public function grantWithEmptyConsentTextThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Consent text cannot be empty');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::profiling(),
            '10.0.0.1',
            'Mozilla/5.0',
            '',
            'v1.0.0'
        );
    }

    #[Test]
    public function grantWithTooShortConsentTextThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Consent text too short');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::profiling(),
            '10.0.0.1',
            'Mozilla/5.0',
            'Too short text',
            'v1.0.0'
        );
    }

    #[Test]
    public function grantWithEmptyConsentVersionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Consent version cannot be empty');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::marketing(),
            '192.168.1.1',
            'Mozilla/5.0',
            $this->validConsentText,
            ''
        );
    }

    #[Test]
    public function grantWithInvalidConsentVersionFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid consent version format');

        Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::marketing(),
            '192.168.1.1',
            'Mozilla/5.0',
            $this->validConsentText,
            '1.0.0'
        );
    }

    #[Test]
    public function grantAcceptsValidSemanticVersion(): void
    {
        $consent = Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::thirdPartySharing(),
            '::1',
            'Chrome/120',
            $this->validConsentText,
            'v2.10.3'
        );

        self::assertSame('v2.10.3', $consent->consentVersion());
    }

    #[Test]
    public function grantAcceptsIpv6Address(): void
    {
        $consent = Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::analytics(),
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'Mozilla/5.0',
            $this->validConsentText,
            'v1.0.0'
        );

        self::assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $consent->ipAddress());
    }

    #[Test]
    public function reconstituteFromPersistencePreservesAllFields(): void
    {
        $grantedAt = new \DateTimeImmutable('2025-01-15');
        $withdrawnAt = new \DateTimeImmutable('2025-02-15');
        $createdAt = new \DateTimeImmutable('2025-01-15');
        $updatedAt = new \DateTimeImmutable('2025-02-15');

        $consent = Consent::reconstituteFromPersistence(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::marketing(),
            false,
            '10.0.0.1',
            'Safari/17',
            $this->validConsentText,
            'v1.0.0',
            $grantedAt,
            $withdrawnAt,
            $createdAt,
            $updatedAt
        );

        self::assertFalse($consent->isGranted());
        self::assertSame($grantedAt, $consent->grantedAt());
        self::assertSame($withdrawnAt, $consent->withdrawnAt());
        self::assertSame($createdAt, $consent->createdAt());
        self::assertSame($updatedAt, $consent->updatedAt());
    }

    #[Test]
    public function reconstituteDoesNotRecordEvents(): void
    {
        $consent = Consent::reconstituteFromPersistence(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::marketing(),
            true,
            '10.0.0.1',
            'Safari/17',
            $this->validConsentText,
            'v1.0.0',
            new \DateTimeImmutable(),
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        self::assertEmpty($consent->popEvents());
    }

    private function createGrantedConsent(): Consent
    {
        return Consent::grant(
            $this->consentId,
            $this->tenantId,
            $this->customerId,
            ConsentPurpose::marketing(),
            '192.168.1.1',
            'Mozilla/5.0',
            $this->validConsentText,
            'v1.0.0'
        );
    }
}
