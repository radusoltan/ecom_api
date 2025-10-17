# Payment Gateway Selection API

**Version**: 1.0
**Date**: January 16, 2025
**Status**: Production Ready

---

## Overview

The e-commerce platform supports **multiple payment gateways**, allowing clients to choose the most appropriate provider based on:
- Geographic region
- Currency support
- Transaction fees
- Customer preferences

### Supported Gateways

| Gateway | Status | Regions | Currencies | Methods | Test Mode |
|---------|--------|---------|------------|---------|-----------|
| **Stripe** | ✅ Production | US, EU, UK, CA | 135+ | Card, Digital Wallets | ✅ |
| **PayPal** | ✅ Production | Global | 100+ | PayPal, Venmo, Card | ✅ |
| **2Checkout** | ✅ Production | 200+ countries | 87 | Card | ✅ |

---

## API Endpoints

### 1. Create Payment

**Endpoint**: `POST /api/payments`

**Description**: Creates a new payment with the specified gateway.

**Request Headers**:
```http
Content-Type: application/json
X-Tenant-ID: {tenant-uuid}
Authorization: Bearer {jwt-token}
```

**Request Body**:
```json
{
  "orderId": "01J9ORDER123456789",
  "amount": 19999,
  "currency": "USD",
  "method": "card",
  "gateway": "twocheckout",
  "metadata": {
    "billing": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "country": "US",
      "state": "CA",
      "city": "Los Angeles",
      "address": "123 Main Street",
      "zip": "90001"
    },
    "customer_ip": "192.168.1.1",
    "description": "Order #12345 - Premium Package"
  }
}
```

**Field Descriptions**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `orderId` | string | Yes | Order identifier (ULID format) |
| `amount` | integer | Yes | Amount in cents (e.g., 19999 = $199.99) |
| `currency` | string | Yes | ISO 4217 currency code (USD, EUR, GBP, etc.) |
| `method` | string | Yes | Payment method: `card`, `paypal`, `bank_transfer` |
| `gateway` | string | Yes | Gateway identifier: `stripe`, `paypal`, `twocheckout` |
| `metadata` | object | No | Additional information for gateway processing |
| `metadata.billing` | object | Recommended | Billing address (required for 2Checkout) |
| `metadata.customer_ip` | string | Recommended | Customer IP address for fraud prevention |

**Response** (201 Created):
```json
{
  "id": "01K7P7PAYMENT123456",
  "orderId": "01J9ORDER123456789",
  "tenantId": "9efae4ea-94fc-4807-b1bc-5e495ee7858c",
  "amount": 19999,
  "currency": "USD",
  "method": "card",
  "gateway": "twocheckout",
  "status": "pending",
  "createdAt": "2025-01-16T10:30:00+00:00",
  "updatedAt": "2025-01-16T10:30:00+00:00"
}
```

**Response Status Codes**:
- `201 Created` - Payment created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Insufficient permissions
- `422 Unprocessable Entity` - Validation failed

---

### 2. Authorize Payment

**Endpoint**: `POST /api/payments/{id}/authorize`

**Description**: Authorizes a payment through the selected gateway. Funds are reserved but not captured.

**Request Headers**:
```http
Content-Type: application/json
X-Tenant-ID: {tenant-uuid}
Authorization: Bearer {jwt-token}
```

**Request Body**:
```json
{
  "paymentMethodId": "pm_card_visa_123456"
}
```

**Response** (200 OK):
```json
{
  "id": "01K7P7PAYMENT123456",
  "status": "authorized",
  "gatewayTransactionId": "2CO123456789",
  "authorizedAt": "2025-01-16T10:30:15+00:00",
  "metadata": {
    "gateway_order_no": "987654321",
    "approval_url": "https://secure.2checkout.com/checkout/12345"
  }
}
```

---

### 3. Capture Payment

**Endpoint**: `POST /api/payments/{id}/capture`

**Description**: Captures an authorized payment. Funds are transferred from customer to merchant.

**Request Headers**:
```http
Content-Type: application/json
X-Tenant-ID: {tenant-uuid}
Authorization: Bearer {jwt-token}
```

**Request Body** (Optional):
```json
{
  "amount": 19999
}
```

**Response** (200 OK):
```json
{
  "id": "01K7P7PAYMENT123456",
  "status": "captured",
  "capturedAmountInCents": 19999,
  "capturedAt": "2025-01-16T10:35:00+00:00"
}
```

**Triggers**:
- ✉️ Email confirmation sent to customer
- 📦 Order status updated to "processing"
- 🎯 `OrderPaid` event emitted for fulfillment

---

### 4. Refund Payment

**Endpoint**: `POST /api/payments/{id}/refund`

**Description**: Refunds a captured payment (full or partial).

**Request Headers**:
```http
Content-Type: application/json
X-Tenant-ID: {tenant-uuid}
Authorization: Bearer {jwt-token}
```

