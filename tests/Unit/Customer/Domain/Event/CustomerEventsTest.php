<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Event;

use App\Customer\Domain\Event\CustomerActivated;
use App\Customer\Domain\Event\CustomerCreated;
use App\Customer\Domain\Event\CustomerDeactivated;
use App\Customer\Domain\Event\CustomerPreferencesUpdated;
use App\Customer\Domain\Event\CustomerSegmentChanged;
use App\Customer\Domain\Event\CustomerUpdated;
use App\Customer\Domain\Event\NotificationPreferencesUpdated;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerCreated::class)]
#[CoversClass(CustomerUpdated::class)]
#[CoversClass(CustomerActivated::class)]
#[CoversClass(CustomerDeactivated::class)]
#[CoversClass(CustomerSegmentChanged::class)]
#[CoversClass(CustomerPreferencesUpdated::class)]
#[CoversClass(NotificationPreferencesUpdated::class)]
final class CustomerEventsTest extends TestCase
{
    // ---------------------------------------------------------------
    // CustomerCreated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnCustomerCreated(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $email = Email::fromString('alice@example.com');

        $event = new CustomerCreated(
            customerId: $customerId,
            tenantId: $tenantId,
            email: $email,
            firstName: 'Alice',
            lastName: 'Smith',
        );

        self::assertTrue($customerId->equals($event->customerId()));
        self::assertTrue($tenantId->equals($event->tenantId()));
        self::assertSame('alice@example.com', $event->email()->toString());
        self::assertSame('Alice', $event->firstName());
        self::assertSame('Smith', $event->lastName());
    }

    // ---------------------------------------------------------------
    // CustomerUpdated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnCustomerUpdated(): void
    {
        $customerId = CustomerId::generate();

        $event = new CustomerUpdated(
            customerId: $customerId,
            firstName: 'Bob',
            lastName: 'Jones',
            phoneNumber: '+1234567890',
        );

        self::assertTrue($customerId->equals($event->customerId()));
        self::assertSame('Bob', $event->firstName());
        self::assertSame('Jones', $event->lastName());
        self::assertSame('+1234567890', $event->phoneNumber());
    }

    #[Test]
    public function itAllowsNullPhoneOnCustomerUpdated(): void
    {
        $event = new CustomerUpdated(
            customerId: CustomerId::generate(),
            firstName: 'Carol',
            lastName: 'White',
            phoneNumber: null,
        );

        self::assertNull($event->phoneNumber());
    }

    // ---------------------------------------------------------------
    // CustomerActivated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesCustomerIdOnCustomerActivated(): void
    {
        $customerId = CustomerId::generate();
        $event = new CustomerActivated(customerId: $customerId);

        self::assertTrue($customerId->equals($event->customerId()));
    }

    // ---------------------------------------------------------------
    // CustomerDeactivated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesCustomerIdOnCustomerDeactivated(): void
    {
        $customerId = CustomerId::generate();
        $event = new CustomerDeactivated(customerId: $customerId);

        self::assertTrue($customerId->equals($event->customerId()));
    }

    // ---------------------------------------------------------------
    // CustomerSegmentChanged
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnCustomerSegmentChanged(): void
    {
        $customerId = CustomerId::generate();
        $old = CustomerSegment::regular();
        $new = CustomerSegment::vip();

        $event = new CustomerSegmentChanged(
            customerId: $customerId,
            oldSegment: $old,
            newSegment: $new,
        );

        self::assertTrue($customerId->equals($event->customerId()));
        self::assertSame('regular', $event->oldSegment()->value());
        self::assertSame('vip', $event->newSegment()->value());
    }

    // ---------------------------------------------------------------
    // CustomerPreferencesUpdated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllFieldsOnCustomerPreferencesUpdated(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $occurredAt = new \DateTimeImmutable('2025-01-15 10:00:00');
        $oldPrefs = ['language' => 'en', 'currency' => 'USD'];
        $newPrefs = ['language' => 'fr', 'currency' => 'EUR'];

        $event = new CustomerPreferencesUpdated(
            customerId: $customerId,
            tenantId: $tenantId,
            oldPreferences: $oldPrefs,
            newPreferences: $newPrefs,
            occurredAt: $occurredAt,
        );

        self::assertTrue($customerId->equals($event->customerId()));
        self::assertTrue($tenantId->equals($event->tenantId()));
        self::assertSame($oldPrefs, $event->oldPreferences());
        self::assertSame($newPrefs, $event->newPreferences());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    // ---------------------------------------------------------------
    // NotificationPreferencesUpdated
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllPublicPropertiesOnNotificationPreferencesUpdated(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $occurredAt = new \DateTimeImmutable();
        $old = ['orderUpdates' => true, 'sms' => false];
        $new = ['orderUpdates' => true, 'sms' => true];

        $event = new NotificationPreferencesUpdated(
            customerId: $customerId,
            tenantId: $tenantId,
            oldPreferences: $old,
            newPreferences: $new,
            occurredAt: $occurredAt,
        );

        self::assertTrue($customerId->equals($event->customerId));
        self::assertTrue($tenantId->equals($event->tenantId));
        self::assertSame($old, $event->oldPreferences);
        self::assertSame($new, $event->newPreferences);
        self::assertSame($occurredAt, $event->occurredAt);
    }
}
