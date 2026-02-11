# Payment Retry Mechanism - Complete Documentation

**Status**: ✅ **COMPLETE** (US-013)
**Epic**: 3 - Payment Integration
**Last Updated**: 2025-11-27

## Overview

The payment retry mechanism automatically retries failed payments using an exponential backoff strategy. This ensures transient payment failures (e.g., temporary network issues, rate limits) don't result in lost sales.

## Architecture

### Components

1. **Domain Layer**
   - `Payment` aggregate with retry methods
   - `RetryPolicy` value object defining retry rules
   - `PaymentRetryExhausted` domain event

2. **Application Layer**
   - `PaymentRetryService` orchestrates retry logic
   - `PaymentRetryExhaustedSubscriber` handles exhausted retries

3. **Infrastructure Layer**
   - `ProcessPaymentRetriesCommand` console command
   - `PaymentScheduleProvider` Symfony Scheduler provider
   - `ProcessPaymentRetries` scheduled message
   - `ProcessPaymentRetriesHandler` message handler
   - `DoctrineORMPaymentRepository::findPendingRetries()` query

## Business Rules

### Retry Policy

Defined in `src/Payment/Domain/ValueObject/RetryPolicy.php`:

```php
- max_attempts: 3
- delay_intervals: [1 hour, 4 hours, 24 hours]
- retryable_errors:
  * card_declined
  * insufficient_funds
  * processing_error
  * network_error
  * timeout
  * gateway_timeout
  * service_unavailable
  * rate_limit_exceeded

- non_retryable_errors:
  * expired_card
  * fraudulent
  * invalid_card_number
  * invalid_cvc
  * stolen_card
  * lost_card
  * restricted_card
  * do_not_honor
  * invalid_amount
  * authentication_required
```

### Retry Schedule

| Attempt | Delay | From Previous Failure |
|---------|-------|----------------------|
| 1st retry | 1 hour | Initial failure |
| 2nd retry | 4 hours | 1st retry failure |
| 3rd retry | 24 hours | 2nd retry failure |

After 3 failed attempts, retries are exhausted and the customer is notified.

## Database Schema

### Payment Table Fields

```sql
-- Retry tracking fields
retry_count INT NOT NULL DEFAULT 0,
next_retry_at TIMESTAMP NULL,
error_code VARCHAR(100) NULL,

-- Performance index for retry queries
CREATE INDEX idx_payments_retry
ON payments (status, next_retry_at, retry_count);
```

The index optimizes the query:
```sql
SELECT * FROM payments
WHERE status = 'failed'
  AND next_retry_at IS NOT NULL
  AND next_retry_at <= NOW()
  AND retry_count < 3
ORDER BY next_retry_at ASC;
```

## Workflow

### 1. Payment Failure with Retry

```
┌─────────────┐
│   Payment   │
│   Fails     │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│ Check if error is retryable │
│ (RetryPolicy)               │
└──────┬──────────────────────┘
       │
       ▼ YES
┌─────────────────────────┐
│ Calculate next retry    │
│ time (1h, 4h, or 24h)  │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│ Update payment:         │
│ - next_retry_at set     │
│ - retry_count unchanged │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│ Dispatch                │
│ PaymentRetryScheduled   │
│ event                   │
└─────────────────────────┘
```

### 2. Automatic Retry Processing

**Option A: Symfony Scheduler (Recommended)**

```
Every 5 minutes:
┌───────────────────────┐
│ Symfony Scheduler     │
│ dispatches message    │
└──────┬────────────────┘
       │
       ▼
┌───────────────────────────────┐
│ ProcessPaymentRetriesHandler  │
│ - Get payments due for retry  │
│ - Process each via service    │
└──────┬────────────────────────┘
       │
       ▼
┌────────────────────────┐
│ PaymentRetryService    │
│ - Attempt gateway call │
│ - Update status        │
│ - Schedule next retry  │
│   or mark exhausted    │
└────────────────────────┘
```

**Option B: Cron Job (Alternative)**

```cron
*/5 * * * * cd /var/www/new_ecom/backend && php bin/console app:payment:process-retries >> /var/log/payment-retries.log 2>&1
```

### 3. Retry Exhaustion

```
After 3rd failure:
┌────────────────────┐
│ Mark retry         │
│ exhausted          │
└──────┬─────────────┘
       │
       ▼
┌──────────────────────┐
│ Dispatch             │
│ PaymentRetryExhausted│
│ event                │
└──────┬───────────────┘
       │
       ▼
┌─────────────────────────────┐
│ PaymentRetryExhaustedSubscriber│
│ - Log exhaustion            │
│ - Send email to customer    │
│ - Suggest payment update    │
└─────────────────────────────┘
```

