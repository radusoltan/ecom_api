# Cart/Checkout Pricing Integration

## Overview

This document describes the integration between the **Cart** and **Pricing** bounded contexts for cart pricing calculations and promotion/coupon application.

## Architecture

### Anti-Corruption Layer (ACL) Pattern

The integration follows the ACL pattern to maintain bounded context isolation:

- **Pricing Context** reads Cart aggregates (read-only)
- **Cart Context** never directly depends on Pricing context
- Communication is unidirectional: Pricing → Cart (read)
- No shared domain models are modified across contexts

### Components

#### 1. Services (Pricing/Application/Service)

**CartPricingService**
- Calculates complete pricing for a cart
- Applies price lists (catalog-level discounts)
- Applies promotions (cart-rule, catalog-rule)
- Returns detailed breakdown with all discounts

**PromotionApplicator**
- Validates coupon codes
- Checks promotion conditions
- Applies promotions to cart
- Validates promotion stacking

#### 2. DTOs (Pricing/Application/DTO)

**CartPriceCalculationResult**
- Complete pricing breakdown for cart
- Contains item-level and cart-level pricing
- Includes all applied discounts

**CartItemPricing**
- Pricing details for individual cart item
- Base price vs final price
- Applied discounts per item

**AppliedDiscountDTO**
- Details of a single discount
- Type (price_list, promotion, coupon)
- Amount and percentage

#### 3. Queries & Commands

**GetCartPricingQuery / GetCartPricingQueryHandler**
- Retrieve cart pricing breakdown
- Supports applied coupon codes
- Returns CartPriceCalculationResult

**ApplyCouponToCartCommand / ApplyCouponToCartCommandHandler**
- Validate coupon code
- Apply coupon to cart
- Returns AppliedDiscountDTO

#### 4. API Endpoints

**GET /api/cart/pricing**
- Headers: X-Cart-ID, X-Tenant-ID
- Query params: coupons (comma-separated)
- Returns: Complete pricing breakdown

**POST /api/cart/apply-coupon**
- Headers: X-Cart-ID, X-Tenant-ID
- Body: { "coupon_code": "SUMMER2024" }
- Returns: Applied discount details

## Usage Examples

### 1. Get Cart Pricing

```bash
GET /api/cart/pricing
X-Cart-ID: 01234567-89ab-cdef-0123-456789abcdef
X-Tenant-ID: 00000000-0000-4000-8000-000000000001

Response:
{
  "cart_id": "01234567-89ab-cdef-0123-456789abcdef",
  "items": [
    {
      "cart_item_id": "item-123",
      "product_id": "prod-456",
      "quantity": 2,
      "base_unit_price": { "value": "100.00", "currency": "USD" },
      "final_unit_price": { "value": "90.00", "currency": "USD" },
      "row_subtotal": { "value": "200.00", "currency": "USD" },
      "row_total": { "value": "180.00", "currency": "USD" },
      "total_discount": { "value": "20.00", "currency": "USD" },
      "applied_discounts": [
        {
          "id": "pricelist-789",
          "type": "price_list",
          "name": "Holiday Sale",
          "amount": { "value": "20.00", "currency": "USD" },
          "discount_type": "price_list",
          "scope": "item"
        }
      ]
    }
  ],
  "subtotal": { "value": "180.00", "currency": "USD" },
  "total_discounts": { "value": "20.00", "currency": "USD" },
  "grand_total": { "value": "180.00", "currency": "USD" },
  "cart_level_discounts": [],
  "total_items_count": 2
}
```

### 2. Apply Coupon

```bash
POST /api/cart/apply-coupon
X-Cart-ID: 01234567-89ab-cdef-0123-456789abcdef
X-Tenant-ID: 00000000-0000-4000-8000-000000000001
Content-Type: application/json

{
  "coupon_code": "SUMMER2024"
}

Response:
{
  "id": "promotion-123",
  "type": "promotion",
  "name": "Summer Sale 2024",
  "amount": { "value": "18.00", "currency": "USD" },
  "discount_type": "percentage",
  "discount_value": 10.0,
  "scope": "cart"
}
```

### 3. Get Pricing with Coupons

