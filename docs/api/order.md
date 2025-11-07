# Order API Documentation v1.0

## Overview

The Order API provides endpoints for managing orders in a multi-tenant e-commerce platform. All endpoints support idempotency and rate limiting to ensure reliable and secure operations.

---

## Base URL

```
/api/orders
```

---

## Authentication & Authorization

All requests require:
- **Header**: `X-Tenant-ID: {tenant-uuid}` - Tenant isolation
- **Header**: `Authorization: Bearer {jwt-token}` - User authentication

---

## Idempotency

### Overview

The Order API implements idempotency for all `POST` requests to prevent duplicate order creation due to network retries or client errors.

### How It Works

1. Client generates a unique `Idempotency-Key` (UUID recommended)
2. Include header: `Idempotency-Key: {unique-key}`
3. Server caches successful responses (2xx) for 24 hours
4. Duplicate requests with same key return cached response
5. Same key with different payload returns `422 Unprocessable Entity`

### Example Request

```http
POST /api/orders
Content-Type: application/json
X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...

{
  "customerEmail": "customer@example.com",
  "lines": [
    {
      "productId": "223e4567-e89b-12d3-a456-426614174001",
      "productName": "Premium Wireless Headphones",
      "quantity": 2,
      "unitPriceAmount": 19999,
      "unitPriceCurrency": "USD"
    }
  ],
  "shippingAddress": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US"
  },
  "billingAddress": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US"
  }
}
```

### Idempotency Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Idempotency-Key` | Yes (for POST) | Unique identifier (UUID or alphanumeric string, max 255 chars) |

### Idempotency Response Headers

| Header | Description |
|--------|-------------|
| `X-Idempotency-Replay` | Set to `true` if response was served from cache |

### Idempotency Errors

#### 422 Unprocessable Entity - Key Conflict

```json
{
  "type": "https://tools.ietf.org/html/rfc7231#section-6.5.1",
  "title": "Idempotency key conflict",
  "status": 422,
  "detail": "The provided idempotency key has been used with a different request payload."
}
```

**Cause**: Same `Idempotency-Key` used with different request body.

**Solution**: Use a new unique key for the new request.

---

## Rate Limiting

### Overview

To prevent abuse and ensure fair resource usage, the Order API implements rate limiting per IP address and tenant.

### Limits

| Operation | Limit | Window | Scope |
|-----------|-------|--------|-------|
| `POST /api/orders` | 10 requests | 1 minute | Per IP + Tenant |
| Global per tenant | 100 requests | 1 minute | Per Tenant |

### Rate Limit Headers

All responses include rate limit information:

| Header | Description | Example |
|--------|-------------|---------|
| `X-RateLimit-Limit` | Maximum requests allowed | `10` |
| `X-RateLimit-Remaining` | Remaining requests in current window | `7` |
| `X-RateLimit-Reset` | Unix timestamp when limit resets | `1704110400` |

### Rate Limit Exceeded Response

#### 429 Too Many Requests

```json
{
  "type": "https://tools.ietf.org/html/rfc6585#section-4",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Rate limit exceeded. Please retry in 45 seconds.",
  "retry_after": 45
}
```

**Headers**:
- `Retry-After: 45` - Seconds to wait before retrying
- `X-RateLimit-Limit: 10`
- `X-RateLimit-Remaining: 0`
- `X-RateLimit-Reset: 1704110400`

**Solution**: Wait for the time specified in `Retry-After` header before retrying.

---

## Fraud Detection

### Overview

All order placement requests are screened for suspicious activity using velocity-based fraud detection.

### Risk Levels

| Level | Score Range | Action |
|-------|-------------|--------|
| Low | 0-30 | Order processed normally |
| Medium | 31-60 | Order processed, flagged for monitoring |
| High | 61-100 | Order logged for manual review |

### Fraud Indicators

- High order velocity from same IP (>5 orders in 10 minutes)
- High order velocity from same email (>3 orders in 10 minutes)
- Multiple failed payment attempts followed by success

### Response

Fraud detection is transparent to the client. High-risk orders are logged but not blocked by default. Administrators can view fraud scores in the admin panel.

---

## Endpoints

### 1. Place Order

Creates a new order with payment, tax calculation, and promotion application.

**Endpoint**: `POST /api/orders`

**Headers**:
- `Content-Type: application/json`
- `X-Tenant-ID: {tenant-uuid}` (required)
- `Idempotency-Key: {unique-key}` (required)
- `Authorization: Bearer {token}` (required)

**Request Body**:

```json
{
  "customerEmail": "customer@example.com",
  "lines": [
    {
      "productId": "uuid",
      "productName": "Product Name",
      "quantity": 2,
      "unitPriceAmount": 19999,
      "unitPriceCurrency": "USD"
    }
  ],
  "shippingAddress": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US"
  },
  "billingAddress": {
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US"
  },
  "couponCode": "SAVE20",
  "promotionContext": {
    "campaignId": "summer-sale-2025"
  }
}
```

**Response**: `201 Created`

```json
{
  "id": "order-uuid",
  "tenantId": "tenant-uuid",
  "customerEmail": "customer@example.com",
  "status": "pending",
  "lines": [...],
  "shippingAddress": {...},
  "billingAddress": {...},
  "totalAmount": 39998,
  "totalCurrency": "USD",
  "appliedPromotions": [...],
  "discountAmount": 0,
  "createdAt": "2025-01-15T10:30:00Z",
  "updatedAt": "2025-01-15T10:30:00Z"
}
```

**Errors**:
- `400 Bad Request` - Invalid request payload
- `422 Unprocessable Entity` - Idempotency key conflict
- `429 Too Many Requests` - Rate limit exceeded