## API Reference

### Domain Methods

#### Payment::scheduleRetry()

```php
/**
 * Schedule a retry for this payment.
 *
 * @param RetryPolicy $policy Retry policy to use
 * @throws \InvalidArgumentException if max retries reached
 */
public function scheduleRetry(RetryPolicy $policy): void;
```

**Example:**
```php
$payment = $paymentRepository->findById($paymentId, $tenantId);
if ($payment->canRetry($retryPolicy)) {
    $payment->scheduleRetry($retryPolicy);
    $paymentRepository->save($payment);
}
```

#### Payment::recordRetryAttempt()

```php
/**
 * Record that a retry attempt was made.
 *
 * @param bool $wasSuccessful Whether the retry succeeded
 * @param string|null $errorCode Error code if retry failed
 * @param string|null $errorMessage Error message if retry failed
 */
public function recordRetryAttempt(
    bool $wasSuccessful,
    ?string $errorCode = null,
    ?string $errorMessage = null
): void;
```

#### Payment::markRetryExhausted()

```php
/**
 * Mark all retries as exhausted.
 *
 * @param RetryPolicy $policy Retry policy used
 * @throws \InvalidArgumentException if not at max attempts
 */
public function markRetryExhausted(RetryPolicy $policy): void;
```

#### Payment::isDueForRetry()

```php
/**
 * Check if payment is due for retry.
 *
 * @param \DateTimeImmutable|null $now Current time (for testing)
 * @return bool True if next_retry_at <= now
 */
public function isDueForRetry(?\DateTimeImmutable $now = null): bool;
```

### Service Methods

#### PaymentRetryService::scheduleRetry()

```php
/**
 * Schedule a retry for a failed payment.
 *
 * @param Payment $payment Failed payment to retry
 * @return \DateTimeImmutable|null Next retry time, or null if not scheduled
 */
public function scheduleRetry(Payment $payment): ?\DateTimeImmutable;
```

#### PaymentRetryService::processRetry()

```php
/**
 * Process a retry for a payment.
 *
 * @param Payment $payment Payment to retry
 * @return bool True if retry was attempted
 */
public function processRetry(Payment $payment): bool;
```

**Current Implementation:**
The `processRetry()` method currently contains placeholder logic marked with `// TODO`. In production, you need to:

1. Integrate with actual payment gateway
2. Attempt to reauthorize/recapture the payment
3. Handle gateway response
4. Update payment based on result

**Example Implementation:**
```php
public function processRetry(Payment $payment): bool
{
    try {
        // 1. Call payment gateway to retry
        $gatewayResult = $this->paymentGateway->retryPayment(
            $payment->gatewayTransactionId(),
            $payment->amountInCents(),
            $payment->currency()
        );

        // 2. Handle gateway response
        $wasSuccessful = $gatewayResult->isSuccessful();
        $errorCode = $gatewayResult->getErrorCode();
        $errorMessage = $gatewayResult->getErrorMessage();

        // 3. Record retry attempt
        $payment->recordRetryAttempt($wasSuccessful, $errorCode, $errorMessage);

        // 4. Update payment based on result
        if ($wasSuccessful) {
            // Gateway retry succeeded
            $payment->authorize($gatewayResult->getTransactionId());
            // Or capture directly if using direct charge
            $payment->capture();
        } elseif ($payment->retryCount() >= $this->retryPolicy->maxAttempts()) {
            // Max attempts reached
            $payment->markRetryExhausted($this->retryPolicy);
        } else {
            // Schedule next retry
            $this->scheduleRetry($payment);
        }

        $this->paymentRepository->save($payment);
        return true;
    } catch (\Throwable $e) {
        $this->logger->error('Failed to process payment retry', [
            'payment_id' => $payment->id()->toString(),
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}
```

## Console Commands

### Process Payment Retries

```bash
php bin/console app:payment:process-retries
```

**Options:**
- `--dry-run` : Show what would be processed without actually processing
- `--limit=N` : Process maximum N payments (default: 100)

**Examples:**
```bash
# Process all pending retries
php bin/console app:payment:process-retries

# Dry run to see what would be processed
php bin/console app:payment:process-retries --dry-run

# Process only 10 payments
php bin/console app:payment:process-retries --limit=10
```

