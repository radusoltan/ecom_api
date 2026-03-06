<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConsentEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $consent = Consent::grant(
            ConsentId::generate(),
            TenantId::generate(),
            CustomerId::generate(),
            ConsentPurpose::marketing(),
            '192.168.1.1',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'I agree to receive marketing emails and promotional content from this company.',
            'v1.0.0',
        );

        $entity = ConsentEntity::fromDomainModel($consent);

        self::assertSame($consent->id()->toString(), $entity->getId());
        self::assertSame('marketing', $entity->getPurpose());
        self::assertTrue($entity->isGranted());
        self::assertSame('192.168.1.1', $entity->getIpAddress());
        self::assertSame('I agree to receive marketing emails and promotional content from this company.', $entity->getConsentText());
        self::assertSame('v1.0.0', $entity->getConsentVersion());
        self::assertNotNull($entity->getGrantedAt());
        self::assertNull($entity->getWithdrawnAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($consent->id()));
        self::assertTrue($restored->isGranted());
    }

    public function testUpdateFromDomainModel(): void
    {
        $consent = Consent::grant(
            ConsentId::generate(),
            TenantId::generate(),
            CustomerId::generate(),
            ConsentPurpose::marketing(),
            '10.0.0.1',
            'Test/1.0 User Agent String',
            'I consent to the processing of my personal data for marketing purposes as described.',
            'v2.0.0',
        );

        $entity = ConsentEntity::fromDomainModel($consent);
        $consent->withdraw();
        $entity->updateFromDomainModel($consent);

        self::assertFalse($entity->isGranted());
        self::assertNotNull($entity->getWithdrawnAt());
    }
}
