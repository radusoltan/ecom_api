# Sprint Plan: P1 Post-Launch Enhancements & Quality Improvements

**Sprint Goal:** Improve platform quality, security, and customer experience post-launch

**Sprint Duration:** 2 weeks (10 working days)

**Priority:** P1 - Post Go-Live Enhancements

**Start Date:** 2025-12-02 (Monday)

**End Date:** 2025-12-13 (Friday)

**Last Updated:** 2025-11-27

---

## Executive Summary

Following the successful completion of Sprint P0 (Checkout Flow), this sprint focuses on quality improvements, security hardening, and customer experience enhancements identified during the P0 retrospective.

| Epic | Description | Business Value | Effort | Priority |
|------|-------------|----------------|--------|----------|
| Epic 5: Test Coverage Improvement | Increase from ~67% to >=80% | Quality & Reliability | 3 days | HIGH |
| Epic 6: Customer Experience | Guest cart merge, Order history | Conversion & Retention | 3 days | HIGH |
| Epic 7: Security Hardening | Account lockout, Rate limiting | Security Compliance | 2 days | HIGH |
| Epic 8: Customer Communication | Welcome email, Invoice PDF | Engagement & Legal | 2 days | MEDIUM |

**Total Estimated Effort:** 10 days (2 weeks)

---

## Sprint Objectives

### Primary Objectives
1. **Quality Excellence**: Achieve >=80% test coverage (from current ~67%)
2. **Security Compliance**: Implement brute force protection and rate limiting
3. **Customer Experience**: Seamless cart merge on login, order history access
4. **Legal Compliance**: Invoice PDF generation for orders

### Success Criteria
- Test coverage >= 80% global
- Test pass rate >= 95%
- Zero security vulnerabilities in auth endpoints
- Customer satisfaction metrics (NPS) improvement

---

## Epic 5: Test Coverage Improvement (3 days)

### Context
Current test metrics from P0:
- **Total Tests:** 2,099
- **Current Coverage:** ~67%
- **Pass Rate:** ~73.5%
- **Errors:** 443
- **Failures:** 112
- **Target Coverage:** >= 80%

### Existing Assets
- Test infrastructure: PHPUnit 10.x configured
- TenantTestTrait for multi-tenancy testing
- reset_test_db.sh automation script
- Test database with default tenant

### User Stories

---

### US-017: Fix Existing Test Errors

**As a** developer
**I want to** fix existing test errors and failures
**So that** the test suite provides reliable feedback

**Acceptance Criteria:**
- [ ] Reduce test errors from 443 to < 50
- [ ] Reduce test failures from 112 to < 20
- [ ] Test pass rate increases to >= 95%
- [ ] All critical path tests passing (checkout, payment, auth)
- [ ] CI pipeline runs without blocking errors

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Analyze and categorize 443 test errors | Test | 2h | - |
| Fix TenantTestTrait usage in failing tests | Test | 3h | Analysis |
| Fix event subscriber constructor signatures | Test | 2h | Analysis |
| Fix repository integration tests | Test | 3h | Trait fixes |
| Fix functional API tests | Test | 4h | Repository fixes |
| Verify all 16 P0 user stories tests pass | Test | 2h | All fixes |

**Technical Notes:**
```php
// Common fixes required:
// 1. Add TenantTestTrait to integration tests missing it
// 2. Fix constructor injection mismatches in event subscribers
// 3. Update mocked repository method signatures
// 4. Fix RLS context issues in functional tests
```

---

### US-018: Increase Unit Test Coverage

**As a** developer
**I want to** add unit tests for uncovered domain logic
**So that** critical business rules are validated

**Acceptance Criteria:**
- [ ] Domain layer coverage >= 98% (from 96%)
- [ ] Application layer coverage >= 96% (from 94%)
- [ ] All value objects have edge case tests
- [ ] All command handlers have error path tests
- [ ] PHPStan level 8 passes with no exceptions

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Add Cart domain model edge case tests | Domain | 2h | - |
| Add Payment retry policy tests | Domain | 1h | - |
| Add StockReservation expiry tests | Domain | 1h | - |
| Add missing handler error path tests | Application | 3h | Domain tests |
| Add value object validation tests | Domain | 2h | - |
| Run coverage report and verify >= 80% | Test | 1h | All tests |

**Target Test Files:**
```
tests/Unit/Cart/Domain/Model/CartTest.php - Add edge cases
tests/Unit/Payment/Domain/Model/PaymentTest.php - Retry scenarios
tests/Unit/Inventory/Domain/Model/StockReservationTest.php - Expiry
tests/Unit/Cart/Application/Command/CheckoutCartHandlerTest.php - Error paths
```

