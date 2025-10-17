# Sprint 1 Testing & Verification Guide

## 🧪 Quick Start

### Prerequisites
```bash
# Ensure Redis is running
redis-cli ping
# Expected: PONG

# Ensure PHP dependencies are installed
cd /var/www/new_ecom/backend
composer install
```

---

## 🏃 Running Tests

### All Sprint 1 Tests
```bash
# Run all new tests
php bin/phpunit tests/Unit/Shared/Infrastructure/Http/Middleware/IdempotencyMiddlewareTest.php
php bin/phpunit tests/Functional/Order/OrderRateLimitingTest.php
```

### Individual Test Suites

#### 1. Idempotency Middleware Tests
```bash
php bin/phpunit tests/Unit/Shared/Infrastructure/Http/Middleware/IdempotencyMiddlewareTest.php --testdox
```

**Expected Output**:
```
Idempotency Middleware
 ✔ Handles cache exception gracefully
 ✔ Returns 422 for different payload with same key
 ✔ Ignores post requests without idempotency key
 ✔ Rejects invalid idempotency key format
 ✔ Allows first request with valid key
 ✔ Does not cache error responses
 ✔ Returns cached response for duplicate request
 ✔ Caches successful response
 ✔ Ignores non post requests

OK (9 tests, 40 assertions)
```

#### 2. Rate Limiting Functional Tests
```bash
php bin/phpunit tests/Functional/Order/OrderRateLimitingTest.php --testdox
```

**Expected Output**:
```
Order Rate Limiting
 ✔ Order placement within limit is allowed
 ✔ Exceeding rate limit returns 429
 ✔ Rate limit headers are present in response
 ✔ Different ips have separate rate limits

OK (5 tests, X assertions)
```

### Test Coverage
```bash
# Generate coverage report (requires Xdebug)
XDEBUG_MODE=coverage php bin/phpunit tests/Unit/Shared/Infrastructure/Http/Middleware/IdempotencyMiddlewareTest.php --coverage-text

# Expected: 100% coverage for IdempotencyMiddleware
```

---

## 🔍 Manual Verification

### 1. Idempotency Testing

#### Setup Test Environment
```bash
# Start Symfony server
symfony server:start -d

# Or use PHP built-in server
php -S 127.0.0.1:8000 -t public/
```

#### Test 1: First Request Creates Order
```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000" \
  -H "Idempotency-Key: test-idempotency-$(date +%s)" \
  -d '{
    "customerEmail": "test@example.com",
    "lines": [
      {
        "productId": "223e4567-e89b-12d3-a456-426614174001",
        "productName": "Test Product",
        "quantity": 1,
        "unitPriceAmount": 1000,
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
  }'
```

**Expected**: 201 Created with order details

#### Test 2: Duplicate Request Returns Cached Response
```bash
# Use SAME Idempotency-Key as Test 1
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000" \
  -H "Idempotency-Key: test-idempotency-SAME-KEY" \
  -d '{ /* SAME payload */ }'
```

**Expected**:
- Same 201 response
- Header: `X-Idempotency-Replay: true`
- Same order ID as first request

#### Test 3: Different Payload with Same Key Returns 422
```bash
# Use SAME Idempotency-Key but DIFFERENT payload
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000" \
  -H "Idempotency-Key: test-idempotency-SAME-KEY" \
  -d '{
    "customerEmail": "different@example.com",
    "lines": [ /* different lines */ ]
  }'
```

**Expected**:
```json
{
  "type": "https://tools.ietf.org/html/rfc7231#section-6.5.1",
  "title": "Idempotency key conflict",
  "status": 422,
  "detail": "The provided idempotency key has been used with a different request payload."
}
```

---

### 2. Rate Limiting Testing

#### Test 1: Normal Request (Under Limit)
```bash
for i in {1..5}; do
  curl -X POST http://127.0.0.1:8000/api/orders \
    -H "Content-Type: application/json" \
    -H "X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000" \
    -H "Idempotency-Key: rate-test-$i" \
    -d '{ /* valid payload */ }'
  echo "Request $i completed"
  sleep 1
done
```