**Request Body**:
```json
{
  "amount": 5000,
  "reason": "Customer requested refund - product defect"
}
```

**Response** (200 OK):
```json
{
  "id": "01K7P7PAYMENT123456",
  "status": "refunded",
  "refundedAmountInCents": 5000,
  "refundReason": "Customer requested refund - product defect",
  "refundedAt": "2025-01-16T11:00:00+00:00"
}
```

---

### 5. Cancel Payment

**Endpoint**: `POST /api/payments/{id}/cancel`

**Description**: Cancels an authorized but not yet captured payment.

**Request Headers**:
```http
Content-Type: application/json
X-Tenant-ID: {tenant-uuid}
Authorization: Bearer {jwt-token}
```

**Request Body**:
```json
{
  "reason": "Customer cancelled order"
}
```

**Response** (200 OK):
```json
{
  "id": "01K7P7PAYMENT123456",
  "status": "cancelled",
  "cancelledAt": "2025-01-16T10:45:00+00:00",
  "cancelReason": "Customer cancelled order"
}
```

---

### 6. Get Payment Status

**Endpoint**: `GET /api/payments/{id}`

**Description**: Retrieves current payment status and details.

**Request Headers**:
```http
X-Tenant-ID: {tenant-uuid}
Authorization: Bearer {jwt-token}
```

**Response** (200 OK):
```json
{
  "id": "01K7P7PAYMENT123456",
  "orderId": "01J9ORDER123456789",
  "tenantId": "9efae4ea-94fc-4807-b1bc-5e495ee7858c",
  "amount": 19999,
  "currency": "USD",
  "method": "card",
  "gateway": "twocheckout",
  "status": "captured",
  "gatewayTransactionId": "2CO123456789",
  "capturedAmountInCents": 19999,
  "refundedAmountInCents": 0,
  "createdAt": "2025-01-16T10:30:00+00:00",
  "authorizedAt": "2025-01-16T10:30:15+00:00",
  "capturedAt": "2025-01-16T10:35:00+00:00",
  "updatedAt": "2025-01-16T10:35:00+00:00"
}
```

---

## Gateway-Specific Guides

### Stripe

**Best For**: US, EU, UK markets with strong fraud protection

**Setup**:
```json
{
  "gateway": "stripe",
  "metadata": {
    "payment_method_id": "pm_card_visa",
    "setup_future_usage": "off_session",
    "customer_id": "cus_123456"
  }
}
```

**Features**:
- ✅ 3D Secure (SCA compliance)
- ✅ Digital wallets (Apple Pay, Google Pay)
- ✅ Subscription billing
- ✅ Advanced fraud detection

**Test Cards**:
```
Success: 4242 4242 4242 4242
Decline: 4000 0000 0000 0002
3D Secure: 4000 0027 6000 3184
```

---

### PayPal

**Best For**: Global reach, customer trust, alternative payment methods

**Setup**:
```json
{
  "gateway": "paypal",
  "metadata": {
    "return_url": "https://yoursite.com/payment/success",
    "cancel_url": "https://yoursite.com/payment/cancel"
  }
}
```

**Features**:
- ✅ PayPal account payments
- ✅ Venmo integration
- ✅ Pay Later options
- ✅ Buyer protection

**Test Account**:
```
Email: sb-test@business.example.com
Password: [Provided by PayPal Sandbox]
```

---

### 2Checkout

**Best For**: International markets, 200+ countries, local payment methods

**Setup**:
```json
{
  "gateway": "twocheckout",
  "metadata": {
    "billing": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "country": "US",
      "city": "Los Angeles",
      "address": "123 Main St",
      "zip": "90001"
    },
    "customer_ip": "192.168.1.1"
  }
}
```

**Features**:
- ✅ 87 currencies
- ✅ Local payment methods
- ✅ Subscription management
- ✅ Global compliance (PCI DSS, GDPR)

**Test Cards** (Sandbox):
```
Success: 4111 1111 1111 1111 (VISA)
Success: 5555 5555 5555 4444 (MasterCard)
Success: 3782 822463 10005 (AMEX)
```

**Test Cardholders**:
- `John Doe` - Success
- `Mona Doe` - Insufficient funds
- Any CVV and expiry date works in sandbox

**Sandbox URL**: https://sandbox.2checkout.com/sandbox/

---

## Payment Flow Diagram

