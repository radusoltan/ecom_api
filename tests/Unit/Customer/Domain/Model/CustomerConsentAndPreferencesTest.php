<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Event\CustomerConsentChanged;
use App\Customer\Domain\Event\CustomerPreferencesUpdated;
use App\Customer\Domain\Event\NotificationPreferencesUpdated;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerConsent;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Customer\Domain\ValueObject\NotificationPreferences;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Customer::class)]
final class CustomerConsentAndPreferencesTest extends TestCase
{
    private Customer $customer;
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();

        $this->customer = Customer::register(
            id: $this->customerId,
            tenantId: $this->tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );
        // Clear registration event
        $this->customer->popEvents();
    }

    // -----------------------------------------------------------------------
    // updateConsent
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesMarketingEmailConsent(): void
    {
        $this->customer->updateConsent(ConsentType::MARKETING_EMAIL, true);

        self::assertTrue($this->customer->getConsents()->marketingEmail());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerConsentChanged::class, $events[0]);
    }

    #[Test]
    public function itUpdatesMarketingSmsConsent(): void
    {
        $this->customer->updateConsent(ConsentType::MARKETING_SMS, true);

        self::assertTrue($this->customer->getConsents()->marketingSms());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerConsentChanged::class, $events[0]);
    }

    #[Test]
    public function itUpdatesThirdPartySharingConsent(): void
    {
        $this->customer->updateConsent(ConsentType::THIRD_PARTY_SHARING, true);

        self::assertTrue($this->customer->getConsents()->thirdPartySharing());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerConsentChanged::class, $events[0]);
    }

    #[Test]
    public function itUpdatesAnalyticsTrackingConsent(): void
    {
        $this->customer->updateConsent(ConsentType::ANALYTICS_TRACKING, true);

        self::assertTrue($this->customer->getConsents()->analyticsTracking());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerConsentChanged::class, $events[0]);
    }

    #[Test]
    public function itWithdrawsMarketingEmailConsent(): void
    {
        // First grant it
        $this->customer->updateConsent(ConsentType::MARKETING_EMAIL, true);
        $this->customer->popEvents();

        // Then withdraw
        $this->customer->updateConsent(ConsentType::MARKETING_EMAIL, false);

        self::assertFalse($this->customer->getConsents()->marketingEmail());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerConsentChanged::class, $events[0]);
    }

    #[Test]
    public function itRecordsConsentChangedEventWithCorrectData(): void
    {
        $this->customer->updateConsent(ConsentType::MARKETING_SMS, true);

        $events = $this->customer->popEvents();
        /** @var CustomerConsentChanged $event */
        $event = $events[0];
        self::assertSame(ConsentType::MARKETING_SMS, $event->consentType());
        self::assertTrue($event->granted());
        self::assertTrue($this->customerId->equals($event->customerId()));
    }

    #[Test]
    public function itInitialisesWithAllConsentsDefaultedToFalse(): void
    {
        $consents = $this->customer->getConsents();

        self::assertFalse($consents->marketingEmail());
        self::assertFalse($consents->marketingSms());
        self::assertFalse($consents->thirdPartySharing());
        self::assertFalse($consents->analyticsTracking());
    }

    // -----------------------------------------------------------------------
    // updatePreferences
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesPreferencesAndRecordsEvent(): void
    {
        $newPreferences = CustomerPreferences::create(
            preferredLanguage: 'fr',
            preferredCurrency: 'EUR',
        );

        $this->customer->updatePreferences($newPreferences);

        self::assertSame('fr', $this->customer->preferences()->preferredLanguage());
        self::assertSame('EUR', $this->customer->preferences()->preferredCurrency());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CustomerPreferencesUpdated::class, $events[0]);
    }

    #[Test]
    public function itPreferencesEventContainsOldAndNewData(): void
    {
        $initialPreferences = $this->customer->preferences()->toArray();

        $newPreferences = CustomerPreferences::create(
            preferredLanguage: 'de',
            preferredCurrency: 'USD',
        );

        $this->customer->updatePreferences($newPreferences);

        $events = $this->customer->popEvents();
        /** @var CustomerPreferencesUpdated $event */
        $event = $events[0];
        self::assertSame($initialPreferences, $event->oldPreferences());
        self::assertSame($newPreferences->toArray(), $event->newPreferences());
    }

    #[Test]
    public function itInitialisesWithDefaultPreferences(): void
    {
        $preferences = $this->customer->preferences();

        self::assertSame('en', $preferences->preferredLanguage());
        self::assertSame('EUR', $preferences->preferredCurrency());
        self::assertFalse($preferences->newsletterSubscribed());
        self::assertFalse($preferences->marketingEmailsAllowed());
    }

    // -----------------------------------------------------------------------
    // updateNotificationPreferences
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesNotificationPreferencesAndRecordsEvent(): void
    {
        $newPrefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: false,
            promotionalOffers: false,
            priceDropAlerts: true,
            backInStockAlerts: true,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false,
        );

        $this->customer->updateNotificationPreferences($newPrefs);

        self::assertFalse($this->customer->getNotificationPreferences()->shippingUpdates());
        self::assertTrue($this->customer->getNotificationPreferences()->priceDropAlerts());

        $events = $this->customer->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NotificationPreferencesUpdated::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenPromotionalOffersEnabledWithoutMarketingEmailConsent(): void
    {
        // Marketing email consent is false by default
        $newPrefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: true, // requires marketing email consent
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false,
        );

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Promotional offers notifications require marketing email consent');

        $this->customer->updateNotificationPreferences($newPrefs);
    }

    #[Test]
    public function itAllowsPromotionalOffersWhenMarketingEmailConsentGranted(): void
    {
        // First grant marketing email consent
        $this->customer->updateConsent(ConsentType::MARKETING_EMAIL, true);
        $this->customer->popEvents();

        $newPrefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: true,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false,
        );

        $this->customer->updateNotificationPreferences($newPrefs);

        self::assertTrue($this->customer->getNotificationPreferences()->promotionalOffers());
    }

    #[Test]
    public function itThrowsWhenSmsPreferenceEnabledWithoutPhoneNumber(): void
    {
        // Customer has no phone number
        $newPrefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: true, // requires phone number
        );

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('SMS notifications require a phone number to be set');

        $this->customer->updateNotificationPreferences($newPrefs);
    }

    #[Test]
    public function itAllowsSmsPreferenceWhenPhoneNumberIsSet(): void
    {
        // Register customer with phone number
        $customerWithPhone = Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('phone@example.com'),
            firstName: 'Jane',
            lastName: 'Smith',
            phoneNumber: '+12025551234',
        );
        $customerWithPhone->popEvents();

        $newPrefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: true,
        );

        $customerWithPhone->updateNotificationPreferences($newPrefs);

        self::assertTrue($customerWithPhone->getNotificationPreferences()->preferSms());
    }

    #[Test]
    public function itNotificationPreferencesEventContainsOldAndNewData(): void
    {
        $oldPrefs = $this->customer->getNotificationPreferences()->toArray();

        $newPrefs = NotificationPreferences::create(
            orderUpdates: true,
            shippingUpdates: false,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: true,
            preferSms: false,
        );

        $this->customer->updateNotificationPreferences($newPrefs);

        $events = $this->customer->popEvents();
        /** @var NotificationPreferencesUpdated $event */
        $event = $events[0];
        // NotificationPreferencesUpdated uses public constructor promotion
        self::assertSame($oldPrefs, $event->oldPreferences);
        self::assertSame($newPrefs->toArray(), $event->newPreferences);
    }

    #[Test]
    public function itInitialisesWithDefaultNotificationPreferences(): void
    {
        $prefs = $this->customer->getNotificationPreferences();

        self::assertTrue($prefs->orderUpdates());
        self::assertTrue($prefs->shippingUpdates());
        self::assertFalse($prefs->promotionalOffers());
        self::assertTrue($prefs->securityAlerts());
        self::assertFalse($prefs->preferSms());
    }

    // -----------------------------------------------------------------------
    // Name trimming and validation edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itTrimsWhitespaceFromNamesOnRegister(): void
    {
        $customer = Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('trim@example.com'),
            firstName: '  Alice  ',
            lastName: '  Wonderland  ',
        );

        self::assertSame('Alice', $customer->firstName());
        self::assertSame('Wonderland', $customer->lastName());
        self::assertSame('Alice Wonderland', $customer->fullName());
    }

    #[Test]
    public function itTrimsWhitespaceFromNamesOnUpdateProfile(): void
    {
        $this->customer->updateProfile('  Bob  ', '  Builder  ');

        self::assertSame('Bob', $this->customer->firstName());
        self::assertSame('Builder', $this->customer->lastName());
    }

    #[Test]
    public function itAcceptsNameOfExactlyTwoCharacters(): void
    {
        $customer = Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('ab@example.com'),
            firstName: 'Al',
            lastName: 'Bo',
        );

        self::assertSame('Al', $customer->firstName());
        self::assertSame('Bo', $customer->lastName());
    }

    #[Test]
    public function itAcceptsNameOfExactlyFiftyCharacters(): void
    {
        $fiftyChars = str_repeat('A', 50);
        $customer = Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('fifty@example.com'),
            firstName: $fiftyChars,
            lastName: $fiftyChars,
        );

        self::assertSame($fiftyChars, $customer->firstName());
    }

    #[Test]
    public function itThrowsWhenFirstNameIsAllWhitespace(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('First name must be between 2 and 50 characters');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('ws@example.com'),
            firstName: '   ', // Only whitespace -> 0 chars after trim
            lastName: 'Doe',
        );
    }

    #[Test]
    public function itThrowsWhenLastNameIsAllWhitespace(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Last name must be between 2 and 50 characters');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('ws2@example.com'),
            firstName: 'John',
            lastName: '   ',
        );
    }

    // -----------------------------------------------------------------------
    // Phone number validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsValidE164PhoneNumbers(): void
    {
        $validNumbers = ['+12025551234', '+442071838750', '+33123456789', '+49301234567'];

        foreach ($validNumbers as $number) {
            $customer = Customer::register(
                id: CustomerId::generate(),
                tenantId: $this->tenantId,
                email: Email::fromString('phone'.mt_rand().'@example.com'),
                firstName: 'John',
                lastName: 'Doe',
                phoneNumber: $number,
            );
            self::assertSame($number, $customer->phoneNumber());
        }
    }

    #[Test]
    public function itThrowsWhenPhoneNumberMissingPlusSign(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid phone number format');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('badphone@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '12025551234', // missing +
        );
    }

    #[Test]
    public function itThrowsWhenPhoneNumberTooShort(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid phone number format');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('shortphone@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+1', // only country code
        );
    }

    #[Test]
    public function itThrowsWhenPhoneNumberTooLong(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid phone number format');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('longphone@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+1234567890123456', // 16 digits after +, exceeds 15
        );
    }

    #[Test]
    public function itThrowsWhenPhoneNumberHasLetters(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid phone number format');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('letterphone@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+1ABC5551234',
        );
    }

    #[Test]
    public function itThrowsWhenPhoneNumberStartsWithZero(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid phone number format');

        Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('zerostart@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+02025551234', // starts with +0
        );
    }

    #[Test]
    public function itValidatesPhoneNumberOnUpdateProfileToo(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid phone number format');

        $this->customer->updateProfile('John', 'Doe', 'not-e164-format');
    }

    #[Test]
    public function itClearsPhoneNumberWhenNullPassedOnUpdate(): void
    {
        $customerWithPhone = Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString('clearphone@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+12025551234',
        );

        $customerWithPhone->updateProfile('John', 'Doe', null);

        self::assertNull($customerWithPhone->phoneNumber());
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence with addresses
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteFromPersistenceWithAddressesDoesNotRecordEvents(): void
    {
        $now = new \DateTimeImmutable();

        $customer = Customer::reconstituteFromPersistence(
            id: $this->customerId,
            tenantId: $this->tenantId,
            email: Email::fromString('reconstitute@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: null,
            segment: \App\Customer\Domain\ValueObject\CustomerSegment::vip(),
            loyaltyPoints: 500,
            isActive: true,
            preferences: CustomerPreferences::create(),
            consents: CustomerConsent::default(),
            notificationPreferences: NotificationPreferences::default(),
            createdAt: $now,
            updatedAt: $now,
        );

        // No events from reconstitution
        self::assertFalse($customer->hasEvents());
        self::assertSame(500, $customer->loyaltyPoints());
        self::assertTrue($customer->segment()->isVip());
    }

    // -----------------------------------------------------------------------
    // redeemLoyaltyPoints — negative points edge case
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenRedeemingNegativePoints(): void
    {
        $this->customer->awardLoyaltyPoints(100, 'Test');
        $this->customer->popEvents();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Loyalty points to redeem must be greater than 0');

        $this->customer->redeemLoyaltyPoints(-10);
    }

    #[Test]
    public function itAllowsRedeemingExactBalance(): void
    {
        $this->customer->awardLoyaltyPoints(50, 'Test');
        $this->customer->popEvents();

        $this->customer->redeemLoyaltyPoints(50);

        self::assertSame(0, $this->customer->loyaltyPoints());
    }

    // -----------------------------------------------------------------------
    // awardLoyaltyPoints — negative points edge case
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenAwardingNegativePoints(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Loyalty points to award must be greater than 0');

        $this->customer->awardLoyaltyPoints(-5, 'Negative test');
    }

    #[Test]
    public function itTracksUpdatedAtOnLoyaltyPointOperations(): void
    {
        $beforeAward = $this->customer->updatedAt();
        // Ensure at least 1 microsecond passes
        usleep(1000);
        $this->customer->awardLoyaltyPoints(10, 'test');

        self::assertGreaterThanOrEqual(
            $beforeAward->getTimestamp(),
            $this->customer->updatedAt()->getTimestamp()
        );
    }
}
