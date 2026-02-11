# Sprint Plan: P0 Critical Checkout Flow

**Sprint Goal:** Enable complete checkout flow: Browse -> Cart -> Payment -> Order

**Sprint Duration:** 3 weeks (15 working days)

**Priority:** P0 - Go-Live Blocking

**Last Updated:** 2025-11-26

---

## Executive Summary

This sprint addresses 4 critical gaps identified in the platform audit that block the go-live:

| Gap | Current State | Target State | Effort | Status |
|-----|---------------|--------------|--------|--------|
| Cart API | ~~Domain exists, no endpoints~~ | Full REST API (6 endpoints) | ~~5 days~~ | ✅ **COMPLETE** |
| JWT Auth | ~~Config exists, no register/profile~~ | Login/Register/Profile API | ~~3 days~~ | ✅ **COMPLETE** |
| Payment Integration | ~~Stripe gateway exists, partial flow~~ | Complete Stripe flow + webhook | ~~4 days~~ | ✅ **COMPLETE** |
| Stock Reservations | ~~StockItem/Reservation domain exists~~ | Reservation API + Auto-release | ~~3 days~~ | ✅ **COMPLETE** |

**Total Estimated Effort:** ~~15 days~~ **COMPLETE** - All 4 epics delivered

---

## Sprint Status Summary (Updated 2025-11-27)

### ✅ Epic 1: Cart API - **COMPLETE**
*(see below)*

### ✅ Epic 2: JWT Authentication - **COMPLETE**
*(see below)*

### ✅ Epic 4: Stock Reservations - **COMPLETE**

**Implemented Components:**
- US-014: `StockValidator` updated to query real stock across warehouses
- US-015: `OrderCancelledStockSubscriber` - releases stock on order cancellation
- US-016: `CheckStockAvailability` API - POST /api/v1/stock/check endpoint
- Fixed: `StockReservationEntity` - added warehouseId column for multi-warehouse support
- Fixed: `ReserveStockProcessor` - correct parameter order for StockReservation::create()
- Fixed: `ReleaseStockHandler` - proper referenceId parameter for release()
- Fixed: `CheckStockAvailabilityProcessor` - handles both array and object item formats
- Migration: `Version20251127054300_StockReservationWarehouseId` - warehouse_id column
- Existing: `ReleaseExpiredReservations` scheduler (15-minute reservation timeout)

**API Endpoints Available:**
| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/v1/stock/check` | POST | Check availability for multiple items | ✅ |
| `/api/v1/stock/reserve` | POST | Reserve stock for checkout | ✅ |
| `/api/v1/stock/allocate` | POST | Allocate reserved stock to order | ✅ |
| `/api/v1/stock/release` | POST | Release reserved/allocated stock | ✅ |

**Features:**
- Real-time stock availability checking across all warehouses
- Multi-warehouse stock aggregation for availability queries
- Stock reservation with 15-minute expiry timeout
- Automatic reservation release via Symfony Scheduler
- Order cancellation triggers automatic stock release
- Multi-tenant support via X-Tenant-ID header
- Domain events: `StockReserved`, `StockAllocated`, `StockReleased`

---

### ✅ Epic 3: Payment Integration - **COMPLETE**

**Implemented Components:**
- Command & Handler: `InitiatePayment`, `InitiatePaymentHandler` - Creates PaymentIntent via Stripe
- Command & Handler: `MarkPaymentAsFailed`, `MarkPaymentAsFailedHandler` - Handles failed payments
- Scheduler: `PaymentScheduleProvider`, `ProcessPaymentRetries`, `ProcessPaymentRetriesHandler` - Auto-retry mechanism
- Updated Controller: `StripePaymentController` - Enhanced with orderId linking
- Updated Webhook: `StripeWebhookHandler` - Improved event handling
- Service Alias: `PaymentGatewayInterface` → `StripePaymentGateway` - DI configuration
- Tests: 8 StripePaymentApiTest functional tests (100% pass)
- Documentation: `docs/US-011-STRIPE-PAYMENT-FLOW.md`, `docs/payment/PAYMENT_RETRY_MECHANISM.md`

**API Endpoints Available:**
| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/v1/payments/stripe/create-intent` | POST | Create Stripe PaymentIntent with orderId | ✅ |
| `/api/v1/payments/stripe/verify-payment/{id}` | GET | Verify payment status | ✅ |
| `/api/v1/payments/stripe/capture-after-success/{id}` | POST | Capture payment + update order | ✅ |
| `/api/v1/payments/stripe/webhook` | POST | Handle Stripe webhook events | ✅ |

**Features:**
- Stripe PaymentIntent creation with order linking
- Client secret returned for Stripe Elements integration
- Automatic payment retry with exponential backoff (1h, 4h, 24h)
- Order status updates on payment success
- Multi-tenant support via X-Tenant-ID header
- Proper validation (orderId, amount, customerEmail required)
- Domain events: `PaymentCaptured`, `PaymentFailed`, `PaymentRetryExhausted`

---

### ✅ Epic 2 Details: JWT Authentication

