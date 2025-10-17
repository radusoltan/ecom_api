# Storefront API Documentation

## Overview

The Storefront API provides optimized, read-only endpoints designed for public-facing e-commerce storefronts. These endpoints are specifically tailored for performance with built-in caching, CDN support, and locale-aware content delivery.

## Features

- **Performance Optimized**: Built-in HTTP caching with ETag support
- **CDN Ready**: Cache-Control headers configured for edge caching
- **Multi-tenant**: Complete tenant isolation
- **Internationalization**: Accept-Language header support
- **SEO Friendly**: Proper cache headers and metadata
- **Pagination**: Efficient pagination for large product catalogs
- **Filtering**: Product filtering by category, price, search query
- **Sorting**: Multiple sort options (newest, price, name)

## Base URL

```
/api/storefront
```

## Authentication

Storefront endpoints are public but require the `X-Tenant-ID` header for multi-tenant isolation.

## Common Headers

### Request Headers

| Header | Required | Description | Example |
|--------|----------|-------------|---------|
| `X-Tenant-ID` | Yes | Tenant UUID for isolation | `550e8400-e29b-41d4-a716-446655440000` |
| `Accept-Language` | No | Preferred language (default: en) | `en-US,en;q=0.9` |
| `If-None-Match` | No | ETag for cache validation | `"abc123..."` |

### Response Headers

| Header | Description |
|--------|-------------|
| `Cache-Control` | Cache directives for browsers and CDN |
| `Vary` | Varies by Accept-Language and X-Tenant-ID |
| `ETag` | Entity tag for cache validation |
| `Last-Modified` | Last modification timestamp |
| `X-Content-Language` | Resolved content language |

## Endpoints

### 1. Featured Products

Get products marked as featured for homepage display.

**Endpoint**: `GET /api/storefront/featured-products`

**Query Parameters**:
- `limit` (optional, default: 8, max: 20) - Number of products to return

**Cache**: 5 minutes (max-age=300, stale-while-revalidate=600)

**Example Request**:
```bash
curl -X GET 'http://localhost:8000/api/storefront/featured-products?limit=8' \
  -H 'X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000' \
  -H 'Accept-Language: en-US,en;q=0.9'
```

**Example Response** (200 OK):
```json
[
  {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "slug": "premium-wireless-headphones",
    "name": "Premium Wireless Headphones",
    "price": {
      "amount": 29999,
      "currency": "USD"
    },
    "primaryImage": {
      "urlSm": "/media/originals/.../thumb_sm.jpg",
      "urlMd": "/media/originals/.../thumb_md.jpg",
      "urlLg": "/media/originals/.../thumb_lg.jpg"
    },
    "isFeatured": true,
    "rating": 4.5,
    "availability": "in_stock",
    "description": "High-quality wireless headphones with noise cancellation"
  }
]
```

### 2. Home Categories

Get categories marked as "show on front" for homepage display.

**Endpoint**: `GET /api/storefront/home-categories`

**Query Parameters**:
- `limit` (optional, default: 12, max: 20) - Number of categories to return

**Cache**: 5 minutes (max-age=300, stale-while-revalidate=600)

**Example Request**:
```bash
curl -X GET 'http://localhost:8000/api/storefront/home-categories?limit=12' \
  -H 'X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000' \
  -H 'Accept-Language: en-US,en;q=0.9'
```

**Example Response** (200 OK):
```json
[
  {
    "id": "234e5678-e89b-12d3-a456-426614174000",
    "slug": "electronics",
    "name": "Electronics",
    "image": {
      "urlSm": "/media/originals/.../thumb_sm.jpg",
      "urlMd": "/media/originals/.../thumb_md.jpg",
      "urlLg": "/media/originals/.../thumb_lg.jpg"
    },
    "showOnFront": true,
    "childrenCount": 15,
    "description": "Browse our wide selection of electronics"
  }
]
```

### 3. Product Listing

Get paginated product listing with filters, sorting, and facets.

**Endpoint**: `GET /api/storefront/products`

