# Email Notification Infrastructure

**Status**: Implemented (P4-003)
**Date**: 2025-11-27
**Version**: 1.0

## Overview

The email notification infrastructure provides a unified service for sending transactional and marketing emails using Symfony Mailer with async support via Messenger.

## Architecture

### Components

1. **SymfonyEmailSender** - Main service for sending emails
   - Location: `src/Notifications/Infrastructure/Email/SymfonyEmailSender.php`
   - Features:
     - HTML + text template rendering
     - Translation support
     - Async sending via Messenger
     - Batch email support
     - Error logging with retry mechanism

2. **Email Templates** - Twig templates for all email types
   - Location: `templates/emails/`
   - All templates include both HTML and text versions
   - Base layout: `templates/emails/base.html.twig`

3. **Translations** - Email text translations
   - Location: `translations/emails.{locale}.yaml`
   - Supported locales: EN (default), FR, DE

## Configuration

### Environment Variables

```env
# .env
MAILER_DSN=null://localhost              # For dev/test (no actual sending)
MAILER_FROM=noreply@ecom.local           # Default sender address
FRONTEND_URL=http://localhost:3004       # For generating links in emails
```

### Production Configuration

For production, update `MAILER_DSN`:

```env
# SMTP
MAILER_DSN=smtp://username:password@smtp.example.com:587

# Gmail
MAILER_DSN=gmail+smtp://username:password@default

# SendGrid
MAILER_DSN=sendgrid://API_KEY@default

# AWS SES
MAILER_DSN=ses+smtp://ACCESS_KEY:SECRET_KEY@default?region=us-east-1

# Mailgun
MAILER_DSN=mailgun+smtp://USERNAME:PASSWORD@default?region=us
```

### Service Configuration

File: `config/services.yaml`

```yaml
App\Notifications\Infrastructure\Email\SymfonyEmailSender:
    public: true
    arguments:
        $fromEmail: '%env(MAILER_FROM)%'
```

### Mailer Configuration

File: `config/packages/mailer.yaml`

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        envelope:
            sender: '%env(MAILER_FROM)%'
```

### Messenger Configuration

File: `config/packages/messenger.yaml`

```yaml
framework:
    messenger:
        routing:
            # Symfony Mailer messages are routed to async transport
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': async
```

## Usage

### Basic Email Sending

```php
use App\Notifications\Infrastructure\Email\SymfonyEmailSender;

final class OrderPlacedNotificationHandler
{
    public function __construct(
        private readonly SymfonyEmailSender $emailSender,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->emailSender->send(
            to: $event->customerEmail,
            subject: 'Order Confirmation',
            templateName: 'order/order_placed',
            context: [
                'customerName' => $event->customerName,
                'orderId' => $event->orderId->toString(),
                'orderDate' => $event->orderDate,
                'total' => $event->total->getAmount(),
                'currency' => $event->total->getCurrency()->getCurrencyCode(),
                'items' => $event->items,
                'orderViewUrl' => sprintf('%s/orders/%s', $frontendUrl, $event->orderId),
            ],
            locale: $event->locale ?? 'en',
        );
    }
}
```

### Batch Email Sending

```php
$recipients = ['customer1@example.com', 'customer2@example.com', 'customer3@example.com'];

$result = $this->emailSender->sendBatch(
    recipients: $recipients,
    subject: 'Newsletter',
    templateName: 'newsletter/monthly',
    context: ['month' => 'November', 'year' => '2025'],
    locale: 'en',
);