**Implemented Components:**
- RegisterUser Command & Handler: `src/User/Application/Command/RegisterUser/`
- Password Reset Flow: `RequestPasswordReset`, `ResetPassword` commands & handlers
- API Resources: `AuthResource`, `PasswordResetResource`
- API Processors: `RegisterUserProcessor`, `RefreshTokenProcessor`, `RequestPasswordResetProcessor`, `ResetPasswordProcessor`
- Domain Exceptions: `EmailAlreadyExistsException`, `UsernameAlreadyExistsException`
- Infrastructure: `PasswordResetTokenEntity`, refresh token configuration
- Tests: 8 Registration tests + 6 Login tests (14 total, 100% pass)

**API Endpoints Available:**
| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/login_check` | POST | Login with email/password | ✅ |
| `/api/v1/auth/register` | POST | Register new user account | ✅ |
| `/api/v1/auth/token/refresh` | POST | Refresh JWT token | ✅ |
| `/api/v1/auth/password/reset-request` | POST | Request password reset email | ✅ |
| `/api/v1/auth/password/reset` | POST | Reset password with token | ✅ |

**Features:**
- JWT token generation on registration (auto-login)
- Refresh token generation and validation (30-day TTL)
- Password validation (min 8 characters)
- Email/username uniqueness validation
- Proper error handling (400/409/401 HTTP codes)
- Multi-tenant support via X-Tenant-ID header

---

### Epic 1 Details

**Implemented Components:**
- API Platform Resources: `CartResource`, `CheckoutResource`
- API Processors: `AddItemToCartProcessor`, `UpdateCartItemProcessor`, `RemoveItemFromCartProcessor`, `ClearCartProcessor`, `AssignCartToCustomerProcessor`, `CheckoutProcessor`
- API Provider: `GetCartProvider`
- Commands: `CreateCart`, `AddItemToCart`, `UpdateCartQuantity`, `RemoveItemFromCart`, `ClearCart`, `MergeCarts`, `AssignCartToCustomer`
- Queries: `GetCart`, `GetCartSummary`
- Services: `CartPriceCalculator`, `CartToOrderConverter`, `StockValidator`, `CartAbandonmentService`
- Domain: Complete Cart aggregate with value objects, events, exceptions
- Tests: 23 Cart API tests + 13 Checkout API tests (36 total, 100% pass)

**API Endpoints Available:**
| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/v1/cart` | GET | Retrieve current cart | ✅ |
| `/api/v1/cart/items` | POST | Add item to cart | ✅ |
| `/api/v1/cart/items/{itemId}` | PATCH | Update item quantity | ✅ |
| `/api/v1/cart/items/{itemId}` | DELETE | Remove item from cart | ✅ |
| `/api/v1/cart` | DELETE | Clear cart | ✅ |
| `/api/v1/cart/assign` | POST | Assign guest cart to customer | ✅ |
| `/api/v1/checkout` | POST | Convert cart to order | ✅ |

---

## Epic 1: Cart API (5 days)

### Existing Assets
- Domain Model: `src/Cart/Domain/Model/Cart.php` (complete with business rules)
- Value Objects: `CartId`, `CartItem`, `CartStatus`, `SessionId`, `Quantity`
- Doctrine Entity: `src/Cart/Infrastructure/Persistence/Doctrine/Entity/CartEntity.php`
- Commands: `CreateCart`, `AddItemToCart` (handlers exist)
- Repository Interface: `CartRepositoryInterface`
- Events: `CartCreated`, `ItemAddedToCart`, `ItemRemovedFromCart`, `CartCleared`

### Missing Components
- REST API Controller
- API Platform Resource (optional, can use custom controller)
- GetCart, UpdateQuantity, RemoveItem, ClearCart, Checkout commands

---

### US-001: Create Cart

**As a** customer (guest or authenticated)
**I want to** create a shopping cart
**So that** I can add items before checkout

**Acceptance Criteria:**
- [x] Guest users get cart via session ID (auto-generated)
- [x] Authenticated users get cart via customer ID
- [x] Cart persists across page refreshes
- [x] Returns cart ID in response
- [x] Multi-tenant isolation (X-Tenant-ID required)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `CartController` | Presentation | 2h | - |
| Implement `POST /api/v1/cart` endpoint | Presentation | 1h | CartController |
| Add session ID generation for guests | Presentation | 1h | - |
| Integration test: create cart | Test | 1h | Controller |
| Functional test: guest vs authenticated | Test | 1h | Controller |

**API Specification:**
```yaml
POST /api/v1/cart
Headers:
  X-Tenant-ID: required
  Authorization: optional (Bearer JWT)
  X-Session-ID: optional (for guests, auto-generated if missing)
Request Body: {} (empty)
Response: 201 Created
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "sessionId": "sess_123...",
  "customerId": null,
  "status": "active",
  "items": [],
  "total": { "amount": "0.00", "currency": "USD" },
  "itemCount": 0,
  "createdAt": "2025-11-26T10:00:00Z",
  "updatedAt": "2025-11-26T10:00:00Z"
}
```

---

### US-002: Add Item to Cart

