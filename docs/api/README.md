# API Documentation

REST API documentation for the e-commerce platform.

## Available APIs

### 🛒 [Cart API](cart.md)
Shopping cart management, add/remove items, checkout preparation.

**Key Endpoints:**
- `POST /api/carts` - Create cart
- `POST /api/carts/{id}/items` - Add item
- `GET /api/carts/{id}` - Get cart details

---

### 📦 [Order API](order.md)
Order placement, tracking, and management.

**Key Endpoints:**
- `POST /api/orders` - Place order
- `GET /api/orders/{id}` - Get order details
- `PATCH /api/orders/{id}/cancel` - Cancel order

---

### 🖼️ [Media API](media.md)
Image uploads, thumbnails, multi-tenant media management.

**Key Endpoints:**
- `POST /api/media` - Upload image
- `GET /api/media/{id}` - Get media details
- `DELETE /api/media/{id}` - Delete media

---

### 🏪 [Storefront API](storefront.md)
Public-facing catalog API for storefront.

**Key Endpoints:**
- `GET /api/products` - List products
- `GET /api/products/{id}` - Product details
- `GET /api/categories` - List categories

---

## General API Information

### Base URL
```
http://localhost:8000/api
```

### Authentication
```http
Authorization: Bearer {jwt-token}
```

### Required Headers
```http
X-Tenant-ID: {tenant-uuid}
Content-Type: application/json
Accept: application/json
```

### Response Format
All responses follow JSON:API format:

```json
{
  "@context": "/api/contexts/Product",
  "@id": "/api/products/123",
  "@type": "Product",
  "id": "123",
  "name": "Product Name"
}
```

### Error Handling
Errors follow RFC 7807 Problem Details:

```json
{
  "type": "https://example.com/probs/out-of-stock",
  "title": "Product out of stock",
  "status": 400,
  "detail": "Product XYZ is currently out of stock"
}
```

### Pagination
Collections are paginated:

```json
{
  "hydra:member": [...],
  "hydra:totalItems": 100,
  "hydra:view": {
    "hydra:first": "/api/products?page=1",
    "hydra:last": "/api/products?page=10",
    "hydra:next": "/api/products?page=3"
  }
}
```

## OpenAPI Documentation

Interactive API documentation available at:
- **Swagger UI**: http://localhost:8000/api/docs
- **GraphQL Playground**: http://localhost:8000/api/graphql

## Testing

Example with cURL:

```bash
# Get products
curl -X GET "http://localhost:8000/api/products" \
  -H "X-Tenant-ID: your-tenant-id" \
  -H "Accept: application/json"

# Create order
curl -X POST "http://localhost:8000/api/orders" \
  -H "X-Tenant-ID: your-tenant-id" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token" \
  -d '{
    "items": [
      {"productId": "123", "quantity": 2}
    ]
  }'
```

---

**Last Updated**: 2025-11-06
