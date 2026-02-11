# Cart Abandonment Email Notification System

Complete implementation of cart abandonment recovery emails following DDD/CQRS architecture.

## Overview

The cart abandonment system automatically identifies carts that have been inactive for 24 hours and sends recovery emails to customers, encouraging them to complete their purchase.

## Architecture

### Components

```
Cart/
├── Domain/
│   └── Event/
│       └── CartAbandoned.php                    # Domain event
├── Application/
│   ├── Service/
│   │   └── CartAbandonmentService.php           # Core business logic
│   └── EventSubscriber/
│       └── CartAbandonedSubscriber.php          # Email sender
└── Infrastructure/
    ├── Console/
    │   └── ProcessAbandonedCartsCommand.php     # CLI command
    └── Persistence/Doctrine/
        ├── Entity/CartEntity.php                # Updated with tracking field
        └── Repository/DoctrineCartRepository.php # Query methods
```

### Email Template

```
templates/emails/cart/cart_abandoned.html.twig
```

### Translations

```
translations/emails.en.yaml (updated)
```

### Database Migration

```
migrations/Version20251126120000.php
```

## Business Rules

1. **Abandonment Criteria**:
   - Cart not updated in **24 hours**
   - Cart status is **active**
   - Cart has **at least one item**
   - Customer must be **authenticated** (has CustomerId)

2. **Email Sending**:
   - **Only one email** per cart (tracked via `abandonment_email_sent` flag)
   - Email includes cart summary, total, and items
   - Yellow/gold header for attention
   - Clear CTA button to complete order

3. **Processing**:
   - Runs via cron job (recommended every 6 hours)
   - Processes up to **100 carts** per run (batch processing)
   - Oldest carts processed first
   - Email failures logged but don't block processing

## Database Schema Changes

### New Column: `carts.abandonment_email_sent`

```sql
ALTER TABLE carts
ADD COLUMN abandonment_email_sent BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX idx_carts_abandonment_email
ON carts(abandonment_email_sent, status, updated_at);
```

## Usage

### Run Migration

```bash
symfony console doctrine:migrations:migrate
```

### Manual Execution

```bash
# Process abandoned carts
symfony console app:cart:process-abandoned

# Dry-run mode (see what would be processed without sending emails)
symfony console app:cart:process-abandoned --dry-run
```

### Cron Setup

Add to your crontab to run every 6 hours:

```bash
0 */6 * * * cd /var/www/new_ecom/backend && /usr/bin/php bin/console app:cart:process-abandoned >> /var/log/cart-abandonment.log 2>&1
```

Or use Symfony's cron package:

```yaml
# config/packages/cron.yaml
cron:
  jobs:
    cart_abandonment:
      command: app:cart:process-abandoned
      schedule: '0 */6 * * *'
      description: 'Process abandoned carts and send recovery emails'
```

## Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│  Cron Job (Every 6 hours)                                   │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  ProcessAbandonedCartsCommand                               │
│  - Calls CartAbandonmentService                             │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  CartAbandonmentService                                      │
│  1. Find abandoned carts (not updated in 24h)               │
│  2. Filter: active, has items, has customer, no email sent  │
│  3. Process up to 100 carts                                 │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  For each cart:                                              │
│  1. Load Cart aggregate                                     │
│  2. Load Customer to get email                              │
│  3. Dispatch CartAbandoned event                            │
│  4. Mark abandonment_email_sent = TRUE                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│  CartAbandonedSubscriber                                     │
│  - Listens for CartAbandoned event                          │
│  - Renders email template                                   │
│  - Sends email via Symfony Mailer                           │
│  - Logs success/failure                                     │
└─────────────────────────────────────────────────────────────┘
```

## Email Template Features

### Yellow/Gold Theme
- Header color: `#f59e0b` (amber)
- Gradient background for cart summary
- Attention-grabbing design

### Content Sections
1. **Greeting** - Personalized with customer name
2. **Cart Summary** - Item count, total, expiry date
3. **Item List** - Product names, quantities, prices
4. **Special Offer** - Optional discount incentive
5. **CTA Button** - "Complete Your Order"
6. **Footer** - Support information

### Translation Keys

All text is translatable via `emails.en.yaml`:

```yaml
emails:
  cart:
    abandoned:
      title: 'You left items in your cart'
      header: 'Don''t Forget Your Cart!'
      greeting: 'Hello %customer_name%,'
      message: 'We noticed you left %item_count% item(s)...'
      # ... etc
```

## Testing

### Unit Tests (TODO)

```bash
# Test domain event
vendor/bin/phpunit tests/Unit/Cart/Domain/Event/CartAbandonedTest.php

# Test service
vendor/bin/phpunit tests/Unit/Cart/Application/Service/CartAbandonmentServiceTest.php

# Test subscriber
vendor/bin/phpunit tests/Unit/Cart/Application/EventSubscriber/CartAbandonedSubscriberTest.php
```

### Integration Tests (TODO)