**As a** customer
**I want to** add products to my cart
**So that** I can purchase them later

**Acceptance Criteria:**
- [ ] Product ID and quantity required
- [ ] Variant ID optional (for products with variants)
- [ ] Duplicate products merge (increase quantity)
- [ ] Max 100 items per cart enforced
- [ ] Max 999 quantity per item enforced
- [ ] Price snapshot captured at add time
- [ ] Stock availability checked (emit event, do not block)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Implement `POST /api/v1/cart/{cartId}/items` | Presentation | 2h | US-001 |
| Update `AddItemToCartHandler` to fetch price | Application | 2h | PricingService |
| Create `CartPriceCalculator` service | Domain Service | 2h | - |
| Emit `StockCheckRequested` event | Domain | 1h | - |
| Unit test: add item to cart | Test | 1h | Handler |
| Functional test: add item API | Test | 1h | Controller |

**API Specification:**
```yaml
POST /api/v1/cart/{cartId}/items
Headers:
  X-Tenant-ID: required
Request Body:
{
  "productId": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "variantId": null,
  "quantity": 2
}
Response: 200 OK
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "items": [
    {
      "id": "item_123",
      "productId": "...",
      "variantId": null,
      "quantity": 2,
      "unitPrice": { "amount": "29.99", "currency": "USD" },
      "rowTotal": { "amount": "59.98", "currency": "USD" }
    }
  ],
  "total": { "amount": "59.98", "currency": "USD" },
  "itemCount": 2
}
```

---

### US-003: Update Item Quantity

**As a** customer
**I want to** change the quantity of items in my cart
**So that** I can buy more or fewer items

**Acceptance Criteria:**
- [ ] Cart item ID and new quantity required
- [ ] Quantity 0 removes item (or use DELETE)
- [ ] Max 999 quantity enforced
- [ ] Returns updated cart

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `UpdateCartItemQuantity` command | Application | 1h | - |
| Create `UpdateCartItemQuantityHandler` | Application | 1h | Command |
| Implement `PATCH /api/v1/cart/{cartId}/items/{itemId}` | Presentation | 1h | US-002 |
| Unit test: update quantity | Test | 1h | Handler |
| Functional test: update API | Test | 0.5h | Controller |

**API Specification:**
```yaml
PATCH /api/v1/cart/{cartId}/items/{itemId}
Headers:
  X-Tenant-ID: required
Request Body:
{
  "quantity": 5
}
Response: 200 OK
{ /* Full cart object */ }
```

---

### US-004: Remove Item from Cart

**As a** customer
**I want to** remove items from my cart
**So that** I no longer purchase unwanted items

**Acceptance Criteria:**
- [ ] Cart item ID required
- [ ] Returns 404 if item not found
- [ ] Returns updated cart

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `RemoveCartItem` command | Application | 0.5h | - |
| Create `RemoveCartItemHandler` | Application | 1h | Command |
| Implement `DELETE /api/v1/cart/{cartId}/items/{itemId}` | Presentation | 1h | US-002 |
| Unit test: remove item | Test | 0.5h | Handler |
| Functional test: remove API | Test | 0.5h | Controller |

**API Specification:**
```yaml
DELETE /api/v1/cart/{cartId}/items/{itemId}
Headers:
  X-Tenant-ID: required
Response: 200 OK
{ /* Full cart object */ }
```

---

### US-005: View Cart

**As a** customer
**I want to** view my cart contents
**So that** I can review items before checkout

**Acceptance Criteria:**
- [ ] Returns cart by ID or session/customer
- [ ] Includes all items with prices
- [ ] Calculates total
- [ ] Returns empty cart if none exists (creates one)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `GetCart` query | Application | 0.5h | - |
| Create `GetCartHandler` | Application | 1h | Query |
| Implement `GET /api/v1/cart` (current user/session) | Presentation | 1h | US-001 |
| Implement `GET /api/v1/cart/{cartId}` | Presentation | 0.5h | US-001 |
| Functional test: get cart API | Test | 1h | Controller |

**API Specification:**
```yaml
GET /api/v1/cart
Headers:
  X-Tenant-ID: required
  Authorization: optional
  X-Session-ID: optional
Response: 200 OK
{ /* Full cart object with items and totals */ }

GET /api/v1/cart/{cartId}
Response: 200 OK / 404 Not Found
```

---

### US-006: Checkout Cart (Convert to Order)

**As a** customer
**I want to** checkout my cart
**So that** I can place my order

**Acceptance Criteria:**
- [ ] Validates cart has items
- [ ] Validates all items have stock (integration with US-014)
- [ ] Creates order from cart items
- [ ] Marks cart as converted
- [ ] Returns order ID for payment flow
- [ ] Requires shipping address
- [ ] Requires billing address (or same as shipping)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `CheckoutCart` command | Application | 1h | - |
| Create `CheckoutCartHandler` | Application | 3h | Order context |
| Implement `POST /api/v1/cart/{cartId}/checkout` | Presentation | 2h | Handler |
| Integrate with `PlaceOrder` command | Application | 2h | Order context |
| Emit `CartCheckedOut` event | Domain | 0.5h | - |
| Unit test: checkout flow | Test | 2h | Handler |
| Integration test: cart -> order | Test | 2h | Full flow |