---

### US-019: Add Integration Test Coverage

**As a** developer
**I want to** add integration tests for repository operations
**So that** database interactions are verified

**Acceptance Criteria:**
- [ ] All repository implementations have integration tests
- [ ] Transaction rollback works correctly per test
- [ ] Multi-tenant isolation verified in tests
- [ ] Gedmo Translatable persistence verified
- [ ] PostgreSQL RLS enforced in test environment

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Add CartRepository integration tests | Integration | 2h | US-017 |
| Add PaymentRepository integration tests | Integration | 2h | US-017 |
| Add StockReservationRepository tests | Integration | 2h | US-017 |
| Verify RLS in all repository tests | Integration | 1h | All repo tests |
| Add concurrent access tests | Integration | 2h | Repo tests |

**Test Pattern:**
```php
final class DoctrineCartRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());
        $this->cleanupTestData();
    }

    public function testSaveAndRetrieveCart(): void
    {
        // Test cart persistence with RLS
    }

    public function testTenantIsolation(): void
    {
        // Verify different tenant cannot access cart
    }
}
```

---

## Epic 6: Customer Experience (3 days)

### Context
Customer experience improvements identified in P0:
- Guest carts lost on login (no merge)
- No order history API for customers
- Cart abandonment tracking incomplete

### User Stories

---

### US-020: Guest Cart Merge on Login

**As a** customer with items in my guest cart
**I want** my cart items to merge when I login
**So that** I don't lose items I added before logging in

**Acceptance Criteria:**
- [ ] Guest cart items merge with existing user cart on login
- [ ] Duplicate products have quantities combined
- [ ] Guest cart marked as merged (not deleted for audit)
- [ ] Works with JWT authentication flow
- [ ] Emits `CartsMerged` domain event
- [ ] Session ID cleared after successful merge

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `MergeCarts` command | Application | 1h | - |
| Create `MergeCartsHandler` | Application | 2h | Command |
| Create `CartsMerged` domain event | Domain | 0.5h | - |
| Create `LoginSuccessCartMergeSubscriber` | Application | 2h | Handler |
| Update JWT success handler to trigger merge | Infrastructure | 1h | Subscriber |
| Unit tests for merge logic | Test | 2h | Handler |
| Functional tests for login + merge | Test | 2h | Full flow |

**API Behavior (Automatic on Login):**
```yaml
# No new endpoint - automatic on successful login
POST /api/login_check
Request Body:
{
  "email": "user@example.com",
  "password": "password123"
}
Headers:
  X-Session-ID: "guest_session_123"  # Guest cart identifier

Response: 200 OK
{
  "token": "eyJ...",
  "refreshToken": "...",
  "cartMerged": true,  # NEW field
  "cartItemCount": 5    # Combined count
}
```

**Domain Event:**
```php
final readonly class CartsMerged implements DomainEventInterface
{
    public function __construct(
        public CartId $targetCartId,
        public CartId $sourceCartId,
        public CustomerId $customerId,
        public int $itemsMerged,
        public DateTimeImmutable $mergedAt,
    ) {}
}
```

**Business Rules:**
```yaml
cart_merge:
  duplicate_handling: "combine_quantities"
  max_combined_quantity: 999
  guest_cart_disposition: "mark_merged"  # Not deleted
  audit_retention: "90 days"
```

---

### US-021: Order History API

**As a** registered customer
**I want to** view my order history
**So that** I can track past purchases and reorder

**Acceptance Criteria:**
- [ ] Returns paginated list of customer's orders
- [ ] Includes order status, total, date, item count
- [ ] Supports filtering by status (pending, completed, cancelled)
- [ ] Supports date range filtering
- [ ] Supports sorting (newest first, oldest first)
- [ ] Returns detailed order view with line items
- [ ] Multi-tenant isolation enforced

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `GetOrderHistory` query | Application | 1h | - |
| Create `GetOrderHistoryHandler` | Application | 2h | Query |
| Create `OrderHistoryResource` API Platform resource | Presentation | 2h | Handler |
| Create `GetOrderHistoryProvider` state provider | Presentation | 2h | Handler |
| Add pagination support | Presentation | 1h | Provider |
| Add filtering and sorting | Presentation | 2h | Provider |
| Functional tests for order history API | Test | 2h | Full flow |