**Expected**: All requests succeed (status 201 or 4xx for validation)

#### Test 2: Exceed Rate Limit
```bash
# Send 12 requests rapidly (limit is 10/minute)
for i in {1..12}; do
  echo "Sending request $i..."
  curl -i -X POST http://127.0.0.1:8000/api/orders \
    -H "Content-Type: application/json" \
    -H "X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000" \
    -H "Idempotency-Key: rate-burst-$i" \
    -d '{ /* valid payload */ }' | head -n 1
done
```

**Expected After 10th Request**:
```
HTTP/1.1 429 Too Many Requests
Retry-After: 60
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1704110400

{
  "type": "https://tools.ietf.org/html/rfc6585#section-4",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Rate limit exceeded. Please retry in 60 seconds.",
  "retry_after": 60
}
```

---

### 3. Fraud Detection Verification

#### Test 1: Normal Order (Low Risk)
```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 123e4567-e89b-12d3-a456-426614174000" \
  -H "Idempotency-Key: fraud-test-1" \
  -d '{ /* valid payload */ }'

# Check logs for fraud score
tail -f var/log/dev.log | grep "fraud check"
```

**Expected Log**:
```
[info] Order fraud check performed {"order_id":"...","fraud_score":0,"risk_level":"low"}
```

#### Test 2: High Velocity (High Risk)
```bash
# Rapidly place 6 orders from same IP
for i in {1..6}; do
  curl -X POST http://127.0.0.1:8000/api/orders \
    -H "Idempotency-Key: fraud-velocity-$i" \
    -d '{ /* valid payload */ }' &
done
wait

# Check logs
tail -n 50 var/log/dev.log | grep -E "(fraud|High-risk)"
```

**Expected Log** (after 5th order):
```
[warning] High-risk order detected {"fraud_score":40,"risk_level":"high","reasons":["High order velocity from IP: 6 orders in 10 minutes"]}
```

---

## 📊 Monitoring & Debugging

### Check Redis Keys

```bash
# View idempotency keys
redis-cli KEYS "idempotency:*"

# View fraud tracking keys
redis-cli KEYS "fraud_check:*"

# View specific key
redis-cli GET "idempotency:tenant-123:test-key-123"

# Monitor all Redis operations
redis-cli MONITOR
```

### Check Logs

```bash
# Tail application logs
tail -f var/log/dev.log

# Filter idempotency logs
tail -f var/log/dev.log | grep idempotency

# Filter rate limit logs
tail -f var/log/dev.log | grep "rate limit"

# Filter fraud logs
tail -f var/log/dev.log | grep fraud
```

### Clear Cache (Reset State)

```bash
# Clear Symfony cache
php bin/console cache:clear

# Clear Redis (WARNING: clears all data)
redis-cli FLUSHDB

# Clear specific pattern
redis-cli --scan --pattern "idempotency:*" | xargs redis-cli DEL
```

---

## 🐛 Troubleshooting

### Issue: Tests Fail with "Connection refused"

**Solution**: Ensure Redis is running
```bash
sudo service redis-server start
# or
redis-server --daemonize yes
```

### Issue: Idempotency Not Working

**Checklist**:
1. ✅ Redis running: `redis-cli ping`
2. ✅ Cache configured: Check `config/packages/framework.yaml`
3. ✅ Middleware registered: Check `config/services/idempotency.yaml`
4. ✅ Headers present: `Idempotency-Key` in request

**Debug**:
```bash
# Check if middleware is loaded
php bin/console debug:event-dispatcher kernel.request
# Should show: App\Shared\Infrastructure\Http\Middleware\IdempotencyMiddleware
```

### Issue: Rate Limiting Not Working