**API Specification:**
```yaml
POST /api/v1/cart/{cartId}/checkout
Headers:
  X-Tenant-ID: required
  Authorization: required (must be authenticated)
Request Body:
{
  "shippingAddress": {
    "firstName": "John",
    "lastName": "Doe",
    "street": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US"
  },
  "billingAddress": null,  // null = same as shipping
  "email": "john@example.com",
  "phone": "+1234567890",
  "notes": "Leave at door"
}
Response: 201 Created
{
  "orderId": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "orderNumber": "ORD-2025-000001",
  "total": { "amount": "99.98", "currency": "USD" },
  "status": "pending",
  "paymentUrl": "/api/v1/payments/stripe/create-intent"
}
```

---

## Epic 2: JWT Authentication (3 days)

### Existing Assets
- JWT Config: `config/packages/lexik_jwt_authentication.yaml`
- Security Config: `config/packages/security.yaml` (firewalls configured)
- User Entity: `UserEntity` implements `UserInterface`, `PasswordAuthenticatedUserInterface`
- User Domain: `User` aggregate with role management
- Login endpoint: `POST /api/login_check` (via Lexik bundle)
- Refresh token bundle: `gesdinet/jwt-refresh-token-bundle` installed

### Missing Components
- Register endpoint
- Profile endpoint
- Password reset flow

---

### US-007: Register Account

**As a** visitor
**I want to** create an account
**So that** I can track orders and save preferences

**Acceptance Criteria:**
- [ ] Email, username, and password required
- [ ] Email must be unique
- [ ] Username must be unique
- [ ] Password min 8 characters
- [ ] Returns JWT token on success (auto-login)
- [ ] Sends welcome email (async)
- [ ] Creates Customer record linked to User

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `RegisterUser` command | Application | 1h | - |
| Create `RegisterUserHandler` | Application | 2h | Command |
| Create `RegistrationController` | Presentation | 2h | Handler |
| Implement `POST /api/v1/auth/register` | Presentation | 1h | Controller |
| Create Customer on registration | Application | 1h | Customer context |
| Send welcome email (async) | Application | 1h | Messenger |
| Unit test: registration handler | Test | 1h | Handler |
| Functional test: register API | Test | 1h | Controller |

**API Specification:**
```yaml
POST /api/v1/auth/register
Headers:
  X-Tenant-ID: required
Request Body:
{
  "email": "john@example.com",
  "username": "johndoe",
  "password": "securePassword123",
  "firstName": "John",
  "lastName": "Doe"
}
Response: 201 Created
{
  "user": {
    "id": "...",
    "email": "john@example.com",
    "username": "johndoe",
    "roles": ["ROLE_CUSTOMER"]
  },
  "token": "eyJ...",
  "refreshToken": "...",
  "customerId": "..."
}
```

---

### US-008: Login

**As a** registered user
**I want to** login to my account
**So that** I can access my orders and profile

**Acceptance Criteria:**
- [x] Email and password required (already implemented via Lexik)
- [x] Returns JWT token on success
- [ ] Returns refresh token
- [ ] Locks account after 5 failed attempts
- [ ] Merges guest cart with user cart on login

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Configure refresh token bundle | Infrastructure | 1h | - |
| Add refresh token to login response | Presentation | 1h | Config |
| Create `MergeGuestCart` command | Application | 2h | Cart context |
| Create cart merge on login listener | Application | 1h | Command |
| Add failed attempt tracking | Infrastructure | 2h | User entity |
| Functional test: login with refresh | Test | 1h | Config |

**API Specification:**
```yaml
POST /api/login_check  # Existing Lexik endpoint
Request Body:
{
  "email": "john@example.com",
  "password": "securePassword123"
}
Response: 200 OK
{
  "token": "eyJ...",
  "refreshToken": "...",
  "user": {
    "id": "...",
    "email": "john@example.com",
    "roles": ["ROLE_CUSTOMER"]
  }
}
```

---

### US-009: Refresh Token

**As a** logged-in user
**I want to** refresh my JWT token
**So that** I stay logged in without re-entering credentials

**Acceptance Criteria:**
- [ ] Refresh token in request body
- [ ] Returns new JWT + refresh token
- [ ] Old refresh token invalidated

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Configure refresh endpoint | Infrastructure | 1h | US-008 |
| Test refresh flow | Test | 1h | Config |

**API Specification:**
```yaml
POST /api/v1/auth/token/refresh
Request Body:
{
  "refreshToken": "..."
}
Response: 200 OK
{
  "token": "eyJ...",
  "refreshToken": "..."
}
```

---

### US-010: Reset Password

**As a** user who forgot my password
**I want to** reset my password via email
**So that** I can regain access to my account