**API Specification:**
```yaml
GET /api/v1/customers/me/orders
Headers:
  Authorization: Bearer {jwt_token}
  X-Tenant-ID: required
Query Parameters:
  page: 1 (default)
  itemsPerPage: 20 (default, max 100)
  status: pending|processing|shipped|delivered|cancelled (optional)
  startDate: 2025-01-01 (optional, ISO 8601)
  endDate: 2025-12-31 (optional, ISO 8601)
  order[createdAt]: asc|desc (default: desc)

Response: 200 OK
{
  "hydra:member": [
    {
      "@id": "/api/v1/orders/01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
      "orderNumber": "ORD-2025-000001",
      "status": "delivered",
      "total": { "amount": "149.99", "currency": "USD" },
      "itemCount": 3,
      "createdAt": "2025-11-25T10:00:00Z",
      "shippedAt": "2025-11-26T14:00:00Z",
      "deliveredAt": "2025-11-28T09:00:00Z"
    }
  ],
  "hydra:totalItems": 15,
  "hydra:view": {
    "@id": "/api/v1/customers/me/orders?page=1",
    "hydra:first": "/api/v1/customers/me/orders?page=1",
    "hydra:last": "/api/v1/customers/me/orders?page=2",
    "hydra:next": "/api/v1/customers/me/orders?page=2"
  }
}

GET /api/v1/customers/me/orders/{orderId}
Response: 200 OK
{
  "@id": "/api/v1/orders/01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "orderNumber": "ORD-2025-000001",
  "status": "delivered",
  "total": { "amount": "149.99", "currency": "USD" },
  "subtotal": { "amount": "139.99", "currency": "USD" },
  "tax": { "amount": "10.00", "currency": "USD" },
  "shippingCost": { "amount": "0.00", "currency": "USD" },
  "items": [
    {
      "productId": "...",
      "productName": "Wireless Headphones",
      "variantName": "Black",
      "quantity": 1,
      "unitPrice": { "amount": "99.99", "currency": "USD" },
      "rowTotal": { "amount": "99.99", "currency": "USD" }
    }
  ],
  "shippingAddress": {
    "firstName": "John",
    "lastName": "Doe",
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US"
  },
  "paymentMethod": "Stripe",
  "createdAt": "2025-11-25T10:00:00Z",
  "updatedAt": "2025-11-28T09:00:00Z"
}
```

**Files to Create:**
```
src/Order/
├── Application/
│   └── Query/
│       ├── GetOrderHistory.php
│       └── GetOrderHistoryHandler.php
├── Presentation/
│   └── Api/
│       ├── Resource/OrderHistoryResource.php
│       └── Provider/GetOrderHistoryProvider.php
```

---

### US-022: Reorder Functionality

**As a** customer viewing my order history
**I want to** quickly reorder items from a previous order
**So that** I can easily repurchase products I liked

**Acceptance Criteria:**
- [ ] One-click add all items from previous order to cart
- [ ] Handles out-of-stock items gracefully (skip with notification)
- [ ] Handles discontinued products (skip with notification)
- [ ] Handles price changes (add at current price with notification)
- [ ] Returns summary of what was added vs. skipped

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `ReorderItems` command | Application | 1h | US-021 |
| Create `ReorderItemsHandler` | Application | 3h | Command |
| Implement stock availability check | Application | 1h | Handler |
| Implement price change detection | Application | 1h | Handler |
| Create `POST /api/v1/orders/{id}/reorder` endpoint | Presentation | 1h | Handler |
| Functional tests | Test | 2h | Full flow |

**API Specification:**
```yaml
POST /api/v1/orders/{orderId}/reorder
Headers:
  Authorization: Bearer {jwt_token}
  X-Tenant-ID: required
Request Body: {} (empty)

Response: 200 OK
{
  "cartId": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "addedItems": [
    {
      "productId": "...",
      "productName": "Wireless Headphones",
      "quantity": 1,
      "currentPrice": { "amount": "99.99", "currency": "USD" },
      "originalPrice": { "amount": "89.99", "currency": "USD" },
      "priceChanged": true
    }
  ],
  "skippedItems": [
    {
      "productId": "...",
      "productName": "USB Cable",
      "reason": "out_of_stock",
      "availableQuantity": 0
    },
    {
      "productId": "...",
      "productName": "Phone Case",
      "reason": "discontinued"
    }
  ],
  "summary": {
    "totalAdded": 2,
    "totalSkipped": 2,
    "cartTotal": { "amount": "199.98", "currency": "USD" }
  }
}
```

---

## Epic 7: Security Hardening (2 days)

### Context
Security gaps identified in P0:
- No account lockout after failed login attempts
- No rate limiting on authentication endpoints
- Brute force attacks possible

### User Stories

---

### US-023: Account Lockout (Brute Force Protection)

