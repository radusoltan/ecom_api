# Cart API Documentation

## Overview

The Cart API provides a complete shopping cart management system for the e-commerce platform. It supports both guest and authenticated users with automatic cart creation, merging, and comprehensive CRUD operations.

**Base URL**: `/api/v1`

**Authentication**:
- Guest users: `X-Session-ID` header (optional, auto-generated if not provided)
- Authenticated users: JWT Bearer token
- All requests: `X-Tenant-ID` header (required for multi-tenancy)

## Key Features

- ✅ Automatic cart creation for new users
- ✅ Guest cart support with session management
- ✅ Cart merging on user authentication
- ✅ Real-time stock validation
- ✅ Price snapshot at add-to-cart time
- ✅ Duplicate item detection with quantity merging
- ✅ Maximum 100 items per cart
- ✅ Maximum 999 quantity per item
- ✅ Cart expiration after 7 days of inactivity
- ✅ Multi-tenant isolation

## API Endpoints

### 1. Retrieve Cart

**GET** `/api/v1/cart`

Retrieve the current cart with all items, prices, and totals.

**Headers:**
```
X-Cart-ID: <cart-uuid>          (required for existing carts)
X-Tenant-ID: <tenant-uuid>      (required)
Authorization: Bearer <token>   (optional)
```

**Response 200 - Success:**
```json
{
  "id": "01HQVZP3X8EXAMPLEULID123",
  "tenantId": "550e8400-e29b-41d4-a716-446655440000",
  "customerId": null,
  "sessionId": "01HQVZP3X8SESSION123",
  "status": "active",
  "items": [
    {
      "id": "01HQVZP3X8ITEM123",
      "productId": "01HQVZP3X8PRODUCT123",
      "variantId": "size-L",
      "quantity": 2,
      "unitPrice": {
        "amount": 1999,
        "currency": "USD"
      }
    }
  ],
  "totalAmount": 3998,
  "totalCurrency": "USD",
  "itemCount": 2,
  "createdAt": "2025-11-01T10:00:00Z",
  "updatedAt": "2025-11-01T10:30:00Z"
}
```

**Response 404 - Cart Not Found:**
```json
{
  "@type": "Error",
  "title": "Not Found",
  "description": "Cart not found"
}
```

---

### 2. Add Item to Cart

**POST** `/api/v1/cart/items`

Add a product to the cart. If the cart doesn't exist, it will be created automatically. If the same product already exists (same productId + variantId), quantities will be merged.

**Headers:**
```
X-Tenant-ID: <tenant-uuid>      (required)
Content-Type: application/json
Authorization: Bearer <token>   (optional)
```

**Request Body:**
```json
{
  "tenantId": "550e8400-e29b-41d4-a716-446655440000",
  "productId": "01HQVZP3X8PRODUCT123",
  "variantId": "size-L",
  "quantity": 2,
  "unitPriceAmount": 1999,
  "unitPriceCurrency": "USD",
  "customerId": null
}
```

**Field Descriptions:**
- `tenantId` (required): Tenant identifier
- `productId` (required): Product identifier (ULID)
- `variantId` (optional): Product variant identifier (e.g., "size-L", "color-red")
- `quantity` (required): Number of items (1-999)
- `unitPriceAmount` (optional): Price in cents. If not provided, fetched from Catalog
- `unitPriceCurrency` (optional): Currency code (USD, EUR, etc.)
- `customerId` (optional): For authenticated users only

**Response 201 - Success:**
Returns the full cart object (same structure as GET /cart)

**Response 400 - Invalid Request:**
```json
{
  "@type": "Error",
  "title": "Bad Request",
  "description": "Product ID and quantity are required"
}
```

**Response 409 - Insufficient Stock:**
```json
{
  "@type": "Error",
  "title": "Conflict",
  "description": "Insufficient stock. Only 5 items available"
}
```

---

### 3. Update Cart Item Quantity

**PATCH** `/api/v1/cart/items/{itemId}`

Update the quantity of an existing cart item. Stock availability is validated before update.

**Headers:**
```
X-Cart-ID: <cart-uuid>          (required)
X-Tenant-ID: <tenant-uuid>      (required)
Content-Type: application/json
```

**URL Parameters:**
- `itemId`: Cart item identifier (UUID/ULID)

**Request Body:**
```json
{
  "tenantId": "550e8400-e29b-41d4-a716-446655440000",
  "newQuantity": 5
}
```

**Response 200 - Success:**
Returns the updated cart object