---

### 2. Get Order by ID

Retrieves a single order by ID.

**Endpoint**: `GET /api/orders/{id}`

**Headers**:
- `X-Tenant-ID: {tenant-uuid}` (required)
- `Authorization: Bearer {token}` (required)

**Response**: `200 OK`

```json
{
  "id": "order-uuid",
  "tenantId": "tenant-uuid",
  "customerEmail": "customer@example.com",
  "status": "processing",
  "lines": [...],
  "totalAmount": 39998,
  "totalCurrency": "USD",
  "createdAt": "2025-01-15T10:30:00Z"
}
```

**Errors**:
- `404 Not Found` - Order not found

---

### 3. List Orders

Retrieves a paginated list of orders.

**Endpoint**: `GET /api/orders`

**Query Parameters**:
- `page` (optional) - Page number (default: 1)
- `limit` (optional) - Items per page (default: 30, max: 100)
- `customerEmail` (optional) - Filter by customer email
- `status` (optional) - Filter by status

**Headers**:
- `X-Tenant-ID: {tenant-uuid}` (required)
- `Authorization: Bearer {token}` (required)

**Response**: `200 OK`

```json
{
  "data": [
    {
      "id": "order-uuid-1",
      "customerEmail": "customer@example.com",
      "status": "delivered",
      "totalAmount": 39998,
      "createdAt": "2025-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "total": 150,
    "page": 1,
    "limit": 30,
    "pages": 5
  }
}
```

---

### 4. Update Order Status

Updates the status of an existing order.

**Endpoint**: `PATCH /api/orders/{id}/status`

**Headers**:
- `Content-Type: application/json`
- `X-Tenant-ID: {tenant-uuid}` (required)
- `Authorization: Bearer {token}` (required)

**Request Body**:

```json
{
  "status": "processing"
}
```

**Response**: `200 OK`

**Errors**:
- `400 Bad Request` - Invalid status transition
- `404 Not Found` - Order not found

---

### 5. Cancel Order

Cancels an existing order.

**Endpoint**: `PATCH /api/orders/{id}/cancel`

**Headers**:
- `X-Tenant-ID: {tenant-uuid}` (required)
- `Authorization: Bearer {token}` (required)

**Response**: `200 OK`

**Errors**:
- `400 Bad Request` - Order cannot be cancelled (already shipped/delivered)
- `404 Not Found` - Order not found

---

## Order Status State Machine

```
draft → pending → processing → shipped → delivered
   ↓       ↓          ↓
cancelled  cancelled  cancelled
```

**Valid Transitions**:
- `draft` → `pending` (order placed)
- `pending` → `processing` (payment captured)
- `pending` → `cancelled` (customer cancellation)
- `processing` → `shipped` (fulfillment completed)
- `processing` → `cancelled` (merchant cancellation)
- `shipped` → `delivered` (delivery confirmed)

---

## Best Practices

### Idempotency Keys

1. **Generate on client**: Use UUIDv4 or similar collision-resistant algorithm
2. **One key per operation**: Never reuse keys across different orders
3. **Store mapping**: Keep track of key → order ID for your records
4. **Retry with same key**: Always use the same key when retrying failed requests

### Rate Limiting

1. **Implement exponential backoff**: Wait longer between each retry
2. **Respect Retry-After header**: Never retry before the specified time
3. **Monitor limits**: Track `X-RateLimit-Remaining` to avoid hitting limits
4. **Use separate IPs**: If possible, distribute load across multiple IPs

### Error Handling

1. **Parse error responses**: All errors follow RFC 7807 problem+json format
2. **Log idempotency conflicts**: Investigate 422 responses for debugging
3. **Handle 429 gracefully**: Implement retry logic with backoff
4. **Check fraud logs**: High-risk orders may require manual review

---

## Examples

### Complete Order Placement with Retry Logic

```javascript
async function placeOrderWithRetry(orderData, maxRetries = 3) {
  const idempotencyKey = generateUUID();
  let attempt = 0;

  while (attempt < maxRetries) {
    try {
      const response = await fetch('/api/orders', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Tenant-ID': 'your-tenant-id',
          'Idempotency-Key': idempotencyKey,
          'Authorization': 'Bearer your-token'
        },
        body: JSON.stringify(orderData)
      });

      if (response.ok) {
        return await response.json();
      }

      if (response.status === 429) {
        const retryAfter = response.headers.get('Retry-After');
        await sleep(retryAfter * 1000);
        attempt++;
        continue;
      }

      if (response.status === 422) {
        // Idempotency conflict - check if original request succeeded
        console.error('Idempotency key conflict');
        return null;
      }

      throw new Error(`Order placement failed: ${response.status}`);
    } catch (error) {
      attempt++;
      await sleep(Math.pow(2, attempt) * 1000); // Exponential backoff
    }
  }

  throw new Error('Max retries exceeded');
}
```

---

## Monitoring & Observability

### Metrics

The following metrics are exposed for monitoring:

- `order_placed_total` - Total orders placed
- `order_rate_limit_hits_total` - Rate limit violations
- `order_idempotency_cache_hits` - Idempotent request replays
- `order_fraud_score_distribution` - Fraud score histogram

### Logs

Key log events:

- `Idempotency: cached response` - Response served from cache
- `Idempotency key reused with different payload` - 422 conflict
- `Order placement rate limit exceeded` - 429 rate limit
- `High-risk order detected` - Fraud score > 60

---

## Support

For API issues or questions:
- **Email**: api-support@ecom-platform.com
- **Docs**: https://docs.ecom-platform.com/api
- **Status**: https://status.ecom-platform.com

**Version**: 1.0
**Last Updated**: January 2025
