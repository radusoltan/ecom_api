<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Event\CustomerActivated;
use App\Customer\Domain\Event\CustomerCreated;
use App\Customer\Domain\Event\CustomerDeactivated;
use App\Customer\Domain\Event\CustomerSegmentChanged;
use App\Customer\Domain\Event\CustomerUpdated;
use App\Customer\Domain\Event\LoyaltyPointsAwarded;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerConsent;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Domain\ValueObject\NotificationPreferences;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;
    private Email $email;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
        $this->email = Email::fromString('john.doe@example.com');
    }

    public function testRegisterCreatesActiveCustomerWithDefaultSegment(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe',
            '+12345678901'
        );

        self::assertEquals($this->customerId, $customer->id());
        self::assertEquals($this->tenantId, $customer->tenantId());
        self::assertEquals($this->email, $customer->email());
        self::assertEquals('John', $customer->firstName());
        self::assertEquals('Doe', $customer->lastName());
        self::assertEquals('+12345678901', $customer->phoneNumber());
        self::assertTrue($customer->segment()->isRegular());
        self::assertEquals(0, $customer->loyaltyPoints());
        self::assertTrue($customer->isActive());
        self::assertEquals('John Doe', $customer->fullName());

        // Check event recorded
        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerCreated::class, $events[0]);
    }

    public function testRegisterWithoutPhoneNumber(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'Jane',
            'Smith'
        );

        self::assertNull($customer->phoneNumber());
        self::assertEquals('Jane Smith', $customer->fullName());
    }

    public function testRegisterValidatesFirstNameMinLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must be between 2 and 50 characters');

        Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'J', // Too short
            'Doe'
        );
    }

    public function testRegisterValidatesFirstNameMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must be between 2 and 50 characters');

        Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            str_repeat('A', 51), // Too long
            'Doe'
        );
    }

    public function testRegisterValidatesLastNameMinLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must be between 2 and 50 characters');

        Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'D' // Too short
        );
    }

    public function testRegisterValidatesLastNameMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name must be between 2 and 50 characters');

        Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            str_repeat('D', 51) // Too long
        );
    }

    public function testUpdateProfileChangesNameAndPhone(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->popEvents(); // Clear initial events

        $customer->updateProfile('Jane', 'Smith', '+19876543210');

        self::assertEquals('Jane', $customer->firstName());
        self::assertEquals('Smith', $customer->lastName());
        self::assertEquals('+19876543210', $customer->phoneNumber());
        self::assertEquals('Jane Smith', $customer->fullName());

        // Check event recorded
        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerUpdated::class, $events[0]);
    }

    public function testUpdateProfileValidatesFirstName(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name must be between 2 and 50 characters');

        $customer->updateProfile('J', 'Smith', null);
    }

    public function testChangeSegmentFromRegularToVip(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->popEvents(); // Clear initial events

        $customer->changeSegment(CustomerSegment::vip());

        self::assertTrue($customer->segment()->isVip());

        // Check event recorded
        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerSegmentChanged::class, $events[0]);
    }

    public function testChangeSegmentFromVipToWholesale(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->changeSegment(CustomerSegment::vip());
        $customer->popEvents(); // Clear events

        $customer->changeSegment(CustomerSegment::wholesale());

        self::assertTrue($customer->segment()->isWholesale());

        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerSegmentChanged::class, $events[0]);
    }

    public function testChangeSegmentFailsWhenAlreadyInSegment(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer is already in segment: regular');

        $customer->changeSegment(CustomerSegment::regular());
    }

    public function testAwardLoyaltyPointsIncreasesBalance(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->popEvents(); // Clear initial events

        $customer->awardLoyaltyPoints(100, 'Purchase reward');

        self::assertEquals(100, $customer->loyaltyPoints());

        // Check event recorded
        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LoyaltyPointsAwarded::class, $events[0]);

        // Award more points
        $customer->awardLoyaltyPoints(50, 'Referral bonus');
        self::assertEquals(150, $customer->loyaltyPoints());
    }

    public function testAwardLoyaltyPointsValidatesPositiveAmount(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty points to award must be greater than 0');

        $customer->awardLoyaltyPoints(0, 'Test');
    }

    public function testRedeemLoyaltyPointsDecreasesBalance(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->awardLoyaltyPoints(100, 'Test');
        $customer->redeemLoyaltyPoints(30);

        self::assertEquals(70, $customer->loyaltyPoints());
    }

    public function testRedeemLoyaltyPointsFailsWhenInsufficientBalance(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->awardLoyaltyPoints(50, 'Test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient loyalty points. Available: 50, Requested: 100');

        $customer->redeemLoyaltyPoints(100);
    }

    public function testRedeemLoyaltyPointsValidatesPositiveAmount(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Loyalty points to redeem must be greater than 0');

        $customer->redeemLoyaltyPoints(0);
    }

    public function testDeactivateChangesStatusToInactive(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->popEvents(); // Clear initial events

        $customer->deactivate();

        self::assertFalse($customer->isActive());

        // Check event recorded
        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerDeactivated::class, $events[0]);
    }

    public function testDeactivateFailsWhenAlreadyInactive(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->deactivate();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer "John Doe" is already inactive');

        $customer->deactivate();
    }

    public function testActivateChangesStatusToActive(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $customer->deactivate();
        $customer->popEvents(); // Clear events

        $customer->activate();

        self::assertTrue($customer->isActive());

        // Check event recorded
        $events = $customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerActivated::class, $events[0]);
    }

    public function testActivateFailsWhenAlreadyActive(): void
    {
        $customer = Customer::register(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer "John Doe" is already active');

        $customer->activate();
    }

    public function testReconstituteFromPersistence(): void
    {
        $now = new \DateTimeImmutable();

        $customer = Customer::reconstituteFromPersistence(
            $this->customerId,
            $this->tenantId,
            $this->email,
            'John',
            'Doe',
            '+12345678901',
            CustomerSegment::vip(),
            150,
            false,
            CustomerPreferences::create(),
            CustomerConsent::default(),
            NotificationPreferences::default(),
            $now,
            $now
        );

        self::assertEquals($this->customerId, $customer->id());
        self::assertEquals($this->tenantId, $customer->tenantId());
        self::assertEquals($this->email, $customer->email());
        self::assertEquals('John', $customer->firstName());
        self::assertEquals('Doe', $customer->lastName());
        self::assertEquals('+12345678901', $customer->phoneNumber());
        self::assertTrue($customer->segment()->isVip());
        self::assertEquals(150, $customer->loyaltyPoints());
        self::assertFalse($customer->isActive());
        self::assertEquals($now, $customer->createdAt());
        self::assertEquals($now, $customer->updatedAt());

        // No events should be recorded for reconstitution
        $events = $customer->popEvents();
        self::assertCount(0, $events);
    }
}