**Query Parameters**:

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `q` | string | Search query | `wireless headphones` |
| `category` | UUID | Filter by category ID | `234e5678-e89b-12d3-...` |
| `priceMin` | integer | Minimum price in cents | `1000` |
| `priceMax` | integer | Maximum price in cents | `50000` |
| `sort` | string | Sort option | `newest`, `price_asc`, `price_desc`, `name` |
| `page` | integer | Page number (default: 1) | `2` |
| `itemsPerPage` | integer | Items per page (default: 24, max: 48) | `24` |

**Cache**: 2 minutes (max-age=120, stale-while-revalidate=600)

**Example Request**:
```bash
curl -X GET 'http://localhost:8000/api/storefront/products?q=headphones&sort=price_asc&page=1&itemsPerPage=24' \
  -H 'X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000' \
  -H 'Accept-Language: en-US,en;q=0.9'
```

**Example Response** (200 OK):
```json
{
  "data": [
    {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "slug": "premium-wireless-headphones",
      "name": "Premium Wireless Headphones",
      "price": {
        "amount": 29999,
        "currency": "USD"
      },
      "primaryImage": {
        "urlSm": "/media/originals/.../thumb_sm.jpg",
        "urlMd": "/media/originals/.../thumb_md.jpg",
        "urlLg": "/media/originals/.../thumb_lg.jpg"
      },
      "isFeatured": false,
      "rating": 4.5,
      "availability": "in_stock",
      "description": "High-quality wireless headphones"
    }
  ],
  "meta": {
    "total": 156,
    "page": 1,
    "itemsPerPage": 24,
    "totalPages": 7
  },
  "facets": {
    "categories": [],
    "priceRanges": [],
    "attributes": []
  }
}
```

## Caching Strategy

### HTTP Caching

All storefront endpoints implement HTTP caching:

1. **ETags**: Each response includes an ETag header for cache validation
2. **Cache-Control**: Public caching with appropriate max-age
3. **Stale-While-Revalidate**: Allows serving stale content while revalidating
4. **Vary**: Responses vary by Accept-Language and X-Tenant-ID headers

### Cache Durations

| Endpoint | max-age | stale-while-revalidate |
|----------|---------|------------------------|
| Featured Products | 5 minutes | 10 minutes |
| Home Categories | 5 minutes | 10 minutes |
| Product Listing | 2 minutes | 10 minutes |

### CDN Configuration

For optimal performance, configure your CDN to:

1. **Respect Cache-Control headers**
2. **Use ETags for revalidation**
3. **Include Vary headers in cache key**
4. **Set edge TTL to match max-age**

Example Cloudflare configuration:
```
Cache-Control: public, max-age=300, stale-while-revalidate=600
```

Example Nginx configuration:
```nginx
location /api/storefront/ {
    proxy_pass http://backend;
    proxy_cache_valid 200 5m;
    proxy_cache_use_stale error timeout updating;
    proxy_cache_key "$scheme$request_method$host$request_uri$http_accept_language$http_x_tenant_id";
    add_header X-Cache-Status $upstream_cache_status;
}
```

## Frontend Integration

### React/Next.js Example

```jsx
// Featured Products Component
async function getFeaturedProducts(tenantId, locale = 'en') {
  const response = await fetch('/api/storefront/featured-products', {
    headers: {
      'X-Tenant-ID': tenantId,
      'Accept-Language': locale
    },
    next: { revalidate: 300 } // Next.js ISR - 5 minutes
  });

  if (!response.ok) {
    throw new Error('Failed to fetch featured products');
  }

  return response.json();
}

// Product Listing with Filters
async function getProducts(filters) {
  const { tenantId, locale, q, category, priceMin, priceMax, sort, page } = filters;

  const params = new URLSearchParams();
  if (q) params.append('q', q);
  if (category) params.append('category', category);
  if (priceMin) params.append('priceMin', priceMin);
  if (priceMax) params.append('priceMax', priceMax);
  if (sort) params.append('sort', sort);
  if (page) params.append('page', page);

  const response = await fetch(`/api/storefront/products?${params}`, {
    headers: {
      'X-Tenant-ID': tenantId,
      'Accept-Language': locale
    },
    next: { revalidate: 120 } // Next.js ISR - 2 minutes
  });

  return response.json();
}
```

### Vue.js Example

