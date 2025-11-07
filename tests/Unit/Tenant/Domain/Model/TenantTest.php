<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant\Domain\Model;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\TenantActivated;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\Event\TenantDeactivated;
use App\Tenant\Domain\Event\TenantReactivated;
use App\Tenant\Domain\Event\TenantSuspended;
use App\Tenant\Domain\Exception\TranslationQuotaExceededException;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    // =============================================
    // Test Tenant Creation via create()
    // =============================================

    public function testItCreatesTenantWithActiveStatus(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('owner@example.com');

        // Act
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->assertTrue($tenant->status()->isActive());
    }

    public function testItRecordsTenantCreatedEventOnCreation(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('owner@example.com');

        // Act
        $tenant = Tenant::create($name, $email);
        $events = $tenant->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TenantCreated::class, $events[0]);
    }

    public function testItSetsAllPropertiesCorrectlyOnCreation(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('owner@example.com');
        $beforeCreation = new \DateTimeImmutable();

        // Act
        $tenant = Tenant::create($name, $email);
        $afterCreation = new \DateTimeImmutable();

        // Assert
        $this->assertNotNull($tenant->id());
        $this->assertInstanceOf(TenantId::class, $tenant->id());
        $this->assertTrue($tenant->name()->equals($name));
        $this->assertTrue($tenant->ownerEmail()->equals($email));
        $this->assertTrue($tenant->status()->isActive());
        $this->assertGreaterThanOrEqual($beforeCreation, $tenant->createdAt());
        $this->assertLessThanOrEqual($afterCreation, $tenant->createdAt());
    }

    public function testCreatedEventContainsCorrectData(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('owner@example.com');

        // Act
        $tenant = Tenant::create($name, $email);
        $events = $tenant->popEvents();
        $event = $events[0];

        // Assert
        $this->assertInstanceOf(TenantCreated::class, $event);
        $this->assertTrue($event->tenantId->equals($tenant->id()));
        $this->assertTrue($event->name->equals($name));
        $this->assertTrue($event->ownerEmail->equals($email));
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    // =============================================
    // Test Tenant Reconstitution via fromPersistence()
    // =============================================

    public function testItReconstitutesTenantFromPersistence(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Persisted Tenant');
        $email = Email::fromString('persisted@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertTrue($tenant->id()->equals($id));
        $this->assertTrue($tenant->name()->equals($name));
        $this->assertTrue($tenant->ownerEmail()->equals($email));
        $this->assertTrue($tenant->status()->equals($status));
        $this->assertSame($createdAt, $tenant->createdAt());
    }

    public function testItDoesNotRecordEventsOnReconstitution(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Persisted Tenant');
        $email = Email::fromString('persisted@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertFalse($tenant->hasEvents());
        $this->assertEmpty($tenant->popEvents());
    }

    public function testItReconstitutesInactiveTenantCorrectly(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertTrue($tenant->status()->isInactive());
        $this->assertFalse($tenant->hasEvents());
    }

    // =============================================
    // Test Activate Method
    // =============================================

    public function testItActivatesInactiveTenant(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Act
        $tenant->activate();

        // Assert
        $this->assertTrue($tenant->status()->isActive());
    }

    public function testItRecordsTenantActivatedEvent(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Act
        $tenant->activate();
        $events = $tenant->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TenantActivated::class, $events[0]);
    }

    public function testActivatedEventContainsCorrectTenantId(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Act
        $tenant->activate();
        $events = $tenant->popEvents();
        $event = $events[0];

        // Assert
        $this->assertInstanceOf(TenantActivated::class, $event);
        $this->assertTrue($event->tenantId->equals($id));
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    public function testItThrowsExceptionWhenActivatingActiveTenant(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear the creation event

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant is already active');

        // Act
        $tenant->activate();
    }

    // =============================================
    // Test Deactivate Method
    // =============================================

    public function testItDeactivatesActiveTenant(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear the creation event

        // Act
        $tenant->deactivate();

        // Assert
        $this->assertTrue($tenant->status()->isInactive());
    }

    public function testItRecordsTenantDeactivatedEvent(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear the creation event

        // Act
        $tenant->deactivate();
        $events = $tenant->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TenantDeactivated::class, $events[0]);
    }

    public function testDeactivatedEventContainsCorrectTenantId(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $id = $tenant->id();
        $tenant->popEvents(); // Clear the creation event

        // Act
        $tenant->deactivate();
        $events = $tenant->popEvents();
        $event = $events[0];

        // Assert
        $this->assertInstanceOf(TenantDeactivated::class, $event);
        $this->assertTrue($event->tenantId->equals($id));
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn());
    }

    public function testItThrowsExceptionWhenDeactivatingInactiveTenant(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant is not active');

        // Act
        $tenant->deactivate();
    }

    // =============================================
    // Test Event Management
    // =============================================

    public function testPopEventsReturnsAllRecordedEventsAndClearsThem(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Act
        $firstPop = $tenant->popEvents();
        $secondPop = $tenant->popEvents();

        // Assert
        $this->assertCount(1, $firstPop);
        $this->assertInstanceOf(TenantCreated::class, $firstPop[0]);
        $this->assertEmpty($secondPop);
    }

    public function testMultipleEventsAreRecordedCorrectly(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear creation event

        // Act
        $tenant->deactivate();
        $tenant->activate();
        $events = $tenant->popEvents();

        // Assert
        $this->assertCount(2, $events);
        $this->assertInstanceOf(TenantDeactivated::class, $events[0]);
        $this->assertInstanceOf(TenantActivated::class, $events[1]);
    }

    public function testHasEventsReturnsTrueWhenEventsExist(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');

        // Act
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->assertTrue($tenant->hasEvents());
    }

    public function testHasEventsReturnsFalseWhenNoEventsExist(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertFalse($tenant->hasEvents());
    }

    public function testHasEventsReturnsFalseAfterPoppingEvents(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Act
        $tenant->popEvents();

        // Assert
        $this->assertFalse($tenant->hasEvents());
    }

    // =============================================
    // Test Getters
    // =============================================

    public function testIdGetterReturnsTenantId(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertInstanceOf(TenantId::class, $tenant->id());
        $this->assertTrue($tenant->id()->equals($id));
    }

    public function testNameGetterReturnsTenantName(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertInstanceOf(TenantName::class, $tenant->name());
        $this->assertTrue($tenant->name()->equals($name));
    }

    public function testOwnerEmailGetterReturnsEmail(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertInstanceOf(Email::class, $tenant->ownerEmail());
        $this->assertTrue($tenant->ownerEmail()->equals($email));
    }

    public function testStatusGetterReturnsTenantStatus(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertInstanceOf(TenantStatus::class, $tenant->status());
        $this->assertTrue($tenant->status()->equals($status));
        $this->assertTrue($tenant->status()->isInactive());
    }

    public function testCreatedAtGetterReturnsCreationTimestamp(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable('2024-06-15 10:30:00');

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $tenant->createdAt());
        $this->assertSame($createdAt, $tenant->createdAt());
        $this->assertEquals('2024-06-15 10:30:00', $tenant->createdAt()->format('Y-m-d H:i:s'));
    }

    public function testGettersReturnSameInstanceOnMultipleCalls(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Act & Assert
        $this->assertSame($tenant->id(), $tenant->id());
        $this->assertSame($tenant->name(), $tenant->name());
        $this->assertSame($tenant->ownerEmail(), $tenant->ownerEmail());
        $this->assertSame($tenant->createdAt(), $tenant->createdAt());
    }

    // =============================================
    // Test Suspend Method
    // =============================================

    public function testItSuspendsActiveTenant(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear the creation event

        // Act
        $tenant->suspend();

        // Assert
        $this->assertTrue($tenant->status()->isSuspended());
        $this->assertFalse($tenant->status()->isActive());
    }

    public function testItRecordsTenantSuspendedEvent(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear the creation event

        // Act
        $tenant->suspend();
        $events = $tenant->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TenantSuspended::class, $events[0]);
        $this->assertTrue($events[0]->tenantId->equals($tenant->id()));
    }

    public function testItThrowsExceptionWhenSuspendingAlreadySuspendedTenant(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Suspended Tenant');
        $email = Email::fromString('suspended@example.com');
        $status = TenantStatus::suspended();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant is already suspended');

        // Act
        $tenant->suspend();
    }

    public function testItThrowsExceptionWhenSuspendingInactiveTenant(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot suspend an inactive tenant');

        // Act
        $tenant->suspend();
    }

    // =============================================
    // Test Reactivate Method
    // =============================================

    public function testItReactivatesSuspendedTenant(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Suspended Tenant');
        $email = Email::fromString('suspended@example.com');
        $status = TenantStatus::suspended();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Act
        $tenant->reactivate();

        // Assert
        $this->assertTrue($tenant->status()->isActive());
        $this->assertFalse($tenant->status()->isSuspended());
    }

    public function testItRecordsTenantReactivatedEvent(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Suspended Tenant');
        $email = Email::fromString('suspended@example.com');
        $status = TenantStatus::suspended();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Act
        $tenant->reactivate();
        $events = $tenant->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TenantReactivated::class, $events[0]);
        $this->assertTrue($events[0]->tenantId->equals($tenant->id()));
    }

    public function testItThrowsExceptionWhenReactivatingActiveTenant(): void
    {
        // Arrange
        $name = TenantName::fromString('Active Tenant');
        $email = Email::fromString('active@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents(); // Clear the creation event

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tenant is already active');

        // Act
        $tenant->reactivate();
    }

    public function testItThrowsExceptionWhenReactivatingInactiveTenant(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Inactive Tenant');
        $email = Email::fromString('inactive@example.com');
        $status = TenantStatus::inactive();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only suspended tenants can be reactivated');

        // Act
        $tenant->reactivate();
    }

    // =============================================
    // Test Locale Management
    // =============================================

    public function testItCreatesWithDefaultLocaleEn(): void
    {
        // Arrange & Act
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->assertTrue($tenant->defaultLocale()->equals(LanguageCode::en()));
    }

    public function testItCreatesWithEnLocaleEnabled(): void
    {
        // Arrange & Act
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->assertCount(1, $tenant->enabledLocales());
        $this->assertTrue($tenant->enabledLocales()[0]->equals(LanguageCode::en()));
    }

    public function testItSetsDefaultLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents();
        $tenant->enableLocale(LanguageCode::fr());

        // Act
        $tenant->setDefaultLocale(LanguageCode::fr());

        // Assert
        $this->assertTrue($tenant->defaultLocale()->equals(LanguageCode::fr()));
    }

    public function testItThrowsExceptionWhenSettingDefaultLocaleToDisabledLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot set default locale to a disabled locale');

        // Act
        $tenant->setDefaultLocale(LanguageCode::fr());
    }

    public function testItEnablesLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->popEvents();

        // Act
        $tenant->enableLocale(LanguageCode::fr());

        // Assert
        $this->assertCount(2, $tenant->enabledLocales());
        $hasEnglish = false;
        $hasFrench = false;
        foreach ($tenant->enabledLocales() as $locale) {
            if ($locale->equals(LanguageCode::en())) {
                $hasEnglish = true;
            }
            if ($locale->equals(LanguageCode::fr())) {
                $hasFrench = true;
            }
        }
        $this->assertTrue($hasEnglish);
        $this->assertTrue($hasFrench);
    }

    public function testItThrowsExceptionWhenEnablingAlreadyEnabledLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Locale is already enabled');

        // Act
        $tenant->enableLocale(LanguageCode::en());
    }

    public function testItDisablesLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->enableLocale(LanguageCode::fr());
        $tenant->enableLocale(LanguageCode::de());
        $this->assertCount(3, $tenant->enabledLocales());

        // Act
        $tenant->disableLocale(LanguageCode::fr());

        // Assert
        $this->assertCount(2, $tenant->enabledLocales());
        foreach ($tenant->enabledLocales() as $locale) {
            $this->assertFalse($locale->equals(LanguageCode::fr()));
        }
    }

    public function testItThrowsExceptionWhenDisablingNotEnabledLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Locale is not enabled');

        // Act
        $tenant->disableLocale(LanguageCode::fr());
    }

    public function testItThrowsExceptionWhenDisablingDefaultLocale(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->enableLocale(LanguageCode::fr());

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot disable the default locale');

        // Act
        $tenant->disableLocale(LanguageCode::en());
    }

    public function testItReconstitutesWithCustomLocales(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();
        $defaultLocale = LanguageCode::fr();
        $enabledLocales = [LanguageCode::en(), LanguageCode::fr(), LanguageCode::de()];

        // Act
        $tenant = Tenant::fromPersistence($id, $name, $email, $status, $createdAt, $defaultLocale, $enabledLocales);

        // Assert
        $this->assertTrue($tenant->defaultLocale()->equals(LanguageCode::fr()));
        $this->assertCount(3, $tenant->enabledLocales());
    }

    // =============================================
    // Test Translation Quota
    // =============================================

    public function testItCreatesWithDefaultTranslationQuota(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');

        // Act
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->assertEquals(10000, $tenant->translationQuota());
        $this->assertEquals(0, $tenant->translationUsage());
    }

    public function testItIncrementsTranslationUsage(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Act
        $tenant->incrementTranslationUsage();

        // Assert
        $this->assertEquals(1, $tenant->translationUsage());
    }

    public function testItIncrementsTranslationUsageByCustomCount(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Act
        $tenant->incrementTranslationUsage(5);

        // Assert
        $this->assertEquals(5, $tenant->translationUsage());
    }

    public function testItThrowsExceptionWhenTranslationQuotaExceeded(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence(
            $id,
            $name,
            $email,
            $status,
            $createdAt,
            null,
            null,
            100, // quota
            95   // current usage
        );

        // Assert
        $this->expectException(TranslationQuotaExceededException::class);
        $this->expectExceptionMessage('Translation quota exceeded');

        // Act
        $tenant->incrementTranslationUsage(10); // Would exceed quota
    }

    public function testItSetsTranslationQuota(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Act
        $tenant->setTranslationQuota(50000);

        // Assert
        $this->assertEquals(50000, $tenant->translationQuota());
    }

    public function testItThrowsExceptionWhenSettingNegativeQuota(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Translation quota cannot be negative');

        // Act
        $tenant->setTranslationQuota(-100);
    }

    public function testItThrowsExceptionWhenSettingQuotaAboveMaximum(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Translation quota cannot exceed 1,000,000');

        // Act
        $tenant->setTranslationQuota(2000000);
    }

    public function testItResetsTranslationUsage(): void
    {
        // Arrange
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $tenant = Tenant::create($name, $email);
        $tenant->incrementTranslationUsage(100);

        // Act
        $tenant->resetTranslationUsage();

        // Assert
        $this->assertEquals(0, $tenant->translationUsage());
    }

    public function testItChecksIfTranslationQuotaIsAvailable(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence(
            $id,
            $name,
            $email,
            $status,
            $createdAt,
            null,
            null,
            100, // quota
            95   // current usage
        );

        // Assert
        $this->assertTrue($tenant->hasTranslationQuotaAvailable(5));
        $this->assertFalse($tenant->hasTranslationQuotaAvailable(6));
    }

    public function testItReturnsRemainingTranslationQuota(): void
    {
        // Arrange
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Tenant');
        $email = Email::fromString('test@example.com');
        $status = TenantStatus::active();
        $createdAt = new \DateTimeImmutable();
        $tenant = Tenant::fromPersistence(
            $id,
            $name,
            $email,
            $status,
            $createdAt,
            null,
            null,
            10000, // quota
            3500   // current usage
        );

        // Assert
        $this->assertEquals(6500, $tenant->remainingTranslationQuota());
    }
}