**As a** platform operator
**I want** accounts to be locked after multiple failed login attempts
**So that** brute force attacks are prevented

**Acceptance Criteria:**
- [ ] Account locks after 5 consecutive failed attempts
- [ ] Lockout duration: 15 minutes (configurable)
- [ ] Lockout counter resets on successful login
- [ ] Admin can manually unlock accounts
- [ ] Logs lockout events for security audit
- [ ] Email notification sent to user on lockout
- [ ] Returns generic error (no information leakage)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Add `failedLoginAttempts`, `lockedUntil` to UserEntity | Infrastructure | 1h | Migration |
| Create `IncrementFailedAttempts` command | Application | 0.5h | - |
| Create `LockAccount` command | Application | 0.5h | - |
| Create `UnlockAccount` command | Application | 0.5h | - |
| Create handlers for all commands | Application | 2h | Commands |
| Create `LoginFailedSubscriber` | Application | 1h | Handlers |
| Create `AccountLockedSubscriber` (email) | Application | 1h | Handler |
| Update `AuthenticatorChecker` to check lockout | Infrastructure | 1h | Entity |
| Admin endpoint: `POST /api/v1/admin/users/{id}/unlock` | Presentation | 1h | Handler |
| Unit tests for lockout logic | Test | 2h | Handlers |
| Functional tests for lockout flow | Test | 2h | Full flow |

**Database Migration:**
```sql
ALTER TABLE users ADD COLUMN failed_login_attempts INT DEFAULT 0;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN last_failed_login_at TIMESTAMP NULL;

CREATE INDEX idx_users_locked_until ON users(locked_until) WHERE locked_until IS NOT NULL;
```

**Configuration:**
```yaml
# config/packages/security.yaml
parameters:
  app.security.max_failed_attempts: 5
  app.security.lockout_duration: 900  # 15 minutes in seconds
```

**Domain Model Updates:**
```php
// User aggregate methods
public function recordFailedLoginAttempt(): void
{
    $this->failedLoginAttempts++;
    $this->lastFailedLoginAt = new DateTimeImmutable();

    if ($this->failedLoginAttempts >= self::MAX_FAILED_ATTEMPTS) {
        $this->lockUntil(
            (new DateTimeImmutable())->modify('+15 minutes')
        );
        $this->recordEvent(new AccountLocked($this->id, $this->lockedUntil));
    }
}

public function isLocked(): bool
{
    return $this->lockedUntil !== null
        && $this->lockedUntil > new DateTimeImmutable();
}

public function unlock(): void
{
    $this->lockedUntil = null;
    $this->failedLoginAttempts = 0;
    $this->recordEvent(new AccountUnlocked($this->id));
}
```

**Error Response (Generic):**
```yaml
POST /api/login_check
Response: 401 Unauthorized
{
  "error": "invalid_credentials",
  "message": "Invalid email or password"  # Same message for locked or wrong password
}

# For locked account (same response, but log indicates lockout)
```

---

### US-024: Rate Limiting on Auth Endpoints

**As a** platform operator
**I want** authentication endpoints to be rate limited
**So that** automated attacks are mitigated

**Acceptance Criteria:**
- [ ] Login endpoint: 10 requests per minute per IP
- [ ] Register endpoint: 5 requests per minute per IP
- [ ] Password reset: 3 requests per minute per IP
- [ ] Returns 429 Too Many Requests when exceeded
- [ ] Includes `Retry-After` header
- [ ] Rate limits stored in Redis for distributed deployments
- [ ] Configurable limits per endpoint

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Install `symfony/rate-limiter` | Infrastructure | 0.5h | - |
| Configure rate limiters in `rate_limiter.yaml` | Infrastructure | 1h | Package |
| Create `RateLimiterSubscriber` | Infrastructure | 2h | Config |
| Apply rate limiter to login endpoint | Presentation | 0.5h | Subscriber |
| Apply rate limiter to register endpoint | Presentation | 0.5h | Subscriber |
| Apply rate limiter to password reset | Presentation | 0.5h | Subscriber |
| Configure Redis storage for rate limits | Infrastructure | 1h | Redis |
| Functional tests for rate limiting | Test | 2h | Full flow |

**Configuration:**
```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        login_limiter:
            policy: 'sliding_window'
            limit: 10
            interval: '1 minute'
            lock_factory: 'lock.factory'
            cache_pool: 'cache.rate_limiter'

        register_limiter:
            policy: 'sliding_window'
            limit: 5
            interval: '1 minute'
            lock_factory: 'lock.factory'
            cache_pool: 'cache.rate_limiter'

        password_reset_limiter:
            policy: 'sliding_window'
            limit: 3
            interval: '1 minute'
            lock_factory: 'lock.factory'
            cache_pool: 'cache.rate_limiter'
```