**Response 400 - Invalid Quantity:**
```json
{
  "@type": "Error",
  "title": "Bad Request",
  "description": "Quantity must be between 1 and 999"
}
```

**Response 404 - Item Not Found:**
```json
{
  "@type": "Error",
  "title": "Not Found",
  "description": "Cart item not found"
}
```

**Response 409 - Insufficient Stock:**
```json
{
  "@type": "Error",
  "title": "Conflict",
  "description": "Insufficient stock for requested quantity"
}
```

---

### 4. Remove Item from Cart

**DELETE** `/api/v1/cart/items/{itemId}`

Remove a specific item from the cart.

**Headers:**
```
X-Cart-ID: <cart-uuid>          (required)
X-Tenant-ID: <tenant-uuid>      (required)
```

**URL Parameters:**
- `itemId`: Cart item identifier to remove

**Response 200 - Success:**
Returns the updated cart object (without the removed item)

**Response 404 - Item Not Found:**
```json
{
  "@type": "Error",
  "title": "Not Found",
  "description": "Cart item not found"
}
```

---

### 5. Clear Cart

**DELETE** `/api/v1/cart`

Remove all items from the cart. The cart itself remains but becomes empty.

**Headers:**
```
X-Cart-ID: <cart-uuid>          (required)
X-Tenant-ID: <tenant-uuid>      (required)
```

**Response 200 - Success:**
Returns the empty cart object:
```json
{
  "id": "01HQVZP3X8EXAMPLEULID123",
  "tenantId": "550e8400-e29b-41d4-a716-446655440000",
  "items": [],
  "totalAmount": 0,
  "totalCurrency": "USD",
  "itemCount": 0,
  "createdAt": "2025-11-01T10:00:00Z",
  "updatedAt": "2025-11-01T11:00:00Z"
}
```

**Response 404 - Cart Not Found:**
```json
{
  "@type": "Error",
  "title": "Not Found",
  "description": "Cart not found"
}
```

---

## Business Rules

### Cart Limits
- **Maximum Items per Cart**: 100 distinct items
- **Maximum Quantity per Item**: 999
- **Minimum Quantity per Item**: 1
- **Cart Expiration**: 7 days of inactivity

### Duplicate Item Detection
When adding an item that already exists in the cart (same `productId` + `variantId`):
- Quantities are automatically merged
- Event emitted: `CartQuantityUpdated` (not `ItemAddedToCart`)
- Unit price is NOT updated (price snapshot maintained)

### Price Handling
- **Price Snapshot**: Prices are captured at add-to-cart time
- **No Auto-Update**: Prices do NOT change when product prices change
- **Optional Override**: Can provide explicit price via `unitPriceAmount`/`unitPriceCurrency`
- **Default Behavior**: If price not provided, fetched from Catalog context

### Stock Validation
- Validated on `AddItemToCart` command
- Validated on `UpdateCartQuantity` command
- Integration with Inventory context via `StockValidator` service
- Returns available quantity in error messages

### Multi-Tenancy
- All operations are tenant-isolated
- `X-Tenant-ID` header required on all requests
- PostgreSQL Row-Level Security enforces isolation at database level
- Cannot access carts from different tenants

### Guest vs Authenticated Carts
- **Guest Cart**: Identified by `sessionId` (generated on frontend)
- **Authenticated Cart**: Identified by `customerId` (from JWT token)
- **Cart Merge**: On login, guest cart items are merged into authenticated cart
- **Session Management**: Session ID stored in browser localStorage/sessionStorage

---

## Error Responses

All error responses follow the RFC 7807 Problem Details format:

```json
{
  "@context": "/api/v1/contexts/Error",
  "@id": "/api/v1/errors/400",
  "@type": "Error",
  "title": "Bad Request",
  "description": "Detailed error message",
  "status": 400,
  "type": "/errors/400"
}
```

### Common Status Codes

- **200 OK**: Successful GET, PATCH, DELETE operations
- **201 Created**: Successful POST operations
- **400 Bad Request**: Invalid input, missing required fields, validation errors
- **404 Not Found**: Cart or cart item not found
- **409 Conflict**: Insufficient stock, business rule violations
- **500 Internal Server Error**: Unexpected server errors

---

## Domain Events

The Cart API emits the following domain events for event-driven integrations:

1. **CartCreated**
   - Emitted when: New cart is created
   - Payload: cartId, tenantId, customerId, sessionId