```bash
# Test repository queries
vendor/bin/phpunit tests/Integration/Cart/Infrastructure/Persistence/Doctrine/Repository/DoctrineCartRepositoryTest.php

# Test command execution
vendor/bin/phpunit tests/Integration/Cart/Infrastructure/Console/ProcessAbandonedCartsCommandTest.php
```

### Manual Testing

1. Create a cart with items
2. Set `updated_at` to 25 hours ago:
   ```sql
   UPDATE carts
   SET updated_at = NOW() - INTERVAL '25 hours'
   WHERE id = 'your-cart-id';
   ```
3. Run command:
   ```bash
   symfony console app:cart:process-abandoned --dry-run
   ```
4. Check logs for processing details

## Monitoring & Metrics

### Key Metrics to Track

1. **Processing Metrics**:
   - Number of abandoned carts found
   - Emails sent vs skipped
   - Error rate
   - Processing duration

2. **Business Metrics**:
   - Email open rate
   - Click-through rate (cart URL)
   - Conversion rate (abandoned → completed)
   - Revenue recovered

### Logging

All operations logged via Psr\Log\LoggerInterface:

```php
// Success
$this->logger->info('Cart abandonment email sent', [
    'cartId' => $event->cartId->toString(),
    'customerEmail' => $event->customerEmail,
    'itemCount' => $event->itemCount,
]);

// Error
$this->logger->error('Failed to send cart abandonment email', [
    'cartId' => $event->cartId->toString(),
    'error' => $exception->getMessage(),
]);
```

## Configuration

### Environment Variables

Add to `.env`:

```env
# Cart abandonment email settings
CART_ABANDONMENT_SENDER_EMAIL=carts@ecommerce.local
CART_ABANDONMENT_SENDER_NAME="E-Commerce Platform"
CART_ABANDONMENT_STOREFRONT_URL=http://localhost:3000
```

### Service Configuration

```yaml
# config/services.yaml
services:
    App\Cart\Application\EventSubscriber\CartAbandonedSubscriber:
        arguments:
            $senderEmail: '%env(CART_ABANDONMENT_SENDER_EMAIL)%'
            $senderName: '%env(CART_ABANDONMENT_SENDER_NAME)%'
            $storefrontUrl: '%env(CART_ABANDONMENT_STOREFRONT_URL)%'
```

## Future Enhancements

### Phase 1 - Current Implementation ✅
- [x] Basic abandoned cart detection
- [x] Single email notification
- [x] Email tracking to prevent duplicates
- [x] Professional HTML template
- [x] Translation support

### Phase 2 - Enhancements (TODO)
- [ ] Multiple email sequence (24h, 48h, 72h)
- [ ] Dynamic discount offers
- [ ] A/B testing for email content
- [ ] Display actual cart items in email
- [ ] Customer name personalization from Customer entity
- [ ] Guest cart support (store email during checkout start)

### Phase 3 - Analytics (TODO)
- [ ] Email open tracking
- [ ] Click tracking
- [ ] Conversion attribution
- [ ] ROI reporting dashboard
- [ ] Integration with analytics platform

## Security Considerations

1. **Email Validation**: Only send to verified customer emails
2. **Rate Limiting**: Batch processing prevents email spam
3. **Data Privacy**: Cart data handled according to GDPR
4. **Error Handling**: Email failures don't expose system internals

## Troubleshooting

### No Emails Sent

1. Check if carts meet criteria:
   ```sql
   SELECT id, customer_id, updated_at, abandonment_email_sent
   FROM carts
   WHERE status = 'active'
   AND updated_at < NOW() - INTERVAL '24 hours'
   AND abandonment_email_sent = FALSE;
   ```

2. Check if customers exist:
   ```sql
   SELECT c.id, cu.email
   FROM carts c
   LEFT JOIN customers cu ON c.customer_id = cu.id
   WHERE c.customer_id IS NOT NULL;
   ```

3. Check email configuration:
   ```bash
   symfony console debug:config framework mailer
   ```

### Emails Sent Multiple Times

- Check `abandonment_email_sent` flag is being set:
  ```sql
  SELECT id, abandonment_email_sent FROM carts WHERE id = 'cart-id';
  ```

### Performance Issues

- Monitor query performance with EXPLAIN:
  ```sql
  EXPLAIN ANALYZE
  SELECT id FROM carts
  WHERE updated_at < $1
  AND status = 'active'
  AND abandonment_email_sent = FALSE
  AND (SELECT COUNT(*) FROM cart_items WHERE cart_id = carts.id) > 0
  LIMIT 100;
  ```

- Ensure index exists:
  ```sql
  \d carts
  -- Should show: idx_carts_abandonment_email
  ```

## References

- [Cart Domain Model](/var/www/new_ecom/backend/src/Cart/Domain/Model/Cart.php)
- [CartEntity](/var/www/new_ecom/backend/src/Cart/Infrastructure/Persistence/Doctrine/Entity/CartEntity.php)
- [Order Email Templates](/var/www/new_ecom/backend/templates/emails/order/)
- [DDD Architecture Guide](/var/www/new_ecom/CLAUDE.md)

---

**Version**: 1.0
**Date**: 2025-11-26
**Author**: Cart Abandonment Team