**Subscriber Implementation:**
```php
#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
final class RateLimiterSubscriber
{
    public function __construct(
        private readonly RateLimiterFactory $loginLimiter,
        private readonly RateLimiterFactory $registerLimiter,
        private readonly RateLimiterFactory $passwordResetLimiter,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $ip = $request->getClientIp();

        $limiter = match($path) {
            '/api/login_check' => $this->loginLimiter,
            '/api/v1/auth/register' => $this->registerLimiter,
            '/api/v1/auth/password/reset-request' => $this->passwordResetLimiter,
            default => null,
        };

        if ($limiter === null) {
            return;
        }

        $limit = $limiter->create($ip)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - time(),
                'Too many requests. Please try again later.'
            );
        }
    }
}
```

**API Response (Rate Limited):**
```yaml
POST /api/login_check
Response: 429 Too Many Requests
Headers:
  Retry-After: 45  # seconds until reset
{
  "error": "rate_limit_exceeded",
  "message": "Too many requests. Please try again later.",
  "retryAfter": 45
}
```

---

## Epic 8: Customer Communication (2 days)

### Context
Communication improvements for customer engagement and legal compliance.

### User Stories

---

### US-025: Welcome Email on Registration

**As a** newly registered customer
**I want to** receive a welcome email
**So that** I know my account was created successfully

**Acceptance Criteria:**
- [ ] Welcome email sent immediately after registration
- [ ] Includes customer's name and username
- [ ] Includes link to complete profile
- [ ] Includes link to start shopping
- [ ] Professional HTML template with text fallback
- [ ] Sent asynchronously (does not block registration)
- [ ] Multi-tenant: uses tenant branding

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `SendWelcomeEmail` command | Application | 0.5h | - |
| Create `SendWelcomeEmailHandler` | Application | 1h | Command |
| Create `UserRegisteredSubscriber` | Application | 1h | Handler |
| Create welcome email HTML template | Infrastructure | 1h | Template |
| Create welcome email text template | Infrastructure | 0.5h | Template |
| Add translation keys for email content | Infrastructure | 0.5h | - |
| Unit tests for subscriber | Test | 1h | Subscriber |
| Integration test for email sending | Test | 1h | Full flow |

**Email Template:**
```twig
{# templates/email/welcome.html.twig #}
{% extends '@email/base.html.twig' %}

{% block subject %}Welcome to {{ tenant_name }}, {{ user.firstName }}!{% endblock %}

{% block content %}
<h1>Welcome, {{ user.firstName }}!</h1>

<p>Thank you for creating an account with {{ tenant_name }}.</p>

<p>Your account details:</p>
<ul>
    <li><strong>Email:</strong> {{ user.email }}</li>
    <li><strong>Username:</strong> {{ user.username }}</li>
</ul>

<p>
    <a href="{{ complete_profile_url }}" class="button">Complete Your Profile</a>
</p>

<p>
    <a href="{{ shop_url }}" class="button-secondary">Start Shopping</a>
</p>

<p>If you have any questions, please contact our support team.</p>

<p>Best regards,<br>The {{ tenant_name }} Team</p>
{% endblock %}
```

**Domain Event:**
```php
final readonly class UserRegistered implements DomainEventInterface
{
    public function __construct(
        public UserId $userId,
        public TenantId $tenantId,
        public Email $email,
        public Username $username,
        public ?string $firstName,
        public DateTimeImmutable $registeredAt,
    ) {}
}
```

---

### US-026: Invoice PDF Generation

**As a** customer
**I want to** download invoices for my orders as PDF
**So that** I have documentation for accounting/tax purposes

**Acceptance Criteria:**
- [ ] PDF generated on demand for completed orders
- [ ] Includes all legal requirements (order number, date, VAT, etc.)
- [ ] Includes seller information (tenant details)
- [ ] Includes buyer information
- [ ] Includes itemized list with prices
- [ ] Includes payment method and totals
- [ ] Multi-tenant: uses tenant branding/logo
- [ ] Cached after first generation
- [ ] Available via authenticated API endpoint

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Install `dompdf/dompdf` or `knplabs/knp-snappy-bundle` | Infrastructure | 0.5h | - |
| Create `GenerateInvoicePdf` command | Application | 1h | - |
| Create `GenerateInvoicePdfHandler` | Application | 2h | Command |
| Create `InvoicePdfGenerator` service | Infrastructure | 3h | Handler |
| Create invoice PDF template (Twig) | Infrastructure | 2h | Service |
| Create `GET /api/v1/orders/{id}/invoice` endpoint | Presentation | 1h | Handler |
| Add invoice number generation (sequential per tenant) | Domain | 1h | - |
| Unit tests for invoice generation | Test | 1h | Handler |
| Functional tests for PDF download | Test | 1h | Full flow |