// Result: ['sent' => 3, 'failed' => 0, 'errors' => []]
```

### Custom Sender Address

```php
$this->emailSender->send(
    to: 'customer@example.com',
    subject: 'Support Response',
    templateName: 'support/response',
    context: ['ticketId' => '12345', 'message' => 'Your issue has been resolved.'],
    locale: 'en',
    fromEmail: 'support@example.com', // Override default sender
);
```

## Available Templates

### Order Templates

| Template | HTML | Text | Description |
|----------|------|------|-------------|
| `order/order_placed` | ✓ | ✓ | Order confirmation |
| `order/order_paid` | ✓ | ✓ | Payment confirmation |
| `order/order_shipped` | ✓ | ✓ | Shipping notification |
| `order/order_delivered` | ✓ | ✓ | Delivery confirmation |
| `order/order_cancelled` | ✓ | ✓ | Cancellation notification |

### Payment Templates

| Template | HTML | Text | Description |
|----------|------|------|-------------|
| `payment/payment_captured` | ✓ | ✓ | Payment success |
| `payment/payment_failed` | ✓ | ✓ | Payment failure |
| `payment/payment_refunded` | ✓ | ✓ | Refund processed |
| `payment/payment_cancelled` | ✓ | ✓ | Payment cancelled |

### Cart Templates

| Template | HTML | Text | Description |
|----------|------|------|-------------|
| `cart/cart_abandoned` | ✓ | ✓ | Abandoned cart reminder |

### User Templates

| Template | HTML | Text | Description |
|----------|------|------|-------------|
| `password_reset` | ✓ | ✓ | Password reset link |

## Template Structure

### Required Context Variables

Each template requires specific context variables. See template files for details.

**Example (order/order_placed):**

```php
$context = [
    'customerName' => string,      // Required
    'orderId' => string,           // Required
    'orderDate' => \DateTimeImmutable, // Required
    'total' => string,             // Required
    'currency' => string,          // Required
    'status' => string,            // Required
    'items' => array,              // Optional
    'shippingAddress' => array,    // Optional
    'orderViewUrl' => string,      // Required
];
```

### Creating New Templates

1. **Create HTML template:**
   ```twig
   {# templates/emails/your_category/your_template.html.twig #}
   {% extends 'emails/base.html.twig' %}

   {% block email_title %}{{ 'emails.your_category.your_template.title'|trans }}{% endblock %}
   {% block header_icon %}📧{% endblock %}
   {% block header_title %}{{ 'emails.your_category.your_template.header'|trans }}{% endblock %}

   {% block content %}
       <p>{{ 'common.greeting'|trans({'name': customerName}, 'emails') }}</p>
       {# Your content here #}
   {% endblock %}
   ```

2. **Create text template:**
   ```twig
   {# templates/emails/your_category/your_template.txt.twig #}
   {{ 'emails.your_category.your_template.header'|trans }}

   {{ 'common.greeting'|trans({'name': customerName}, 'emails') }}

   {# Your content here #}

   ---
   {{ 'emails.footer.thanks'|trans }}
   {{ 'emails.footer.company'|trans }}
   ```

3. **Add translations to `translations/emails.en.yaml`:**
   ```yaml
   emails:
     your_category:
       your_template:
         title: 'Email Title'
         header: 'Email Header'
         # Add more translation keys
   ```

## Error Handling

The email sender implements a retry mechanism via Symfony Messenger:

1. **On Transport Failure**: Exception is re-thrown to trigger Messenger retry
2. **Retry Strategy**: Configured in `config/packages/messenger.yaml`
   - Max retries: 3
   - Delay: 1000ms
   - Multiplier: 2 (exponential backoff)

3. **Logging**: All email operations are logged
   - Success: `info` level
   - Failure: `error` level with stack trace

## Testing

### Integration Tests

Location: `tests/Integration/Notifications/Infrastructure/Email/SymfonyEmailSenderTest.php`

Run tests:
```bash
vendor/bin/phpunit tests/Integration/Notifications/
```

Coverage: 9 tests, 15 assertions, 100% line coverage

### Manual Testing

Send test email:
```bash
# Test mailer configuration (sends to specified address)
php bin/console mailer:test customer@example.com

# Or use swiftmailer test (if available)
php bin/console swiftmailer:email:send --to=customer@example.com --subject="Test Email" --body="This is a test"
```

### Development Email Capture

For local development, use MailHog or Mailtrap:

**MailHog** (recommended):
```bash
# Install and run MailHog
docker run -d -p 1025:1025 -p 8025:8025 mailhog/mailhog

# Update .env
MAILER_DSN=smtp://localhost:1025
```

Access captured emails at: http://localhost:8025

**Mailtrap**:
```env
MAILER_DSN=smtp://username:password@smtp.mailtrap.io:2525
```

## Performance Considerations

1. **Async Processing**: All emails are sent asynchronously via Messenger
   - Non-blocking for API requests
   - Automatic retry on failure
   - Scalable with multiple workers

2. **Template Caching**: Twig templates are compiled and cached
   - Production: Cache enabled automatically
   - Development: Cache disabled for hot reload

3. **Translation Caching**: Translations are compiled and cached
   - Use `php bin/console cache:clear` after translation updates

## Monitoring

### Metrics to Track

1. **Email Delivery Rate**: Percentage of successfully sent emails
2. **Bounce Rate**: Percentage of undeliverable emails
3. **Send Latency**: Time from queue to delivery
4. **Failed Messages**: Count in `messenger_failed` queue

### Checking Failed Messages

```bash
# Show failed messages
php bin/console messenger:failed:show

# Retry failed messages
php bin/console messenger:failed:retry

# Remove failed messages
php bin/console messenger:failed:remove
```

## Security Considerations

1. **Email Validation**: Always validate recipient addresses before sending
2. **Rate Limiting**: Implement rate limiting for bulk emails
3. **SPF/DKIM/DMARC**: Configure DNS records for production domain
4. **Content Security**: Sanitize user-generated content in templates
5. **Unsubscribe Links**: Include unsubscribe mechanism for marketing emails

## Troubleshooting

### Common Issues

**Issue**: Emails not being sent

**Solution**:
1. Check Messenger worker is running: `php bin/console messenger:consume async`
2. Verify MAILER_DSN is correct
3. Check logs: `var/log/dev.log` or `var/log/prod.log`
4. Inspect failed queue: `php bin/console messenger:failed:show`

**Issue**: Template not found

**Solution**:
1. Verify template path: `templates/emails/{templateName}.html.twig`
2. Clear cache: `php bin/console cache:clear`
3. Check text template exists (optional but recommended)

**Issue**: Variables not rendering

**Solution**:
1. Verify all required context variables are passed
2. Check variable naming (use camelCase: `customerName`, not `customer_name`)
3. Review template file for correct variable names

## Next Steps

1. **Event Subscribers** - Create subscribers for domain events
   - OrderPlacedSubscriber
   - PaymentCapturedSubscriber
   - CartAbandonedSubscriber

2. **Notification Preferences** - Allow users to customize email settings
3. **Email Analytics** - Track open rates, click rates, conversions
4. **A/B Testing** - Test different email templates
5. **Scheduled Emails** - Send emails at optimal times

## References

- Symfony Mailer Documentation: https://symfony.com/doc/current/mailer.html
- Symfony Messenger Documentation: https://symfony.com/doc/current/messenger.html
- Twig Documentation: https://twig.symfony.com/
- Email Best Practices: https://www.campaignmonitor.com/resources/guides/email-marketing-best-practices/

## Changelog

### Version 1.0 (2025-11-27)

- Initial implementation
- SymfonyEmailSender service with async support
- 11 email templates (HTML + text versions)
- Integration tests with 100% coverage
- English, French, German translations
- Production-ready configuration
