# Stripe Webhook Testing

## Overview

This directory contains tests for the Stripe webhook handler (`/api/webhooks/stripe` endpoint).

## Testing Challenges

### Signature Verification

Stripe webhooks use HMAC SHA-256 signature verification to ensure webhook authenticity. The signature is generated using:
- The webhook payload
- The current timestamp
- The webhook signing secret

**Problem**: We cannot generate valid Stripe signatures in automated tests without:
1. Using the actual webhook secret from Stripe
2. Implementing Stripe's exact signature algorithm
3. Mocking Stripe's static `Webhook::constructEvent()` method

### Solution: Multi-Layer Testing Strategy

#### 1. Unit Tests (`tests/Unit/Payment/Infrastructure/Webhook/`)
**Focus**: Basic request validation and error handling

Tests:
- Missing signature header → 400
- Invalid signature → 400
- Empty payload handling
- Malformed JSON handling
- Handler instantiation

**What's NOT tested**: Event processing logic (requires valid signatures)

#### 2. Functional Tests (this directory)
**Focus**: Endpoint accessibility and routing

Tests:
- Endpoint exists and accepts POST
- Endpoint does not require JWT authentication
- Only POST method allowed (GET/PUT/DELETE rejected)
- Returns 400 for invalid signatures (expected)

**What's NOT tested**: Actual webhook event processing

#### 3. Manual Testing with Stripe CLI (Recommended)

For comprehensive webhook testing, use the Stripe CLI:

```bash
# Install Stripe CLI
# https://stripe.com/docs/stripe-cli

# Login to your Stripe account
stripe login

# Forward webhooks to local endpoint
stripe listen --forward-to localhost:8000/api/webhooks/stripe

# Trigger test events
stripe trigger payment_intent.succeeded
stripe trigger payment_intent.payment_failed
stripe trigger charge.refunded
```

This provides:
- Real Stripe signatures
- Actual event payloads
- End-to-end validation

#### 4. Integration Testing (Staging/Production)

Configure Stripe webhook in dashboard:
- URL: `https://your-domain.com/api/webhooks/stripe`
- Events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
- Use test mode for staging, live mode for production

Monitor webhook logs in Stripe dashboard.

## Test Coverage Summary

| Test Type | Coverage | Status |
|-----------|----------|--------|
| Unit Tests | Request validation, error handling | ✅ Complete (6 tests) |
| Functional Tests | Endpoint routing, method validation | ✅ Complete (11 tests) |
| Event Processing | Webhook event handling | ⚠️ Manual (Stripe CLI) |
| Integration | End-to-end with real Stripe | ⚠️ Manual (Staging) |

## Webhook Event Handling

The handler supports these events:

### 1. `payment_intent.succeeded`
- Extracts `payment_id` and `tenant_id` from metadata
- Queries payment from database
- Captures payment if not already captured
- Returns 200 with "Payment processed"

### 2. `payment_intent.payment_failed`
- Logs failure reason
- Returns 200 with "Payment failure recorded"
- TODO: Update payment status to failed

### 3. `payment_intent.canceled`
- Logs cancellation reason
- Returns 200 with "Cancellation confirmed"

### 4. `charge.refunded`
- Logs refund details
- Returns 200 with "Refund confirmed"

### 5. Unknown Events
- Logs event type
- Returns 200 with "Event received"

## Error Scenarios

| Scenario | Response | Notes |
|----------|----------|-------|
| Missing signature header | 400 "Missing signature" | Logged as error |
| Invalid signature | 400 "Invalid signature" | Logged with error details |
| Missing payment metadata | 200 "Missing metadata" | Logged as warning |
| Payment not found | 200 "Payment not found" | Logged as warning |
| Payment already captured | 200 "Payment processed" | Skips capture, logs info |
| Processing exception | 500 "Webhook processing failed" | Logged with trace |

## Configuration

Webhook secret is configured in `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_signing_secret
```

Get this from Stripe Dashboard → Developers → Webhooks → [Your endpoint] → Signing secret

## Security

1. **Signature Verification**: All webhooks must have valid Stripe signatures
2. **No Authentication**: Webhooks don't use JWT (Stripe handles auth via signatures)
3. **HTTPS Only**: Production webhooks must use HTTPS (Stripe requirement)
4. **Idempotency**: Handler checks payment status to avoid double-processing

## Monitoring

In production, monitor:
- Webhook delivery success rate (Stripe dashboard)
- Response times (< 5 seconds recommended)
- Error logs for processing failures
- Payment capture success rate after webhooks

## Troubleshooting

### Signature Verification Failures

```
Stripe webhook: Invalid signature
```

**Causes**:
- Wrong webhook secret in `.env`
- Endpoint URL mismatch in Stripe dashboard
- Request body modified by middleware
- HTTPS/TLS certificate issues

**Solution**: Verify `STRIPE_WEBHOOK_SECRET` matches Stripe dashboard secret.

### Payment Not Found

```
Stripe webhook: Payment not found
```

**Causes**:
- Metadata missing from Stripe PaymentIntent
- Payment deleted from database
- Multi-tenant isolation (wrong tenant_id)

**Solution**: Ensure `payment_id` and `tenant_id` are set in PaymentIntent metadata during payment creation.

### Timeout Errors

```
Stripe webhook: Processing failed - timeout
```

**Causes**:
- Slow database queries
- External API calls in webhook handler
- Long-running business logic

**Solution**: Webhook handlers should complete in < 5 seconds. Move slow operations to async jobs.

## Future Improvements

1. **Event Store**: Store webhook events for replay/debugging
2. **Idempotency Keys**: Use Stripe event IDs to prevent duplicate processing
3. **Async Processing**: Move business logic to background jobs
4. **Webhook Retries**: Handle Stripe's automatic retry behavior
5. **Payment Status Updates**: Implement UpdatePaymentStatus command for failed/canceled states