**API Specification:**
```yaml
GET /api/v1/orders/{orderId}/invoice
Headers:
  Authorization: Bearer {jwt_token}
  X-Tenant-ID: required
  Accept: application/pdf

Response: 200 OK
Headers:
  Content-Type: application/pdf
  Content-Disposition: attachment; filename="invoice-INV-2025-000001.pdf"
Body: [Binary PDF content]

# Alternative: JSON metadata
GET /api/v1/orders/{orderId}/invoice?format=json
Response: 200 OK
{
  "invoiceNumber": "INV-2025-000001",
  "orderNumber": "ORD-2025-000001",
  "pdfUrl": "/api/v1/orders/{orderId}/invoice",
  "generatedAt": "2025-11-28T10:00:00Z"
}
```

**Invoice Template Structure:**
```twig
{# templates/pdf/invoice.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Professional invoice styling */
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { border-bottom: 2px solid #333; margin-bottom: 20px; }
        .logo { max-height: 60px; }
        .invoice-details { float: right; }
        .addresses { display: flex; justify-content: space-between; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; }
        .totals { float: right; width: 300px; }
        .footer { margin-top: 40px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ tenant.logo }}" class="logo" alt="{{ tenant.name }}">
        <div class="invoice-details">
            <h1>INVOICE</h1>
            <p><strong>Invoice #:</strong> {{ invoice.number }}</p>
            <p><strong>Date:</strong> {{ invoice.date|date('Y-m-d') }}</p>
            <p><strong>Order #:</strong> {{ order.orderNumber }}</p>
        </div>
    </div>

    <div class="addresses">
        <div class="seller">
            <h3>From:</h3>
            <p>{{ tenant.name }}<br>
            {{ tenant.address.street }}<br>
            {{ tenant.address.city }}, {{ tenant.address.postalCode }}<br>
            VAT: {{ tenant.vatNumber }}</p>
        </div>
        <div class="buyer">
            <h3>Bill To:</h3>
            <p>{{ order.billingAddress.firstName }} {{ order.billingAddress.lastName }}<br>
            {{ order.billingAddress.street }}<br>
            {{ order.billingAddress.city }}, {{ order.billingAddress.postalCode }}</p>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            {% for item in order.items %}
            <tr>
                <td>{{ item.productName }}{% if item.variantName %} - {{ item.variantName }}{% endif %}</td>
                <td>{{ item.quantity }}</td>
                <td>{{ item.unitPrice|money }}</td>
                <td>{{ item.rowTotal|money }}</td>
            </tr>
            {% endfor %}
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>Subtotal:</td><td>{{ order.subtotal|money }}</td></tr>
            <tr><td>Tax ({{ order.taxRate }}%):</td><td>{{ order.tax|money }}</td></tr>
            <tr><td>Shipping:</td><td>{{ order.shippingCost|money }}</td></tr>
            <tr><td><strong>Total:</strong></td><td><strong>{{ order.total|money }}</strong></td></tr>
        </table>
        <p>Payment Method: {{ order.paymentMethod }}</p>
        <p>Payment Status: {{ order.paymentStatus }}</p>
    </div>

    <div class="footer">
        <p>{{ tenant.name }} | {{ tenant.email }} | {{ tenant.phone }}</p>
        <p>{{ tenant.legalNotice }}</p>
    </div>
</body>
</html>
```

**Files to Create:**
```
src/Order/
├── Application/
│   ├── Command/
│   │   ├── GenerateInvoicePdf.php
│   │   └── GenerateInvoicePdfHandler.php
│   └── Service/
│       └── InvoicePdfGenerator.php
├── Domain/
│   └── ValueObject/
│       └── InvoiceNumber.php
├── Infrastructure/
│   └── Pdf/
│       └── DompdfInvoiceGenerator.php
└── Presentation/
    └── Api/
        └── Controller/
            └── InvoiceController.php

templates/
└── pdf/
    └── invoice.html.twig
```

---

## Dependencies & Order of Implementation