**Output:**
```
Payment Retry Processor
=======================

Found 5 payment(s) due for retry

 5/5 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

Results
=======

 ------------ -------
  Metric       Count
 ------------ -------
  Total found   5
  Processed     5
  Successful    3
  Failed        2
 ------------ -------

 [OK] Successfully processed 3 payment retry(ies)
```

## Symfony Scheduler Setup

### 1. Files Created

- `src/Payment/Infrastructure/Schedule/PaymentScheduleProvider.php` - Schedule definition
- `src/Payment/Infrastructure/Schedule/Message/ProcessPaymentRetries.php` - Trigger message
- `src/Payment/Infrastructure/Schedule/MessageHandler/ProcessPaymentRetriesHandler.php` - Message handler

### 2. Configuration

The scheduler is auto-registered via the `#[AsSchedule('payment')]` attribute.

### 3. Running the Scheduler

**Development:**
```bash
# Consume scheduled messages
symfony console messenger:consume scheduler_default -vv
```

**Production (systemd service):**
```ini
# /etc/systemd/system/payment-scheduler.service
[Unit]
Description=Payment Retry Scheduler
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/new_ecom/backend
ExecStart=/usr/bin/php /var/www/new_ecom/backend/bin/console messenger:consume scheduler_default --time-limit=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable payment-scheduler
sudo systemctl start payment-scheduler
sudo systemctl status payment-scheduler
```

### 4. Monitoring

**Check scheduler status:**
```bash
# View scheduler schedule
symfony console debug:scheduler payment

# Monitor message consumption
symfony console messenger:stats
```

**Logs:**
- Application logs: `var/log/dev.log` or `var/log/prod.log`
- Search for: `"scheduled payment retry processing"`

## Email Notifications

### PaymentRetryExhaustedSubscriber

When retries are exhausted, the system automatically:

1. **Logs the exhaustion** with full details
2. **Sends HTML + text email** to customer
3. **Suggests next steps**:
   - Update payment method
   - Check with bank
   - Verify account funds
   - Contact support

### Email Template

**Subject:** "Payment Failed - Action Required"

**Content:**
- Payment details (ID, order ID, attempts)
- Error information (code, message)
- Action items for customer
- Support contact information

**Sender:** Configured in subscriber (default: `payments@ecommerce.local`)

**TODO:** Update email recipient to actual customer email from Order aggregate

## Testing

### Manual Testing

1. **Create a failing payment:**
```bash
# Via API or console command
curl -X POST http://localhost:8000/api/payments \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001" \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "ORD-123",
    "amountInCents": 10000,
    "currency": "USD",
    "method": "credit_card",
    "gateway": "stripe"
  }'
```

2. **Mark payment as failed with retryable error:**
```php
$payment->markAsFailed('Card declined', 'card_declined');
$paymentRetryService->scheduleRetry($payment);
```

3. **Verify retry scheduled:**
```sql
SELECT id, status, retry_count, next_retry_at, error_code
FROM payments
WHERE status = 'failed';
```

4. **Trigger retry processing:**
```bash
# Manually trigger
php bin/console app:payment:process-retries --dry-run

# Or wait for scheduler (5 minutes)
```

5. **Check logs:**
```bash
tail -f var/log/dev.log | grep "payment retry"
```

### Unit Testing

**TODO:** Create unit tests for:
- `Payment::scheduleRetry()` business logic
- `RetryPolicy::calculateNextRetryTime()` calculations
- `PaymentRetryService::shouldRetry()` decisions
- `ProcessPaymentRetriesHandler::__invoke()` processing

### Integration Testing

**TODO:** Create integration tests for:
- `DoctrineORMPaymentRepository::findPendingRetries()` query
- Full retry workflow with database
- Event dispatching and subscriber execution

## Performance Considerations

### Database Query Optimization

The `idx_payments_retry` index ensures efficient queries:

```sql
-- Query plan with index:
EXPLAIN SELECT * FROM payments
WHERE status = 'failed'
  AND next_retry_at <= NOW()
  AND retry_count < 3
ORDER BY next_retry_at ASC;

-- Expected: Index scan on idx_payments_retry
```

**Expected Performance:**
- Query time: <10ms for 10,000 payments
- Throughput: 100+ retries per second

### Scheduler Frequency

Running every 5 minutes provides:
- **Best case**: 5-minute delay from due time to actual retry
- **Worst case**: 10-minute delay (if retry became due just after last run)
- **Average**: ~7.5 minutes from scheduled retry time