2. **ItemAddedToCart**
   - Emitted when: New item added to cart
   - Payload: cartId, tenantId, cartItemId, productId, variantId, quantity, unitPrice

3. **CartQuantityUpdated**
   - Emitted when: Item quantity changed (update or merge)
   - Payload: cartId, tenantId, cartItemId, productId, variantId, newQuantity

4. **ItemRemovedFromCart**
   - Emitted when: Item removed from cart
   - Payload: cartId, tenantId, cartItemId, productId, variantId

5. **CartCleared**
   - Emitted when: All items removed from cart
   - Payload: cartId, tenantId

---

## Integration Points

### Catalog Context
- **Service**: `CartPriceCalculator`
- **Purpose**: Fetch current product prices
- **Query**: `GetProductPrice(productId, variantId, tenantId, customerId)`
- **Fallback**: Uses base price if segment price unavailable

### Inventory Context
- **Service**: `StockValidator`
- **Purpose**: Validate stock availability
- **Query**: `CheckStockAvailability(productId, variantId, tenantId, quantity)`
- **Features**: Multi-warehouse aggregation, detailed availability info

### Customer Context
- **Purpose**: Cart ownership and personalization
- **Data**: CustomerId for authenticated users
- **Features**: Customer segment pricing, cart history

---

## Testing

### Test Coverage
- **Unit Tests**: 50 tests (90%+ coverage)
  - CartTest.php: 32 tests
  - QuantityTest.php: 10 tests
  - CartIdTest.php: 8 tests

- **Functional Tests**: 13 comprehensive API tests
  - Add item scenarios (success, duplicate, stock validation)
  - Update quantity scenarios
  - Remove item scenarios
  - Clear cart scenarios
  - Multi-tenancy isolation
  - Guest vs authenticated flows

### Test Database
All tests use transaction rollback for isolation. Test data is never persisted.

---

## OpenAPI Specification

Full OpenAPI 3.1 specification available at:
- **YAML**: `/docs/api/cart-openapi.yaml`
- **JSON**: `/docs/api/cart-openapi.json`
- **Interactive UI**: `http://localhost:8000/api/docs` (when server running)

---

## Architecture

### Design Patterns
- **DDD (Domain-Driven Design)**: Cart is an Aggregate Root
- **CQRS (Command Query Responsibility Segregation)**: Separate write/read models
- **Hexagonal Architecture**: Clean separation of domain, application, infrastructure
- **Event Sourcing**: Domain events for all state changes

### Layer Separation
- **Domain Layer**: Pure business logic (no framework dependencies)
  - `src/Cart/Domain/Model/Cart.php`
  - `src/Cart/Domain/Model/CartItem.php`
  - Value objects, exceptions, events

- **Application Layer**: Use cases and orchestration
  - Command handlers (writes)
  - Query handlers (reads)
  - DTOs for data transfer

- **Infrastructure Layer**: Framework integration
  - Doctrine entities (ORM)
  - API Platform resources
  - State processors/providers
  - Repository implementations

### Database Schema
```sql
CREATE TABLE carts (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    customer_id VARCHAR(36),
    session_id VARCHAR(36),
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    INDEX idx_carts_tenant_id (tenant_id),
    INDEX idx_carts_customer_id (customer_id),
    INDEX idx_carts_session_id (session_id)
);

CREATE TABLE cart_items (
    id VARCHAR(36) PRIMARY KEY,
    cart_id VARCHAR(36) NOT NULL,
    product_id VARCHAR(36) NOT NULL,
    variant_id VARCHAR(50),
    quantity INT NOT NULL CHECK (quantity >= 1 AND quantity <= 999),
    unit_price_amount INT NOT NULL,
    unit_price_currency VARCHAR(3) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
);
```

---

## Future Enhancements (Not in P0)

- [ ] Cart sharing (send cart link to friend)
- [ ] Save cart for later
- [ ] Cart comparison
- [ ] Abandoned cart recovery
- [ ] Promotional pricing integration
- [ ] Coupon code application
- [ ] Gift wrapping options
- [ ] Personalization/notes per item
- [ ] Subscription cart management
- [ ] B2B bulk pricing

---

## Support

For API issues or questions:
- GitHub Issues: https://github.com/your-org/ecommerce/issues
- API Documentation: http://localhost:8000/api/docs
- Development Guide: See `docs/guides/new-aggregate.md`

---

**Version**: 1.0.0
**Last Updated**: 2025-11-01
**Status**: ✅ Production Ready (P0 Complete)