```
Week 1: Quality & Security (Days 1-5)
├── Day 1-2: Test Fixes (US-017)
│   └── Analyze errors, fix TenantTestTrait issues
├── Day 3: Unit Test Coverage (US-018)
│   └── Add domain/application tests
├── Day 4: Account Lockout (US-023)
│   └── Implement brute force protection
└── Day 5: Rate Limiting (US-024)
    └── Configure and apply rate limiters

Week 2: Experience & Communication (Days 6-10)
├── Day 6: Integration Tests (US-019)
│   └── Repository integration coverage
├── Day 7: Guest Cart Merge (US-020)
│   └── Login cart merge flow
├── Day 8: Order History API (US-021)
│   └── Customer order listing
├── Day 9: Welcome Email (US-025)
│   └── Registration email notification
└── Day 10: Invoice PDF (US-026) + Buffer
    └── PDF generation and cleanup
```

---

## Technical Specifications

### Database Migrations Required

```sql
-- Migration 1: User account lockout fields
ALTER TABLE users ADD COLUMN failed_login_attempts INT DEFAULT 0;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN last_failed_login_at TIMESTAMP NULL;
CREATE INDEX idx_users_locked_until ON users(locked_until) WHERE locked_until IS NOT NULL;

-- Migration 2: Cart merge tracking
ALTER TABLE carts ADD COLUMN merged_into_cart_id VARCHAR(36) NULL;
ALTER TABLE carts ADD COLUMN merged_at TIMESTAMP NULL;
CREATE INDEX idx_carts_merged ON carts(merged_into_cart_id) WHERE merged_into_cart_id IS NOT NULL;

-- Migration 3: Invoice number sequence per tenant
CREATE TABLE invoice_sequences (
    tenant_id VARCHAR(36) PRIMARY KEY,
    current_number INT DEFAULT 0,
    prefix VARCHAR(20) DEFAULT 'INV',
    last_updated TIMESTAMP NOT NULL
);
```

### Configuration Updates

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        login_limiter:
            policy: 'sliding_window'
            limit: 10
            interval: '1 minute'
        register_limiter:
            policy: 'sliding_window'
            limit: 5
            interval: '1 minute'
        password_reset_limiter:
            policy: 'sliding_window'
            limit: 3
            interval: '1 minute'

# config/services.yaml
parameters:
    app.security.max_failed_attempts: 5
    app.security.lockout_duration: 900
```

### New Files to Create

```
src/
├── User/
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── IncrementFailedAttempts.php
│   │   │   ├── IncrementFailedAttemptsHandler.php
│   │   │   ├── LockAccount.php
│   │   │   ├── LockAccountHandler.php
│   │   │   ├── UnlockAccount.php
│   │   │   ├── UnlockAccountHandler.php
│   │   │   ├── SendWelcomeEmail.php
│   │   │   └── SendWelcomeEmailHandler.php
│   │   └── EventSubscriber/
│   │       ├── LoginFailedSubscriber.php
│   │       ├── AccountLockedSubscriber.php
│   │       └── UserRegisteredSubscriber.php
│   └── Infrastructure/
│       └── Security/
│           └── RateLimiterSubscriber.php
├── Cart/
│   └── Application/
│       ├── Command/
│       │   ├── MergeCarts.php
│       │   └── MergeCartsHandler.php
│       └── EventSubscriber/
│           └── LoginSuccessCartMergeSubscriber.php
├── Order/
│   ├── Application/
│   │   ├── Query/
│   │   │   ├── GetOrderHistory.php
│   │   │   └── GetOrderHistoryHandler.php
│   │   ├── Command/
│   │   │   ├── GenerateInvoicePdf.php
│   │   │   ├── GenerateInvoicePdfHandler.php
│   │   │   ├── ReorderItems.php
│   │   │   └── ReorderItemsHandler.php
│   │   └── Service/
│   │       └── InvoicePdfGenerator.php
│   ├── Domain/
│   │   └── ValueObject/
│   │       └── InvoiceNumber.php
│   └── Presentation/
│       └── Api/
│           ├── Resource/
│           │   └── OrderHistoryResource.php
│           ├── Provider/
│           │   └── GetOrderHistoryProvider.php
│           └── Controller/
│               └── InvoiceController.php
└── templates/
    ├── email/
    │   ├── welcome.html.twig
    │   └── welcome.txt.twig
    └── pdf/
        └── invoice.html.twig