```bash
GET /api/cart/pricing?coupons=SUMMER2024,WELCOME10
X-Cart-ID: 01234567-89ab-cdef-0123-456789abcdef
X-Tenant-ID: 00000000-0000-4000-8000-000000000001

Response:
{
  "cart_id": "01234567-89ab-cdef-0123-456789abcdef",
  "subtotal": { "value": "180.00", "currency": "USD" },
  "total_discounts": { "value": "38.00", "currency": "USD" },
  "grand_total": { "value": "142.00", "currency": "USD" },
  "cart_level_discounts": [
    {
      "id": "promotion-123",
      "type": "promotion",
      "name": "Summer Sale 2024",
      "amount": { "value": "18.00", "currency": "USD" }
    },
    {
      "id": "promotion-456",
      "type": "promotion",
      "name": "Welcome Discount",
      "amount": { "value": "20.00", "currency": "USD" }
    }
  ]
}
```

## Business Rules

### Price Calculation Order

1. **Base Price**: Product's base price from Catalog
2. **Price Lists**: Apply active price lists (priority-ordered)
3. **Catalog-Rule Promotions**: Apply item-level promotions
4. **Cart-Rule Promotions**: Apply cart-level promotions
5. **Final Total**: Subtotal minus all discounts

### Promotion Conditions

Promotions can have conditions that must be met:

- **min_purchase**: Minimum cart subtotal (e.g., $50.00)
- **min_items_count**: Minimum number of items in cart
- **product_ids**: Must contain specific products
- **min_quantity**: Minimum quantity per item

### Coupon Validation

Coupons are validated for:
- Valid coupon code (exists in database)
- Promotion is active
- Promotion is within valid date range
- All promotion conditions are met

### Promotion Stacking

- Multiple promotions can be applied to a cart
- Stacking rules handled by `PromotionStackingService`
- Priority determines application order (higher priority first)
- Max 3 promotions can stack (business rule)

## Testing

### Unit Tests

```bash
# Run all Pricing service tests
vendor/bin/phpunit tests/Unit/Pricing/Application/Service/

# Run CartPricingService tests
vendor/bin/phpunit tests/Unit/Pricing/Application/Service/CartPricingServiceTest.php

# Run PromotionApplicator tests
vendor/bin/phpunit tests/Unit/Pricing/Application/Service/PromotionApplicatorTest.php
```

### Functional Tests

```bash
# Run Cart Pricing API tests
vendor/bin/phpunit tests/Functional/Pricing/CartPricingApiTest.php
```

### Integration Tests

```bash
# Run all integration tests
vendor/bin/phpunit tests/Integration/Pricing/
```

## Error Handling

### Common Errors

**404 Not Found**
- Cart with provided ID not found
- Coupon code not found

**400 Bad Request**
- Invalid coupon code format
- Coupon expired or not active
- Promotion conditions not met (e.g., min_purchase)
- Promotion stacking not allowed

**500 Internal Server Error**
- Database connection issues
- Unexpected errors during calculation

### Error Response Format

```json
{
  "error": "Invalid coupon code: SUMMER2024",
  "status": 400,
  "timestamp": "2025-01-15T10:30:00Z"
}
```

## Performance Considerations

### Caching Strategy

- Cart pricing results should be cached (Redis)
- Cache key: `cart:{cart_id}:pricing:{coupons_hash}`
- TTL: 5 minutes (balance between freshness and performance)
- Invalidate on cart modifications

### Database Queries

- Price lists: Single query with priority ordering
- Promotions: Single query with date range filter
- Cart: Single query with item eager loading

### Optimization Tips

1. Use Redis for active price lists cache
2. Eager load cart items in repository
3. Batch promotion condition checks
4. Cache promotion validation results

## Future Enhancements

### Planned Features

1. **Customer Segment Pricing**: Apply segment-based discounts
2. **Tier Pricing**: Quantity-based price breaks
3. **Bundle Discounts**: Buy X get Y free
4. **Usage Limits**: Max uses per customer/globally
5. **Exclusion Rules**: Prevent certain combinations
6. **A/B Testing**: Test promotion effectiveness

### Extensibility Points

- **Custom Promotion Types**: Extend PromotionType value object
- **Custom Discount Calculators**: Implement Discount strategies
- **Custom Conditions**: Add new promotion conditions
- **Event Subscribers**: React to pricing events

## Related Documentation

- [DDD Patterns Summary](architecture/ddd-patterns-summary.md)
- [Testing Guide](technical/testing-guide.md)
- [API Platform Integration](reference/api-platform/)
- [Multi-tenancy Guide](guides/multi-tenancy.md)
- [CLAUDE.md](/var/www/new_ecom/CLAUDE.md) - Project overview

## Support

For issues or questions:
1. Check this documentation first
2. Review test files for usage examples
3. Consult CLAUDE.md for architecture guidelines
4. Contact the development team