```javascript
// Composable for storefront API
export function useStorefront(tenantId) {
  const locale = ref('en');

  async function getFeaturedProducts() {
    const response = await fetch('/api/storefront/featured-products', {
      headers: {
        'X-Tenant-ID': tenantId,
        'Accept-Language': locale.value
      }
    });
    return response.json();
  }

  async function getHomeCategories() {
    const response = await fetch('/api/storefront/home-categories', {
      headers: {
        'X-Tenant-ID': tenantId,
        'Accept-Language': locale.value
      }
    });
    return response.json();
  }

  return {
    getFeaturedProducts,
    getHomeCategories
  };
}
```

## Error Responses

### 400 Bad Request
Missing or invalid tenant ID:
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "An error occurred",
  "hydra:description": "X-Tenant-ID header is required"
}
```

### 404 Not Found
Endpoint not found:
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "An error occurred",
  "hydra:description": "No route found for \"GET /api/storefront/invalid\""
}
```

## Performance Optimization

### Best Practices

1. **Always include Accept-Language header** for better cache hit ratio
2. **Use ETag validation** to reduce bandwidth
3. **Implement client-side caching** (5-10 minutes)
4. **Prefetch on hover** for product links
5. **Use loading="lazy"** for images below the fold
6. **Implement pagination** instead of infinite scroll for better SEO

### Performance Metrics

Target performance metrics:

| Metric | Target | Description |
|--------|--------|-------------|
| TTFB | < 200ms | Time to first byte (cached) |
| TTFB | < 500ms | Time to first byte (uncached) |
| Response Size | < 100KB | Average response size |
| Cache Hit Rate | > 80% | Percentage of cached responses |

## SEO Considerations

### Structured Data

When rendering product listings in frontend:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "itemListElement": [
    {
      "@type": "Product",
      "position": 1,
      "name": "Premium Wireless Headphones",
      "offers": {
        "@type": "Offer",
        "price": "299.99",
        "priceCurrency": "USD",
        "availability": "https://schema.org/InStock"
      }
    }
  ]
}
</script>
```

### Meta Tags

```html
<meta property="og:type" content="website" />
<meta property="og:title" content="Premium Wireless Headphones" />
<meta property="og:description" content="High-quality wireless headphones with noise cancellation" />
<meta property="og:image" content="https://example.com/media/..." />
<link rel="canonical" href="https://example.com/products/wireless-headphones" />
<link rel="alternate" hreflang="en" href="https://example.com/en/products/..." />
<link rel="alternate" hreflang="fr" href="https://example.com/fr/products/..." />
```

## Rate Limiting

Storefront endpoints have generous rate limits:

- **Anonymous**: 1000 requests per minute per IP
- **With API Key**: 5000 requests per minute

## Monitoring

Monitor these metrics for optimal performance:

1. **Cache Hit Rate**: Should be > 80%
2. **Response Time p95**: Should be < 500ms
3. **Error Rate**: Should be < 1%
4. **Stale Responses**: Monitor stale-while-revalidate usage

## Troubleshooting

### Low Cache Hit Rate

**Causes**:
- Accept-Language header varies too much
- Tenant-specific content not cached properly
- Cache TTL too short

**Solutions**:
- Normalize Accept-Language to supported locales only
- Ensure X-Tenant-ID is included in cache key
- Increase cache TTL if appropriate

### Slow Response Times

**Causes**:
- Database queries not optimized
- Missing indexes
- Large result sets

**Solutions**:
- Add database indexes for frequently queried fields
- Implement Redis caching layer
- Reduce page size
- Use Elasticsearch for search queries

## Migration Guide

If migrating from an existing storefront API:

1. **Update endpoints**: Map old endpoints to new `/api/storefront/*` endpoints
2. **Add required headers**: Ensure X-Tenant-ID is included in all requests
3. **Update response parsing**: Adapt to new DTO structure
4. **Implement caching**: Leverage ETag and Cache-Control headers
5. **Test locale handling**: Verify Accept-Language header support

## Future Enhancements

Planned improvements:

- [ ] Elasticsearch integration for advanced search
- [ ] Faceted navigation with aggregations
- [ ] Personalized recommendations
- [ ] Real-time inventory status
- [ ] Advanced filtering (attributes, ratings)
- [ ] GraphQL endpoint alternative
- [ ] Wishlist and saved searches
- [ ] Recently viewed products