**Checklist**:
1. ✅ Rate limiter configured: Check `config/packages/rate_limiter.yaml`
2. ✅ Listener registered: Check `src/Order/Infrastructure/Http/EventListener/`
3. ✅ Symfony RateLimiter component installed: `composer show symfony/rate-limiter`

**Debug**:
```bash
# Check if listener is registered
php bin/console debug:event-dispatcher kernel.request
# Should show: OrderRateLimitListener

# Check rate limiter configuration
php bin/console debug:config framework rate_limiter
```

### Issue: Fraud Scores Always 0

**Checklist**:
1. ✅ FraudCheckService injected in PlaceOrderProcessor
2. ✅ Redis tracking keys created: `redis-cli KEYS "fraud_check:*"`
3. ✅ Multiple requests from same IP/email

**Debug**:
```bash
# Check service registration
php bin/console debug:container FraudCheckService

# Monitor Redis writes during order placement
redis-cli MONITOR | grep fraud_check
```

---

## 🎯 Acceptance Criteria Verification

### ✅ Idempotency
- [ ] Two identical POST requests → only one order created
- [ ] Log shows "Idempotency: reused cached response"
- [ ] Different payload with same key → 422 response
- [ ] Invalid key format → logged warning, request proceeds

### ✅ Rate Limiting
- [ ] 11th request within 1 minute → 429 response
- [ ] Response includes `Retry-After` header
- [ ] Response includes `X-RateLimit-*` headers
- [ ] Different IPs have separate limits
- [ ] Log shows rate limit violations

### ✅ Fraud Detection
- [ ] >5 orders/10min from IP → score +40, high risk
- [ ] >3 orders/10min from email → score +40, high risk
- [ ] High-risk orders logged with warning level
- [ ] Fraud scores visible in logs

---

## 📈 Performance Testing

### Benchmark Idempotency Overhead

```bash
# First request (cache write)
time curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Idempotency-Key: perf-test-1" \
  -d '{ /* payload */ }'
# Expected: ~200-220ms

# Duplicate request (cache read)
time curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Idempotency-Key: perf-test-1" \
  -d '{ /* same payload */ }'
# Expected: ~50-70ms (150ms faster!)
```

### Stress Test Rate Limiting

```bash
# Use Apache Bench to simulate load
ab -n 100 -c 10 -H "X-Tenant-ID: test-tenant" -H "Idempotency-Key: stress-$(date +%s)" \
  http://127.0.0.1:8000/api/orders

# Verify rate limits held under concurrent load
```

---

## 📝 Checklist for Production Deployment

### Pre-Deployment
- [ ] All tests passing: `php bin/phpunit`
- [ ] Redis configured and accessible
- [ ] Environment variables set: `REDIS_URL`
- [ ] Cache warmed: `php bin/console cache:warmup`
- [ ] Services registered: `php bin/console debug:container`

### Deployment
- [ ] Deploy code to staging
- [ ] Run smoke tests in staging
- [ ] Monitor logs for errors
- [ ] Verify Redis memory usage acceptable
- [ ] Deploy to production with rollback plan

### Post-Deployment Monitoring (First 24 hours)
- [ ] Monitor idempotency cache hit rate (target >80%)
- [ ] Check for rate limit violations (log analysis)
- [ ] Review fraud scores for false positives
- [ ] Verify Redis memory stable
- [ ] Check application performance (latency)

---

## 🎓 Further Reading

- **Idempotency Best Practices**: [Stripe Idempotency Guide](https://stripe.com/docs/api/idempotent_requests)
- **Rate Limiting**: [RFC 6585 - Additional HTTP Status Codes](https://tools.ietf.org/html/rfc6585)
- **Redis Caching**: [Symfony Cache Component](https://symfony.com/doc/current/components/cache.html)
- **Fraud Detection**: [OWASP Automated Threats](https://owasp.org/www-project-automated-threats-to-web-applications/)

---

**Last Updated**: January 16, 2025
**Maintainer**: Backend Team
**Status**: ✅ Production Ready