```

---

## Definition of Done

For each user story to be considered complete:

### Code Quality
- [ ] All new code has PHPDoc comments
- [ ] PHPStan level 8 passes
- [ ] PHP-CS-Fixer passes
- [ ] Deptrac validation passes
- [ ] No TODO comments (technical debt documented separately)

### Testing
- [ ] Unit tests for domain logic (>=90% coverage for new code)
- [ ] Integration tests for repositories
- [ ] Functional tests for API endpoints
- [ ] All tests pass in CI
- [ ] Overall test coverage >= 80%

### Documentation
- [ ] API documented in OpenAPI spec
- [ ] CLAUDE.md updated if new patterns introduced
- [ ] README updated if new setup steps required

### Security
- [ ] Input validation on all endpoints
- [ ] Authorization checks (tenant isolation)
- [ ] Rate limiting applied where specified
- [ ] Sensitive data not logged
- [ ] Security audit passed

---

## Risk Assessment

### High Risk

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Test fixes break existing functionality | Sprint delay | Medium | Run full test suite after each fix batch |
| Rate limiter Redis dependency | Service degradation | Low | Fallback to in-memory limiter |
| PDF generation performance | Slow API response | Medium | Cache generated PDFs, async generation option |

### Medium Risk

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Cart merge logic complexity | Data inconsistency | Medium | Comprehensive unit tests, transaction wrapping |
| Account lockout false positives | Customer frustration | Low | Clear unlock process, reasonable limits |
| Email deliverability | Welcome emails not received | Medium | Use reliable SMTP, implement retry |

### Low Risk

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Invoice template changes | Minor rework | Low | Get design approval upfront |
| Order history performance | Slow API | Low | Pagination, indexed queries |

---

## Success Metrics

### Sprint Success Criteria

| Metric | Target | Measurement |
|--------|--------|-------------|
| User stories completed | 10/10 | Story points done |
| Test coverage | >= 80% | PHPUnit coverage report |
| Test pass rate | >= 95% | PHPUnit results |
| API response time | < 200ms p95 | API monitoring |
| Zero critical bugs | 0 | QA testing |
| Security audit pass | 100% | Penetration test results |

### Business Metrics (Post-Sprint)

| Metric | Baseline | Target | Improvement |
|--------|----------|--------|-------------|
| Cart abandonment rate | ~70% | ~60% | -10% (cart merge) |
| Login security incidents | Unknown | 0 | Lockout + rate limit |
| Invoice download rate | 0% | 20% | +20% (new feature) |
| Customer reorders | 0% | 5% | +5% (new feature) |

---

## Sprint Ceremonies

### Sprint Planning
- **Date:** 2025-12-02 (Monday)
- **Duration:** 2 hours
- **Agenda:** Review stories, assign tasks, confirm estimates

### Daily Standups
- **Time:** 09:30 daily
- **Duration:** 15 minutes
- **Format:** What did you do? What will you do? Any blockers?

### Mid-Sprint Review
- **Date:** 2025-12-06 (Friday)
- **Duration:** 1 hour
- **Agenda:** Progress check, adjust if needed

### Sprint Review
- **Date:** 2025-12-13 (Friday)
- **Duration:** 1 hour
- **Agenda:** Demo completed features to stakeholders

### Sprint Retrospective
- **Date:** 2025-12-13 (Friday)
- **Duration:** 1 hour
- **Agenda:** What went well? What to improve? Actions?

---

## Appendix A: Test Coverage Targets by Layer

| Layer | Current | Target | Gap | Priority |
|-------|---------|--------|-----|----------|
| Domain | 96% | 98% | 2% | Medium |
| Application | 94% | 96% | 2% | Medium |
| Infrastructure | 65% | 75% | 10% | High |
| Presentation | 87% | 90% | 3% | Medium |
| **Overall** | **67%** | **80%** | **13%** | **HIGH** |

## Appendix B: Error Categories from P0

| Error Type | Count | Fix Strategy |
|------------|-------|--------------|
| TenantTestTrait missing | ~150 | Add trait to test classes |
| Constructor signature mismatch | ~100 | Update mock configurations |
| Repository method not found | ~80 | Update repository interfaces |
| RLS violation | ~60 | Add setTenantContext() calls |
| Assertion failures | ~50 | Fix expected values |
| Other | ~3 | Individual analysis |

## Appendix C: Related Documentation

- Sprint P0 Checkout Flow: `docs/sprints/SPRINT_P0_CHECKOUT_FLOW.md`
- Sprint P0 Status Report: `docs/sprints/SPRINT_P0_STATUS_REPORT.md`
- Sprint P0 Metrics: `docs/sprints/SPRINT_P0_METRICS.md`
- Testing Guide: `docs/guides/testing-guide.md`
- Security Configuration: `config/packages/security.yaml`
- Rate Limiter Docs: Symfony Rate Limiter Component

---

**Document Version:** 1.0

**Last Updated:** 2025-11-27

**Author:** Product Strategy (Claude Code)

**Status:** Ready for Sprint Planning

**Next Review:** Sprint Planning Meeting (2025-12-02)
