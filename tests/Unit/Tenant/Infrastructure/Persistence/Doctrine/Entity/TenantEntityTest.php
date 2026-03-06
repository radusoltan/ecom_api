<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\TenantEntity;
use PHPUnit\Framework\TestCase;

final class TenantEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $tenant = Tenant::create(
            TenantName::fromString('Acme Corp'),
            Email::fromString('owner@acme.com'),
        );

        $entity = TenantEntity::fromDomainModel($tenant);

        self::assertSame($tenant->id()->toString(), $entity->getId());
        self::assertSame('Acme Corp', $entity->getName());
        self::assertSame('owner@acme.com', $entity->getOwnerEmail());
        self::assertSame('active', $entity->getStatus());
        self::assertSame('en', $entity->getDefaultLocale());
        self::assertContains('en', $entity->getEnabledLocales());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertTrue($restored->id()->equals($tenant->id()));
        self::assertSame('Acme Corp', $restored->name()->value());
    }

    public function testUpdateFromDomain(): void
    {
        $tenant = Tenant::create(
            TenantName::fromString('Test Shop'),
            Email::fromString('test@shop.com'),
        );

        $entity = TenantEntity::fromDomain($tenant);
        $tenant->suspend();
        $entity->updateFromDomain($tenant);

        self::assertSame('suspended', $entity->getStatus());
    }

    public function testSetters(): void
    {
        $entity = new TenantEntity(
            'id-001', 'Name', 'email@test.com', 'active', new \DateTimeImmutable(),
        );

        $entity->setName('Updated Name');
        $entity->setOwnerEmail('new@test.com');
        $entity->setStatus('suspended');
        $entity->setDefaultLocale('de');
        $entity->setEnabledLocales(['en', 'de']);
        $entity->setTranslationQuota(50000);
        $entity->setTranslationUsage(100);
        $entity->setDescription('A test tenant');
        $entity->setTranslatableLocale('de');

        self::assertSame('Updated Name', $entity->getName());
        self::assertSame('new@test.com', $entity->getOwnerEmail());
        self::assertSame('suspended', $entity->getStatus());
        self::assertSame('de', $entity->getDefaultLocale());
        self::assertSame(['en', 'de'], $entity->getEnabledLocales());
        self::assertSame(50000, $entity->getTranslationQuota());
        self::assertSame(100, $entity->getTranslationUsage());
        self::assertSame('A test tenant', $entity->getDescription());
    }
}