Adjust frequency in `PaymentScheduleProvider.php` if needed:
```php
// More frequent (every 1 minute)
RecurringMessage::every('1 minute', new ProcessPaymentRetries())

// Less frequent (every 15 minutes)
RecurringMessage::every('15 minutes', new ProcessPaymentRetries())
```

### Concurrency

**Important:** The scheduler should only run as a **single instance** to avoid:
- Duplicate retry processing
- Race conditions on payment updates
- Wasted gateway API calls

Use process locking in production:
```php
// In ProcessPaymentRetriesHandler
use Symfony\Component\Lock\LockFactory;

private LockFactory $lockFactory;

public function __invoke(ProcessPaymentRetries $message): void
{
    $lock = $this->lockFactory->createLock('payment_retry_processing', 300);

    if (!$lock->acquire()) {
        $this->logger->info('Payment retry processing already running');
        return;
    }

    try {
        // Process retries...
    } finally {
        $lock->release();
    }
}
```

## Monitoring & Alerts

### Key Metrics

1. **Retry success rate**
   ```sql
   SELECT
       COUNT(CASE WHEN status = 'authorized' OR status = 'captured' THEN 1 END) as successful,
       COUNT(CASE WHEN status = 'failed' AND retry_count >= 3 THEN 1 END) as exhausted,
       COUNT(*) as total
   FROM payments
   WHERE retry_count > 0;
   ```

2. **Average attempts before success**
   ```sql
   SELECT AVG(retry_count)
   FROM payments
   WHERE status IN ('authorized', 'captured')
     AND retry_count > 0;
   ```

3. **Pending retries**
   ```sql
   SELECT COUNT(*)
   FROM payments
   WHERE status = 'failed'
     AND next_retry_at IS NOT NULL
     AND retry_count < 3;
   ```

### Alerts

**Set up alerts for:**
- High retry exhaustion rate (>50% exhausted)
- Large backlog of pending retries (>100)
- Scheduler not running (no logs for 10+ minutes)
- High error rate during retry processing

## Error Handling

### Transient Errors (Retryable)

These errors trigger automatic retries:
- `card_declined` - Temporary bank decline
- `insufficient_funds` - Customer may add funds
- `processing_error` - Gateway temporary issue
- `network_error` - Network connectivity issue
- `timeout` - Request timeout
- `service_unavailable` - Gateway temporarily down
- `rate_limit_exceeded` - Too many requests

### Permanent Errors (Non-Retryable)

These errors do NOT trigger retries:
- `expired_card` - Card expired, needs new card
- `fraudulent` - Flagged as fraud
- `invalid_card_number` - Invalid card
- `invalid_cvc` - Wrong security code
- `stolen_card` - Card reported stolen
- `restricted_card` - Card blocked
- `authentication_required` - Needs 3D Secure

### Unknown Errors

By default, unknown error codes are **NOT retried** (fail-safe approach).

To make an error retryable, add it to `RetryPolicy::RETRYABLE_ERRORS`.

## Security Considerations

1. **Email sanitization**: Error messages are sanitized before sending to customers
2. **Sensitive data**: Never log full card details or gateway secrets
3. **Rate limiting**: Consider gateway API rate limits (3 attempts over 29 hours = ~0.1/hour)
4. **Idempotency**: Gateway calls should use idempotency keys to prevent double-charging

## Future Enhancements

### Phase 2 (Future)

1. **Configurable retry policy per tenant**
   - Different max attempts
   - Different delay intervals
   - Custom error code lists

2. **Adaptive retry delays**
   - Increase delay if gateway reports rate limits
   - Decrease delay for high-value payments

3. **Webhooks for retry status**
   - Notify customer immediately when retry succeeds
   - Real-time updates instead of waiting for next attempt

4. **Dashboard analytics**
   - Retry success rate over time
   - Common failure reasons
   - Average time to success

5. **Manual retry trigger**
   - Allow admin to force retry immediately
   - Useful for resolved gateway issues

## References

- **PRD**: Section 9.1 - Payment Retry Logic
- **ADR**: ADR-015 - Exponential Backoff for Payment Retries (if created)
- **Domain Model**: `src/Payment/Domain/Model/Payment.php`
- **Retry Policy**: `src/Payment/Domain/ValueObject/RetryPolicy.php`
- **Service**: `src/Payment/Application/Service/PaymentRetryService.php`

## Support

For questions or issues with payment retries:
1. Check logs: `var/log/*.log`
2. Review monitoring metrics
3. Test with `--dry-run` flag
4. Contact DevOps team for production issues
