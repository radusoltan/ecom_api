# Notification Preferences Implementation

**Date**: 2025-11-28
**Status**: ✅ Complete (Domain, Application, Infrastructure layers)

## Summary

Implemented comprehensive notification preferences for customers following DDD/CQRS architecture patterns. Customers can now control which types of notifications they receive and their preferred communication channel (email vs SMS).

## Files Created

### Domain Layer

1. **`src/Customer/Domain/ValueObject/NotificationPreferences.php`**
   - Immutable value object with 8 notification preferences
   - Business rules enforced:
     - `orderUpdates` and `securityAlerts` cannot be disabled (transactional/legal requirements)
     - Validation prevents disabling critical notifications
   - Factory methods: `default()`, `create()`, `fromArray()`
   - Immutable setters: `withOrderUpdates()`, `withShippingUpdates()`, etc.

2. **`src/Customer/Domain/Event/NotificationPreferencesUpdated.php`**
   - Domain event fired when preferences change
   - Includes old and new preferences for audit trail

### Domain Model Updates

3. **Modified `src/Customer/Domain/Model/Customer.php`**
   - Added `NotificationPreferences $notificationPreferences` property
   - Added `updateNotificationPreferences()` method with business rule validation:
     - Promotional offers require marketing email consent
     - SMS preferences require phone number
   - Added `getNotificationPreferences()` getter
   - Updated `register()` to initialize with default preferences
   - Updated `reconstituteFromPersistence()` to include notification preferences

### Infrastructure Layer

4. **Modified `src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerEntity.php`**
   - Added 8 boolean columns for notification preferences:
     - `notifOrderUpdates` (default: true)
     - `notifShippingUpdates` (default: true)
     - `notifPromotionalOffers` (default: false)
     - `notifPriceDropAlerts` (default: false)
     - `notifBackInStockAlerts` (default: false)
     - `notifSecurityAlerts` (default: true)
     - `notifNewsletterWeekly` (default: false)
     - `notifPreferSms` (default: false)
   - Updated `fromDomainModel()` to map notification preferences
   - Updated `updateFromDomainModel()` to sync notification preferences
   - Updated `toDomainModel()` to reconstitute notification preferences

### Application Layer

5. **`src/Customer/Application/Command/UpdateNotificationPreferences/UpdateNotificationPreferencesCommand.php`**
   - Command DTO with all 8 notification preference fields

6. **`src/Customer/Application/Command/UpdateNotificationPreferences/UpdateNotificationPreferencesCommandHandler.php`**
   - Handler that loads customer, creates preferences, validates, and persists

7. **`src/Customer/Application/Query/GetNotificationPreferences/GetNotificationPreferencesQuery.php`**
   - Query DTO to retrieve customer preferences

8. **`src/Customer/Application/Query/GetNotificationPreferences/GetNotificationPreferencesQueryHandler.php`**
   - Handler that returns NotificationPreferencesDTO

9. **`src/Customer/Application/DTO/NotificationPreferencesDTO.php`**
   - Data transfer object for API responses
   - Includes `lastUpdatedAt` timestamp

10. **`src/Customer/Application/Service/NotificationPreferenceService.php`**
    - Service to check preferences before sending notifications
    - Methods:
      - `shouldSendEmail(CustomerId, TenantId, string $notificationType): bool`
      - `shouldSendSms(CustomerId, TenantId, string $notificationType): bool`
      - `shouldSendEmailByEmail(Email, TenantId, string $notificationType): bool`
      - `shouldSendSmsByEmail(Email, TenantId, string $notificationType): bool`
      - `getPreferredChannel(CustomerId, TenantId): string`
    - Notification types: 'order_update', 'shipping', 'promotional', 'price_drop', 'back_in_stock', 'security', 'newsletter'

### Migration

11. **`migrations/Version20251228100004_NotificationPreferences.php`**
    - Adds 8 notification preference columns to `customers` table
    - All columns have sensible defaults
    - Includes column comments for documentation

### Event Subscriber Updates

12. **Modified `src/Order/Application/EventSubscriber/OrderPlacedSubscriber.php`**
    - Added `NotificationPreferenceService` dependency
    - Checks customer preferences before sending order confirmation emails
    - Uses `shouldSendEmailByEmail()` method (lookup by email address)
    - Logs when emails are skipped due to preferences

13. **Modified `src/Order/Application/EventSubscriber/OrderStatusChangedSubscriber.php`**
    - Added `NotificationPreferenceService` dependency
    - Checks customer preferences before sending status change emails
    - Uses `shouldSendEmailByEmail()` method
    - Logs when emails are skipped due to preferences

## Business Rules Implemented

1. **Critical Notifications (Cannot Be Disabled)**:
   - Order updates (`orderUpdates`) - Transactional requirement
   - Security alerts (`securityAlerts`) - Legal/security requirement

2. **Optional Notifications (Opt-in/Opt-out)**:
   - Shipping updates (default: enabled)
   - Promotional offers (default: disabled, requires marketing consent)
   - Price drop alerts (default: disabled)
   - Back in stock alerts (default: disabled)
   - Weekly newsletter (default: disabled)

3. **Channel Preferences**:
   - `preferSms`: Customer prefers SMS over email (requires phone number)

4. **Cross-Validation**:
   - Promotional offers require `marketingEmail` consent
   - SMS preferences require phone number to be set
   - Newsletter requires marketing email consent

