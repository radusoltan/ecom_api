# Payment Integration Guide

**Version**: 1.0
**Last Updated**: October 17, 2025
**Status**: ✅ PayPal Operational | ✅ Stripe Operational | ⚠️ 2Checkout Needs Valid Credentials

---

## Table of Contents

1. [Overview](#overview)
2. [Supported Payment Gateways](#supported-payment-gateways)
3. [Configuration](#configuration)
4. [API Reference](#api-reference)
5. [Gateway-Specific Implementation](#gateway-specific-implementation)
6. [Architecture & DDD Structure](#architecture--ddd-structure)
7. [Testing](#testing)
8. [Production Checklist](#production-checklist)
9. [Troubleshooting](#troubleshooting)

---

## Overview

Multi-tenant e-commerce platform with support for **3 payment gateways**, allowing clients to choose based on:
- Geographic region
- Currency support
- Transaction fees
- Customer preferences

### Supported Payment Gateways

| Gateway | Status | Regions | Currencies | Methods | Test Mode |
|---------|--------|---------|------------|---------|-----------|
| **Stripe** | ✅ Production Ready | US, EU, UK, CA | 135+ | Card, Digital Wallets | ✅ |
| **PayPal** | ✅ Production Ready | Global | 100+ | PayPal, Venmo, Card | ✅ |
| **2Checkout** | ⚠️ Needs Credentials | 200+ countries | 87 | Card, Local Methods | ✅ |

---

## Configuration

### Environment Variables

#### Backend `.env`
```bash
# Stripe - Get from https://dashboard.stripe.com/
STRIPE_PUBLISHABLE_KEY="pk_test_your-stripe-publishable-key"
STRIPE_SECRET_KEY="sk_test_your-stripe-secret-key"

# PayPal - Get from https://developer.paypal.com/dashboard/
PAYPAL_CLIENT_ID="your-paypal-client-id"
PAYPAL_SECRET_KEY="your-paypal-secret-key"

# 2Checkout - Get from https://secure.2checkout.com/
TWO_CHECKOUT_PUBLISHABLE_KEY="your-2checkout-publishable-key"
TWO_CHECKOUT_PRIVATE_KEY="your-2checkout-private-key"
TWO_CHECKOUT_MERCHANT_CODE="your-merchant-code"
TWO_CHECKOUT_SECRET_KEY="your-secret-key"
```

#### Frontend `.env.local`
```bash
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000
NEXT_PUBLIC_TENANT_ID=your-tenant-id

# Only PUBLIC keys should be exposed to frontend
NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_test_your-key
NEXT_PUBLIC_PAYPAL_CLIENT_ID=your-paypal-client-id
NEXT_PUBLIC_2CHECKOUT_MERCHANT_CODE=your-merchant-code
NEXT_PUBLIC_2CHECKOUT_PUBLISHABLE_KEY=your-publishable-key
```

### Multi-Tenancy

All payment operations are tenant-isolated:

```bash
# Required header for all API calls
X-Tenant-ID: {tenant-uuid}
```

**Database**: PostgreSQL Row-Level Security (RLS)
**Caching**: Redis namespacing `{tenant_id}:payments:*`

---

## API Reference

### Base Endpoints

All payment endpoints require:
- `Content-Type: application/json`
- `X-Tenant-ID: {tenant-uuid}`
- `Authorization: Bearer {jwt-token}`

### 1. Create Payment

**Endpoint**: `POST /api/payments`

**Request Body**:
```json
{
  "orderId": "01J9ORDER123456789",
  "amount": 19999,
  "currency": "USD",
  "method": "card",
  "gateway": "stripe",
  "metadata": {
    "billing": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "country": "US"
    }
  }
}
```

**Response** (201 Created):
```json
{
  "id": "01K7P7PAYMENT123456",
  "orderId": "01J9ORDER123456789",
  "status": "pending",
  "gateway": "stripe",
  "amount": 19999,
  "currency": "USD",
  "createdAt": "2025-10-17T10:30:00+00:00"
}
```

### 2. Authorize Payment

**Endpoint**: `POST /api/payments/{id}/authorize`

Authorizes a payment (funds reserved but not captured).

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
  "gatewayTransactionId": "ch_stripe_123456789",
  "authorizedAt": "2025-10-17T10:30:15+00:00"
}
```

### 3. Capture Payment

**Endpoint**: `POST /api/payments/{id}/capture`

Captures an authorized payment (funds transferred).

**Response** (200 OK):
```json
{
  "id": "01K7P7PAYMENT123456",
  "status": "captured",
  "capturedAmountInCents": 19999,
  "capturedAt": "2025-10-17T10:35:00+00:00"
}
```

**Triggers**:
- ✉️ Email confirmation sent to customer
- 📦 Order status updated to "processing"
- 🎯 `OrderPaid` event emitted for fulfillment

### 4. Refund Payment

**Endpoint**: `POST /api/payments/{id}/refund`

**Request Body**:
```json
{
  "amount": 5000,
  "reason": "Customer requested refund"
}
```

### 5. Cancel Payment

**Endpoint**: `POST /api/payments/{id}/cancel`

Cancels an authorized but not yet captured payment.

### 6. Get Payment Status

**Endpoint**: `GET /api/payments/{id}`

---

## Gateway-Specific Implementation

### Stripe Integration ✅

**Best For**: US, EU, UK markets with strong fraud protection

**Backend**: `src/Payment/Infrastructure/Gateway/StripePaymentGateway.php`

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

**API Endpoints**:
- `POST /api/payments/stripe/create-intent` - Creates payment intent
- Webhook support at `/api/payments/stripe/webhook`

---

### PayPal Integration ✅

**Best For**: Global reach, customer trust

**Backend**: `src/Payment/Infrastructure/Gateway/PayPalPaymentGateway.php`

**Features**:
- OAuth 2.0 authentication
- Order creation (authorize flow)
- Order capture (after customer approval)
- Refunds & cancellations
- Status checking

**API Endpoints**:
- `POST /api/v1/payments/paypal/create-order` - Creates PayPal order
- `POST /api/v1/payments/paypal/capture-order` - Captures approved order
- `GET /api/v1/payments/paypal/order-status/{orderId}` - Get order status

**Frontend Implementation**:
```tsx
import { PayPalButtons } from '@paypal/react-paypal-js';

// PayPalButtons handles entire payment flow:
// 1. createOrder - calls backend to create PayPal order
// 2. onApprove - captures payment after customer approval
// 3. onError / onCancel - proper error handling
```

**Test Sandbox**: https://www.sandbox.paypal.com/

---

### 2Checkout Integration ⚠️

**Status**: Backend implemented, requires valid credentials

**Best For**: International markets, 200+ countries

**Backend**: `src/Payment/Infrastructure/Gateway/TwoCheckoutPaymentGateway.php`

**Features**:
- ✅ Full HMAC signature generation
- ✅ 87 currencies
- ✅ Local payment methods
- ✅ Global compliance (PCI DSS, GDPR)

**Test Cards** (Sandbox):
```
Success: 4111 1111 1111 1111 (VISA)
Success: 5555 5555 5555 4444 (MasterCard)
Success: 3782 822463 10005 (AMEX)
```

**Known Issue**: Current demo credentials return 401. Solutions:
1. Update with valid production/sandbox credentials
2. Create new sandbox account at https://www.2checkout.com/

---

## Architecture & DDD Structure

### Directory Structure

```
src/Payment/
├── Domain/
│   ├── Model/Payment.php                    # Aggregate root
│   ├── Service/PaymentGatewayInterface.php  # Gateway port
│   └── ValueObject/
│       ├── PaymentId.php
│       ├── PaymentMethod.php
│       ├── PaymentStatus.php
│       └── PaymentGateway.php
├── Application/
│   ├── Command/                             # Write operations
│   │   ├── CreatePayment.php
│   │   ├── AuthorizePayment.php
│   │   ├── CapturePayment.php
│   │   ├── RefundPayment.php
│   │   └── CancelPayment.php
│   └── Query/                               # Read operations
│       ├── GetPaymentById.php
│       ├── GetPaymentsByOrder.php
│       └── GetAllPayments.php
└── Infrastructure/
    ├── Gateway/                             # Adapter implementations
    │   ├── PayPalPaymentGateway.php         ✅
    │   ├── StripePaymentGateway.php         ✅
    │   ├── TwoCheckoutPaymentGateway.php    ⚠️
    │   └── PaymentGatewayFactory.php
    └── Persistence/Doctrine/
        ├── Entity/PaymentEntity.php
        └── Repository/DoctrineORMPaymentRepository.php
```

### Payment Flow Diagram

```
┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│  Frontend   │─────▶│   Backend   │─────▶│   Gateway   │
│  (Next.js)  │      │  (Symfony)  │      │  (Stripe)   │
└─────────────┘      └─────────────┘      └─────────────┘
       │                    │                     │
       │ 1. Create Payment  │                     │
       ├───────────────────▶│ 2. Call Gateway     │
       │                    ├────────────────────▶│
       │                    │ 3. Return Token     │
       │ 4. Payment Token   │◀────────────────────│
       │◀───────────────────│                     │
       │                    │                     │
       │ 5. Authorize       │ 6. Authorize        │
       ├───────────────────▶├────────────────────▶│
       │                    │ 7. Auth Success     │
       │ 8. Authorized      │◀────────────────────│
       │◀───────────────────│                     │
       │                    │                     │
       │ 9. Capture         │ 10. Capture Payment │
       ├───────────────────▶├────────────────────▶│
       │                    │ 11. Capture Success │
       │ 12. Completed      │◀────────────────────│
       │◀───────────────────│                     │
       │                    │ 12. OrderPaid Event │
       │                    │     → Email + Fulfillment
```

---

## Testing

### Backend Test Commands

```bash
# Test Stripe integration
./scripts/test_stripe_integration.sh

# Test PayPal integration
php bin/console payment:test-paypal -vv

# Test 2Checkout integration
./scripts/test_2checkout_integration.sh
```

### Test Mode Configuration

Set in `.env`:
```env
# Development/Staging
STRIPE_TEST_MODE=true
PAYPAL_SANDBOX=true
TWO_CHECKOUT_SANDBOX=true
```

### Manual Testing

1. Start backend:
```bash
cd /var/www/new_ecom/backend
symfony server:start
```

2. Start frontend:
```bash
cd /var/www/new_ecom/storefront
npm run dev
```

3. Test payment flow:
   - Navigate to checkout page
   - Fill in billing information
   - Select payment method
   - Complete payment
   - Verify order created successfully

---

## Production Checklist

### Pre-Deployment

- [ ] **Stripe**:
  - [ ] Update to production credentials (`pk_live_`, `sk_live_`)
  - [ ] Configure webhook endpoint
  - [ ] Enable 3D Secure
  - [ ] Set up fraud detection rules

- [ ] **PayPal**:
  - [ ] Update to production credentials
  - [ ] Set `$sandbox = false` in `PayPalPaymentGateway.php`
  - [ ] Update frontend `PayPalScriptProvider` to production mode
  - [ ] Configure IPN/webhooks

- [ ] **2Checkout**:
  - [ ] Obtain valid production credentials
  - [ ] Test order creation
  - [ ] Configure webhook notifications
  - [ ] Verify HMAC signature

### Security Checklist

- [x] All secret keys stored in `.env` (not committed to git)
- [x] Public keys separated for frontend
- [ ] HTTPS required for production
- [x] Webhook signature verification implemented
- [x] CORS configured properly
- [x] Rate limiting on payment endpoints
- [x] Multi-tenant isolation enforced
- [ ] PCI DSS compliance verified
- [ ] Audit logging enabled

---

## Troubleshooting

### PayPal SDK Not Loading

**Symptom**: Console error about PayPal script failed to load

**Solution**: Use official PayPal Buttons component from `@paypal/react-paypal-js` instead of manual script loading

### 2Checkout 401 Error

**Symptom**: Authorization failed when creating order

**Possible Causes**:
1. Invalid merchant code
2. Expired or incorrect secret key
3. Demo account deactivated
4. HMAC signature mismatch

**Solution**:
1. Verify credentials in 2Checkout dashboard
2. Generate new API keys
3. Check HMAC signature generation logic
4. Enable sandbox/demo mode

### Payment Not Captured

**Symptom**: Payment authorized but not captured

**Solution**:
- Check that capture endpoint is called after approval
- Verify authorization ID is correctly passed
- Check gateway logs for errors

### Error Response Format

**400 Bad Request**:
```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10.4.1",
  "title": "An error occurred",
  "status": 400,
  "detail": "Invalid gateway",
  "violations": [
    {
      "propertyPath": "gateway",
      "message": "This value is not valid."
    }
  ]
}
```

---

## Support Resources

### Gateway Documentation
- **Stripe**: https://stripe.com/docs
- **PayPal**: https://developer.paypal.com/docs
- **2Checkout**: https://verifone.cloud/docs/2checkout/

### Internal Documentation
- **API Reference**: `/api/docs`
- **GraphQL Playground**: `/api/graphql`
- **CLAUDE.md**: Project architecture and patterns

---

**Document maintained by**: Backend Engineering Team
**Last reviewed**: October 17, 2025