```
┌─────────────┐
│   Client    │
│  Frontend   │
└──────┬──────┘
       │ POST /api/payments
       │ {"gateway": "twocheckout", ...}
       ↓
┌─────────────────┐
│  API Platform   │
│ PaymentResource │
└──────┬──────────┘
       │ CreatePayment Command
       ↓
┌────────────────────┐
│ Payment Aggregate  │
│ Domain Model       │
└──────┬─────────────┘
       │ PaymentCreated Event
       ↓
┌──────────────────────────┐
│  PaymentRepository       │
│  Save to Database        │
└──────┬───────────────────┘
       │
       │ POST /api/payments/{id}/authorize
       ↓
┌────────────────────────────┐
│  AuthorizePayment Command  │
└──────┬─────────────────────┘
       │
       ↓
┌─────────────────────────────┐
│ PaymentGatewayFactory       │
│ Select: TwoCheckoutGateway  │
└──────┬──────────────────────┘
       │ authorize()
       ↓
┌───────────────────────────┐
│  2Checkout API            │
│  https://api.2checkout.com│
└──────┬────────────────────┘
       │ Transaction ID: 2CO123456789
       ↓
┌───────────────────────────┐
│ PaymentAuthorized Event   │
└──────┬────────────────────┘
       │
       │ POST /api/payments/{id}/capture
       ↓
┌─────────────────────────┐
│ CapturePayment Command  │
└──────┬──────────────────┘
       │
       ↓
┌───────────────────────────┐
│ PaymentCaptured Event     │
│ → RabbitMQ (payment_events)
└──────┬────────────────────┘
       │
       ↓
┌──────────────────────────────────┐
│ PaymentCapturedSubscriber        │
│ 1. Update Order → "processing"   │
│ 2. Emit OrderPaid Event          │
│ 3. Send Confirmation Email       │
└──────────────────────────────────┘
```

---

## Error Handling

### Common Error Responses

**400 Bad Request**:
```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10.4.1",
  "title": "An error occurred",
  "status": 400,
  "detail": "Invalid gateway: 'invalid_gateway'. Supported gateways: stripe, paypal, twocheckout",
  "violations": [
    {
      "propertyPath": "gateway",
      "message": "This value is not valid."
    }
  ]
}
```

**Gateway Errors**:
```json
{
  "type": "https://example.com/errors/payment-gateway-error",
  "title": "Payment Gateway Error",
  "status": 422,
  "detail": "2Checkout authorization failed: Insufficient funds",
  "gateway": "twocheckout",
  "gatewayCode": "DECLINED",
  "gatewayMessage": "Insufficient funds"
}
```

### Retry Strategy

The platform automatically retries failed operations:

| Operation | Max Retries | Delay | Multiplier |
|-----------|-------------|-------|------------|
| Payment Events | 3 | 1s | 2x (1s, 2s, 4s) |
| Order Events | 3 | 1s | 2x |
| Inventory Events | 5 | 2s | 2x (2s, 4s, 8s, 16s, 32s) |

**Failed messages** are moved to `failed` queue for manual inspection.

---

## Testing

### Test Mode

All gateways support test mode. Set in `.env`:

```env
# Development/Staging
STRIPE_TEST_MODE=true
PAYPAL_SANDBOX=true
TWO_CHECKOUT_SANDBOX=true
```

### Integration Tests

```bash
# Test Stripe integration
php bin/console payment:test-stripe

# Test PayPal integration
php bin/console payment:test-paypal

# Test 2Checkout integration
php bin/console payment:test-2checkout
```

### Postman Collection

Import collection: `/docs/api/postman/Payment_Gateway_API.postman_collection.json`

---

## Best Practices

### 1. Gateway Selection

```javascript
// Frontend: Detect customer location and currency
const selectGateway = (country, currency) => {
  if (['US', 'CA', 'UK'].includes(country)) {
    return 'stripe';  // Best for North America/UK
  } else if (currency === 'EUR' || currency === 'GBP') {
    return 'paypal';  // Global reach
  } else {
    return 'twocheckout';  // International
  }
};
```

### 2. Idempotency

Use idempotency keys to prevent duplicate charges:

```http
POST /api/payments
Idempotency-Key: {unique-request-id}
```

### 3. Webhook Handling

Subscribe to payment events for async updates:

```php
// config/routes.yaml
payment_webhooks:
    path: /webhooks/payments/{gateway}
    controller: App\Payment\Presentation\Controller\WebhookController
    methods: POST
```

### 4. Security

- ✅ Always use HTTPS
- ✅ Validate webhook signatures
- ✅ Store card data via PCI-compliant tokenization
- ✅ Log all payment operations for audit trail
- ✅ Use fraud detection services

---

## Support

### Documentation
- **Stripe**: https://stripe.com/docs
- **PayPal**: https://developer.paypal.com/docs
- **2Checkout**: https://verifone.cloud/docs/2checkout/

### Internal
- **API Reference**: `/api/docs`
- **GraphQL Playground**: `/api/graphql`
- **Sprint 2 Docs**: `SPRINT_2_PARTIAL_IMPLEMENTATION.md`

### Contact
- **Engineering**: dev@example.com
- **DevOps**: devops@example.com
- **Support**: support@example.com

---

**Last Updated**: January 16, 2025
**API Version**: 1.0
**Maintained By**: Backend Engineering Team