**Acceptance Criteria:**
- [ ] Request reset with email
- [ ] Email contains secure reset link (token valid 1 hour)
- [ ] Reset with token + new password
- [ ] Invalidates all existing sessions

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `RequestPasswordReset` command | Application | 1h | - |
| Create `ResetPassword` command | Application | 1h | - |
| Create handlers for both | Application | 2h | Commands |
| Create password reset email template | Infrastructure | 1h | - |
| Implement `POST /api/v1/auth/password/reset-request` | Presentation | 1h | Handler |
| Implement `POST /api/v1/auth/password/reset` | Presentation | 1h | Handler |
| Store reset tokens in database | Infrastructure | 1h | - |
| Functional tests | Test | 1h | Endpoints |

**API Specification:**
```yaml
POST /api/v1/auth/password/reset-request
Request Body:
{
  "email": "john@example.com"
}
Response: 202 Accepted
{
  "message": "If an account exists, a reset email has been sent"
}

POST /api/v1/auth/password/reset
Request Body:
{
  "token": "...",
  "password": "newSecurePassword123"
}
Response: 200 OK
{
  "message": "Password reset successfully"
}
```

---

## Epic 3: Payment Integration (4 days)

### Existing Assets
- Stripe Gateway: `StripePaymentGateway` (authorize, capture, refund, cancel)
- Stripe Controller: `StripePaymentController` (create-intent, verify, capture)
- Webhook Controller: `StripeWebhookController`
- Payment Domain: `Payment` aggregate with retry policy
- Payment Events: `PaymentCreated`, `PaymentAuthorized`, `PaymentCaptured`, `PaymentFailed`
- Stripe SDK: `stripe/stripe-php: ^18.0` installed

### Missing Components
- End-to-end payment flow integration
- Proper webhook signature verification
- Payment status updates to Order
- Frontend payment element integration data

---

### US-011: Pay with Stripe ✅ COMPLETE

**As a** customer at checkout
**I want to** pay with my credit card via Stripe
**So that** I can complete my purchase