## Code Quality

### Static Analysis (PHPStan Level 8)
✅ **PASSED** - All files pass PHPStan level 8 with no errors

Files checked:
- Domain layer (NotificationPreferences, NotificationPreferencesUpdated, Customer)
- Infrastructure layer (CustomerEntity)
- Application layer (Commands, Queries, DTOs, Service)
- Event subscribers (OrderPlacedSubscriber, OrderStatusChangedSubscriber)

### Code Style (PHP-CS-Fixer)
✅ **PASSED** - All files follow PSR-12 coding standards

## Architecture Compliance

### DDD Principles
✅ **Domain models are pure** - No framework dependencies in domain layer
✅ **Business rules in domain** - Validation in NotificationPreferences and Customer aggregate
✅ **Domain events** - NotificationPreferencesUpdated event for audit trail
✅ **Value objects are immutable** - NotificationPreferences uses immutable setters

### CQRS
✅ **Commands for writes** - UpdateNotificationPreferencesCommand
✅ **Queries for reads** - GetNotificationPreferencesQuery
✅ **Separate handlers** - Clear separation of concerns

### Hexagonal Architecture
✅ **Domain at center** - Business logic in Customer aggregate
✅ **Ports (interfaces)** - Repository interface
✅ **Adapters (infrastructure)** - CustomerEntity, Doctrine repository
✅ **Application layer** - Commands, queries, DTOs, services

## Testing Strategy

### Unit Tests (TODO - not in scope)
- NotificationPreferencesTest - Test all factory methods, immutable setters, validation
- Customer aggregate test - Test updateNotificationPreferences() business rules
- NotificationPreferenceServiceTest - Mock repository, test all scenarios

### Integration Tests (TODO - not in scope)
- CustomerRepository - Test persistence of notification preferences
- Database migration - Verify columns created correctly

### Functional Tests (TODO - not in scope)
- UpdateNotificationPreferences API endpoint
- GetNotificationPreferences API endpoint
- OrderPlacedSubscriber - Verify emails respect preferences
- OrderStatusChangedSubscriber - Verify emails respect preferences

## Usage Example

### Update Notification Preferences

```php
use App\Customer\Application\Command\UpdateNotificationPreferences\UpdateNotificationPreferencesCommand;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

$command = new UpdateNotificationPreferencesCommand(
    customerId: CustomerId::fromString('123e4567-e89b-12d3-a456-426614174000'),
    tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
    orderUpdates: true,           // Cannot be false
    shippingUpdates: true,
    promotionalOffers: false,      // Requires marketing consent
    priceDropAlerts: true,
    backInStockAlerts: false,
    securityAlerts: true,          // Cannot be false
    newsletterWeekly: false,
    preferSms: false
);

$commandBus->dispatch($command);
```

### Check Before Sending Notification

```php
use App\Customer\Application\Service\NotificationPreferenceService;

// By customer ID
if ($notificationPreferenceService->shouldSendEmail($customerId, $tenantId, 'promotional')) {
    // Send promotional email
}

// By email address (for order notifications where we only have email)
if ($notificationPreferenceService->shouldSendEmailByEmail($email, $tenantId, 'order_update')) {
    // Send order confirmation
}

// Check SMS preference
if ($notificationPreferenceService->shouldSendSms($customerId, $tenantId, 'shipping')) {
    // Send shipping SMS
}

// Get preferred channel
$channel = $notificationPreferenceService->getPreferredChannel($customerId, $tenantId);
// Returns 'sms' or 'email'
```

## Next Steps (Out of Scope)

The following items were intentionally excluded from this implementation as requested:

1. **API Endpoints** - State processors for API Platform
2. **Tests** - Unit, integration, and functional tests
3. **Frontend Integration** - UI components for preference management
4. **Email Templates** - Updated templates to show preference links
5. **SMS Integration** - Actual SMS sending via Twilio/similar
6. **Unsubscribe Links** - One-click unsubscribe in emails
7. **Preference Center UI** - Dedicated page for managing all preferences

## Migration Instructions

```bash
# Run the migration
symfony console doctrine:migrations:migrate

# Verify columns were added
symfony console dbal:run-sql "SELECT column_name FROM information_schema.columns WHERE table_name = 'customers' AND column_name LIKE 'notif_%'"

# Expected output:
# notif_order_updates
# notif_shipping_updates
# notif_promotional_offers
# notif_price_drop_alerts
# notif_back_in_stock_alerts
# notif_security_alerts
# notif_newsletter_weekly
# notif_prefer_sms
```

## Performance Considerations

1. **Database Queries**: Service methods make 1 additional query to fetch customer preferences
2. **Caching**: Consider caching customer preferences in Redis for high-volume notifications
3. **Batch Processing**: For bulk emails (newsletters), load preferences in batches

## GDPR Compliance

✅ **Opt-in by default** - Marketing notifications default to false
✅ **Audit trail** - NotificationPreferencesUpdated event records all changes
✅ **Easy to withdraw** - Customers can disable any optional notification
✅ **Critical notifications** - Transactional emails cannot be disabled per legal requirements

## Summary

All notification preference functionality has been implemented at the domain, application, and infrastructure layers. The implementation follows DDD/CQRS/Hexagonal architecture principles, passes PHPStan level 8 analysis, and includes comprehensive business rule validation.

API endpoints and tests are intentionally excluded as per requirements and can be added as separate tasks.