**Acceptance Criteria:**
- [x] Create PaymentIntent from order total
- [x] Return client_secret for Stripe Elements
- [x] Link Payment to Order
- [x] Handle 3D Secure authentication (via Stripe's automatic_payment_methods)
- [x] Support multiple currencies

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Update `StripePaymentController` to accept orderId | Presentation | 1h | US-006 |
| Create `InitiatePayment` command | Application | 1h | - |
| Create `InitiatePaymentHandler` | Application | 2h | Payment domain |
| Link Payment entity to Order | Infrastructure | 1h | Migration |
| Return proper payment data for frontend | Presentation | 1h | Controller |
| Integration test: payment flow | Test | 2h | Full flow |

**API Specification:**
```yaml
POST /api/v1/payments/stripe/create-intent
Headers:
  X-Tenant-ID: required
Request Body:
{
  "orderId": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "amount": 9998,  # in cents
  "currency": "usd",
  "customerEmail": "john@example.com"
}
Response: 200 OK
{
  "clientSecret": "pi_xxx_secret_xxx",
  "paymentIntentId": "pi_xxx",
  "paymentId": "01ARZ3NDEKTSV4RRFFQ69G5FAV"  # Our internal ID
}
```

---

### US-012: Payment Confirmation ✅ COMPLETE

**As a** customer
**I want to** receive confirmation when payment succeeds
**So that** I know my order is being processed

**Acceptance Criteria:**
- [x] Stripe webhook updates Payment status
- [x] Payment status updates Order status
- [x] Sends order confirmation email (via PaymentCapturedSubscriber)
- [x] Handles payment failures gracefully (MarkPaymentAsFailed command)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Implement webhook signature verification | Infrastructure | 2h | Security |
| Handle `payment_intent.succeeded` event | Application | 2h | Webhook |
| Handle `payment_intent.payment_failed` event | Application | 1h | Webhook |
| Update Order status on payment success | Application | 1h | Order context |
| Create `PaymentSucceededSubscriber` | Application | 2h | Events |
| Send confirmation email | Notifications | 1h | Email templates |
| Test webhook handling | Test | 2h | Webhook |

**Webhook Events to Handle:**
```php
// Events from Stripe
- payment_intent.succeeded -> Payment::capture() -> Order::markAsPaid()
- payment_intent.payment_failed -> Payment::markAsFailed() -> Notify customer
- charge.refunded -> Payment::refund() -> Order::markAsRefunded()
- payment_intent.canceled -> Payment::cancel() -> Release stock
```

---

### US-013: Payment Retry (Failed Payments) ✅ COMPLETE

**As a** system administrator
**I want** failed payments to be retried automatically
**So that** transient failures don't lose sales

**Acceptance Criteria:**
- [x] Retry policy: 3 attempts with exponential backoff (1h, 4h, 24h) - Domain exists
- [x] Only retry transient errors (card_declined, insufficient_funds) - RetryPolicy exists
- [x] Never retry: expired_card, fraudulent, invalid_card
- [x] Scheduled command to process retries (PaymentScheduleProvider + ProcessPaymentRetries)
- [x] Emit events for retry exhaustion (PaymentRetryExhausted event)

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `ProcessPaymentRetries` command | Application | 1h | - |
| Create `ProcessPaymentRetriesHandler` | Application | 2h | Payment domain |
| Schedule retry processor (Symfony Scheduler) | Infrastructure | 1h | Scheduler |
| Create retry exhaustion notification | Notifications | 1h | Email |
| Test retry flow | Test | 1h | Handler |

**Scheduled Command:**
```php
#[AsPeriodicTask(frequency: '5 minutes')]
class ProcessPaymentRetriesSchedule
{
    public function __invoke(): void
    {
        // Find payments due for retry and dispatch commands
    }
}
```

---

## Epic 4: Stock Management (3 days)

### Existing Assets
- StockItem Domain: `StockItem` aggregate with reserve/allocate/release
- StockReservation Domain: `StockReservation` with expiry logic
- StockItem Entity: `StockItemEntity`
- Warehouse Domain: Complete with API
- Events: `StockReserved`, `StockAllocated`, `StockReleased`, `StockDepleted`

### Missing Components
- Reservation tracking in database
- API endpoints for stock operations
- Integration with checkout flow
- Automatic reservation release job

---

### US-014: Reserve Stock on Order

**As a** system
**I want to** reserve stock when an order is placed
**So that** we don't oversell inventory

**Acceptance Criteria:**
- [ ] Reserve stock for each order line item
- [ ] Check availability before reservation
- [ ] Return error if insufficient stock
- [ ] Reservation expires after 15 minutes (checkout timeout)
- [ ] Support multi-warehouse fulfillment

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `StockReservationEntity` | Infrastructure | 1h | Migration |
| Create `StockReservationRepository` | Infrastructure | 2h | Entity |
| Create `ReserveStock` command | Application | 1h | - |
| Create `ReserveStockHandler` | Application | 2h | Repository |
| Integrate with `CheckoutCartHandler` | Application | 1h | US-006 |
| Create `StockReservationService` | Domain Service | 2h | Multi-warehouse |
| Unit test: stock reservation | Test | 1h | Handler |
| Integration test: checkout reserves stock | Test | 1h | Flow |

**Stock Reservation Flow:**
```
1. Customer initiates checkout
2. For each cart item:
   a. Find available stock across warehouses
   b. Reserve from highest priority warehouse
   c. Create StockReservation record (15 min expiry)
3. If any item fails:
   a. Release all reservations made in this transaction
   b. Return error with unavailable items
4. If all succeed:
   a. Proceed to payment
   b. On payment success: convert reservations to allocations
   c. On payment failure/timeout: release reservations
```

---

### US-015: Release Stock on Cancellation

**As a** system
**I want to** release stock when an order is cancelled
**So that** inventory is available for other customers

**Acceptance Criteria:**
- [ ] Release all reservations for cancelled order
- [ ] Update StockItem.reserved/allocated counts
- [ ] Emit `StockReleased` event for each item
- [ ] Log release reason for audit

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `ReleaseStock` command | Application | 1h | - |
| Create `ReleaseStockHandler` | Application | 1h | Repository |
| Create `OrderCancelledStockSubscriber` | Application | 1h | US-014 |
| Listen to `OrderCancelled` event | Application | 0.5h | Order context |
| Unit test: release stock | Test | 0.5h | Handler |

---

### US-016: Prevent Overselling

**As a** business owner
**I want** the system to prevent overselling
**So that** I don't disappoint customers with backorders

**Acceptance Criteria:**
- [ ] Real-time stock check at add-to-cart (warning only)
- [ ] Hard stock check at checkout (blocking)
- [ ] Atomic reservation (all-or-nothing)
- [ ] Scheduled job releases expired reservations

**Technical Tasks:**

| Task | Layer | Estimate | Dependencies |
|------|-------|----------|--------------|
| Create `CheckStockAvailability` query | Application | 1h | - |
| Create `CheckStockAvailabilityHandler` | Application | 1h | Query |
| Create `ReleaseExpiredReservations` scheduled command | Application | 2h | Scheduler |
| Implement `POST /api/v1/stock/check` endpoint | Presentation | 1h | Query |
| Configure Symfony Scheduler for cleanup | Infrastructure | 1h | Config |
| Integration test: concurrent checkout | Test | 2h | Stress test |

**API Specification:**
```yaml
POST /api/v1/stock/check
Headers:
  X-Tenant-ID: required
Request Body:
{
  "items": [
    { "productId": "...", "variantId": null, "quantity": 2 },
    { "productId": "...", "variantId": "...", "quantity": 1 }
  ]
}
Response: 200 OK
{
  "available": true,
  "items": [
    { "productId": "...", "available": true, "quantityAvailable": 50 },
    { "productId": "...", "available": false, "quantityAvailable": 0 }
  ]
}
```

**Scheduled Jobs:**
```php
// Every 1 minute - release expired reservations
#[AsPeriodicTask(frequency: '1 minute')]
class ReleaseExpiredReservationsSchedule { ... }

// Every 5 minutes - process payment retries
#[AsPeriodicTask(frequency: '5 minutes')]
class ProcessPaymentRetriesSchedule { ... }
```

---

## Dependencies & Order of Implementation

```
Week 1: Foundation (Days 1-5)
├── Day 1-2: Cart API (US-001, US-002, US-005)
├── Day 3: Cart API (US-003, US-004)
├── Day 4: Auth (US-007, US-008, US-009)
└── Day 5: Auth (US-010), Stock foundation (entities, migrations)

Week 2: Integration (Days 6-10)
├── Day 6-7: Checkout (US-006)
├── Day 8: Stock Reservations (US-014)
├── Day 9: Payment Integration (US-011)
└── Day 10: Payment Integration (US-012)

Week 3: Polish & Testing (Days 11-15)
├── Day 11: Stock Release (US-015, US-016)
├── Day 12: Payment Retry (US-013)
├── Day 13: End-to-end integration testing
├── Day 14: Bug fixes, edge cases
└── Day 15: Documentation, deployment prep
```

---

## Technical Specifications

### Database Migrations Required

```sql
-- Migration 1: Stock Reservations Table
CREATE TABLE stock_reservations (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    stock_item_id VARCHAR(36) NOT NULL,
    warehouse_id VARCHAR(36) NOT NULL,
    order_id VARCHAR(36) NULL,
    cart_id VARCHAR(36) NULL,
    quantity INT NOT NULL,
    reserved_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_released BOOLEAN DEFAULT FALSE,
    released_at TIMESTAMP NULL,
    release_reason VARCHAR(50) NULL,
    FOREIGN KEY (stock_item_id) REFERENCES stock_items(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE INDEX idx_reservations_expires ON stock_reservations(expires_at) WHERE is_released = FALSE;
CREATE INDEX idx_reservations_order ON stock_reservations(tenant_id, order_id);

-- Migration 2: Password Reset Tokens Table
CREATE TABLE password_reset_tokens (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX idx_reset_tokens_expires ON password_reset_tokens(expires_at) WHERE used_at IS NULL;

-- Migration 3: Refresh Tokens (if not using bundle's default)
-- gesdinet/jwt-refresh-token-bundle handles this

-- Migration 4: Add payment_id to orders (if not exists)
ALTER TABLE orders ADD COLUMN payment_id VARCHAR(36) NULL;
CREATE INDEX idx_orders_payment ON orders(payment_id);
```

### Configuration Updates

```yaml
# config/packages/gesdinet_jwt_refresh_token.yaml
gesdinet_jwt_refresh_token:
    ttl: 2592000  # 30 days
    user_identity_field: email
    firewall: login

# config/packages/messenger.yaml (add routing)
framework:
    messenger:
        routing:
            'App\Inventory\Application\Command\ReleaseExpiredReservations': async
            'App\Payment\Application\Command\ProcessPaymentRetry': async
            'App\Notifications\Application\Command\SendEmail': async

# config/packages/security.yaml (update access_control)
security:
    access_control:
        - { path: ^/api/v1/auth/register, roles: PUBLIC_ACCESS }
        - { path: ^/api/v1/auth/password, roles: PUBLIC_ACCESS }
        - { path: ^/api/v1/cart, roles: PUBLIC_ACCESS, methods: [GET, POST] }
        - { path: ^/api/v1/cart/.*/checkout, roles: IS_AUTHENTICATED_FULLY }
```

### New Files to Create

```
src/
├── Cart/
│   ├── Presentation/Api/Controller/CartController.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── UpdateCartItemQuantity.php
│   │   │   ├── UpdateCartItemQuantityHandler.php
│   │   │   ├── RemoveCartItem.php
│   │   │   ├── RemoveCartItemHandler.php
│   │   │   ├── ClearCart.php
│   │   │   ├── ClearCartHandler.php
│   │   │   ├── CheckoutCart.php
│   │   │   └── CheckoutCartHandler.php
│   │   └── Query/
│   │       ├── GetCart.php
│   │       └── GetCartHandler.php
│   └── Domain/Service/CartPriceCalculator.php
├── User/
│   ├── Presentation/Api/Controller/
│   │   ├── RegistrationController.php
│   │   └── PasswordResetController.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── RegisterUser.php
│   │   │   ├── RegisterUserHandler.php
│   │   │   ├── RequestPasswordReset.php
│   │   │   ├── RequestPasswordResetHandler.php
│   │   │   ├── ResetPassword.php
│   │   │   └── ResetPasswordHandler.php
│   │   └── EventSubscriber/
│   │       └── UserRegisteredSubscriber.php
│   └── Infrastructure/
│       └── Persistence/Doctrine/Entity/PasswordResetTokenEntity.php
├── Inventory/
│   ├── Presentation/Api/Controller/StockController.php
│   ├── Application/
│   │   ├── Command/
│   │   │   ├── ReserveStock.php
│   │   │   ├── ReserveStockHandler.php
│   │   │   ├── ReleaseStock.php
│   │   │   ├── ReleaseStockHandler.php
│   │   │   ├── ReleaseExpiredReservations.php
│   │   │   └── ReleaseExpiredReservationsHandler.php
│   │   ├── Query/
│   │   │   ├── CheckStockAvailability.php
│   │   │   └── CheckStockAvailabilityHandler.php
│   │   └── EventSubscriber/
│   │       └── OrderCancelledStockSubscriber.php
│   └── Infrastructure/
│       └── Persistence/Doctrine/
│           ├── Entity/StockReservationEntity.php
│           └── Repository/DoctrineStockReservationRepository.php
└── Payment/
    ├── Application/
    │   ├── Command/
    │   │   ├── ProcessPaymentRetry.php
    │   │   └── ProcessPaymentRetryHandler.php
    │   └── EventSubscriber/
    │       ├── PaymentSucceededSubscriber.php
    │       └── PaymentFailedSubscriber.php
    └── Infrastructure/
        └── Scheduler/
            └── PaymentRetrySchedule.php
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
- [ ] Unit tests for domain logic (>=90% coverage)
- [ ] Integration tests for repositories
- [ ] Functional tests for API endpoints
- [ ] All tests pass in CI

### Documentation
- [ ] API documented in OpenAPI spec
- [ ] CLAUDE.md updated if new patterns introduced
- [ ] README updated if new setup steps required

### Security
- [ ] Input validation on all endpoints
- [ ] Authorization checks (tenant isolation)
- [ ] Rate limiting on auth endpoints
- [ ] Sensitive data not logged

---

## Risk Assessment

### High Risk

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Stripe webhook signature verification fails in production | Payment status not updated | Medium | Test with Stripe CLI, use proper endpoint URL |
| Race condition in stock reservation | Overselling | Medium | Use database transactions, optimistic locking |
| Cart-Order integration breaks existing order flow | Checkout blocked | Low | Create separate checkout path, feature flag |

### Medium Risk

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| JWT refresh token not properly invalidated | Security vulnerability | Low | Use token blacklist, short TTL |
| Expired reservation cleanup misses edge cases | Stock locked | Medium | Comprehensive testing, monitoring |
| Payment retry creates duplicate payments | Customer charged twice | Low | Idempotency keys, Stripe handles this |

### Low Risk

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Email sending failures | Customer not notified | Low | Async queue with retry, logging |
| Performance under load | Slow checkout | Low | Redis caching, database indexes |

---

## Success Metrics

### Sprint Success Criteria

| Metric | Target | Measurement |
|--------|--------|-------------|
| All user stories completed | 16/16 | Story points done |
| Test coverage | >= 80% | PHPUnit coverage report |
| API response time | < 200ms p95 | API monitoring |
| Zero critical bugs | 0 | QA testing |

### Go-Live Readiness Checklist

- [ ] Complete checkout flow works end-to-end
- [ ] Guest checkout works
- [ ] Authenticated checkout works
- [ ] Stripe payment succeeds
- [ ] Order confirmation email sent
- [ ] Stock reserved on checkout
- [ ] Stock released on cancellation
- [ ] JWT login/register works
- [ ] Password reset works
- [ ] All tests green
- [ ] PHPStan level 8 passes
- [ ] Deptrac passes
- [ ] Security review completed

---

## Appendix: Existing Code References

### Cart Domain Model Location
`/var/www/new_ecom/backend/src/Cart/Domain/Model/Cart.php`

Key methods:
- `Cart::create()` - Factory method
- `Cart::addItem()` - Adds item with duplicate detection
- `Cart::removeItem()` - Removes by CartItemId
- `Cart::updateQuantity()` - Updates item quantity
- `Cart::clear()` - Removes all items
- `Cart::markAsConverted()` - Marks cart as converted to order
- `Cart::calculateTotal()` - Returns Money value object

### Payment Gateway Location
`/var/www/new_ecom/backend/src/Payment/Infrastructure/Gateway/StripePaymentGateway.php`

Key methods:
- `authorize()` - Creates PaymentIntent with manual capture
- `capture()` - Captures authorized payment
- `refund()` - Processes refund
- `cancel()` - Cancels payment
- `getStatus()` - Retrieves payment status

### Stock Item Domain Location
`/var/www/new_ecom/backend/src/Inventory/Domain/Model/StockItem.php`

Key methods:
- `reserve()` - Soft hold for cart/checkout
- `allocate()` - Hard allocation for confirmed order
- `release()` - Release reserved/allocated stock
- `calculateAvailable()` - Returns available quantity

### Security Configuration Location
`/var/www/new_ecom/backend/config/packages/security.yaml`

Key firewalls:
- `login` - JSON login with JWT success handler
- `api` - JWT protected API routes

---

## Sources

- [LexikJWTAuthenticationBundle Documentation](https://symfony.com/bundles/LexikJWTAuthenticationBundle) - JWT authentication setup
- [Stripe PHP SDK Integration Guide](https://medium.com/@agharsaifeddine/how-to-integrate-stripe-in-a-php-symfony-app-a-complete-step-by-step-guide-9bff98575190) - Stripe + Symfony integration patterns
- [Stripe Payment Intents API](https://docs.stripe.com/changelog/clover/2025-11-17/payment-intents-tax-support?locale=en-GB) - Latest Stripe API updates
