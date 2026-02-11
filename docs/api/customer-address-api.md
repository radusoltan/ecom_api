# Customer Address API Documentation

## Overview

RESTful API for managing customer shipping and billing addresses with multi-tenant support.

**Base Path**: `/api/v1/customers/{customerId}/addresses`

**Authentication**: Required (JWT Bearer token)

**Multi-Tenancy**: Required (`X-Tenant-ID` header)

**Authorization**: Uses `CustomerVoter` with permissions:
- `customer.view` - View addresses
- `customer.edit` - Create, update, delete addresses

## API Endpoints

### 1. List Customer Addresses

**GET** `/api/v1/customers/{customerId}/addresses`

Retrieve all addresses for a specific customer.

**Parameters:**
- `customerId` (path, required): Customer UUID
- `type` (query, optional): Filter by type (`shipping`, `billing`, `other`)

**Response:** `200 OK`
```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "customerId": "123e4567-e89b-12d3-a456-426614174000",
    "tenantId": "00000000-0000-4000-8000-000000000001",
    "street": "123 Main St",
    "street2": "Apt 4B",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "US",
    "type": "shipping",
    "isDefaultShipping": true,
    "isDefaultBilling": false,
    "createdAt": "2024-01-15T10:30:00+00:00",
    "updatedAt": "2024-01-15T10:30:00+00:00"
  }
]
```

**Example:**
```bash
curl -X GET "http://localhost:8000/api/v1/customers/{customerId}/addresses?type=shipping" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001" \
  -H "Accept: application/json"
```

---

### 2. Get Single Address

**GET** `/api/v1/customers/{customerId}/addresses/{id}`

Retrieve details of a specific address.

**Parameters:**
- `customerId` (path, required): Customer UUID
- `id` (path, required): Address UUID

**Response:** `200 OK`
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customerId": "123e4567-e89b-12d3-a456-426614174000",
  "tenantId": "00000000-0000-4000-8000-000000000001",
  "street": "123 Main St",
  "street2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postalCode": "10001",
  "country": "US",
  "type": "shipping",
  "isDefaultShipping": true,
  "isDefaultBilling": false,
  "createdAt": "2024-01-15T10:30:00+00:00",
  "updatedAt": "2024-01-15T10:30:00+00:00"
}
```

**Errors:**
- `404 Not Found` - Address not found or deleted

---

### 3. Create Address

**POST** `/api/v1/customers/{customerId}/addresses`

Add a new shipping or billing address for a customer.

**Parameters:**
- `customerId` (path, required): Customer UUID

**Request Body:**
```json
{
  "street": "123 Main St",
  "street2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postalCode": "10001",
  "country": "US",
  "type": "shipping",
  "isDefaultShipping": false,
  "isDefaultBilling": false
}
```

**Required Fields:**
- `street` (max 255 chars)
- `city` (max 100 chars)
- `postalCode` (max 20 chars)
- `country` (ISO 3166-1 alpha-2, exactly 2 chars)
- `type` (enum: `shipping`, `billing`, `other`)

**Optional Fields:**
- `street2` (max 255 chars)
- `state` (max 100 chars)
- `isDefaultShipping` (boolean, default: false)
- `isDefaultBilling` (boolean, default: false)

**Response:** `201 Created`
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customerId": "123e4567-e89b-12d3-a456-426614174000",
  "tenantId": "00000000-0000-4000-8000-000000000001",
  "street": "123 Main St",
  "street2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postalCode": "10001",
  "country": "US",
  "type": "shipping",
  "isDefaultShipping": false,
  "isDefaultBilling": false,
  "createdAt": "2024-01-15T10:30:00+00:00",
  "updatedAt": "2024-01-15T10:30:00+00:00"
}
```

**Business Rules:**
- If `isDefaultShipping` is true, automatically unsets previous default shipping address
- If `isDefaultBilling` is true, automatically unsets previous default billing address
- Customer must exist and belong to the tenant
- Address ID is auto-generated (UUID v7)

**Errors:**
- `400 Bad Request` - Missing required fields
- `404 Not Found` - Customer not found
- `422 Unprocessable Entity` - Validation errors

**Example:**
```bash
curl -X POST "http://localhost:8000/api/v1/customers/{customerId}/addresses" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "street": "123 Main St",
    "city": "New York",
    "postalCode": "10001",
    "country": "US",
    "type": "shipping"
  }'
```

---

### 4. Update Address

**PUT** `/api/v1/customers/{customerId}/addresses/{id}`

Update all fields of an existing address.

**Parameters:**
- `customerId` (path, required): Customer UUID
- `id` (path, required): Address UUID

**Request Body:** Same as Create Address (all fields required)

**Response:** `200 OK` (same structure as Create response)

**Business Rules:**
- Same as Create Address
- Address must exist and belong to the customer and tenant
- Cannot update `id`, `customerId`, or `tenantId`

**Errors:**
- `400 Bad Request` - Missing required fields
- `404 Not Found` - Address not found
- `422 Unprocessable Entity` - Validation errors

---

### 5. Delete Address

**DELETE** `/api/v1/customers/{customerId}/addresses/{id}`

Soft-delete an address (marks as deleted, not permanently removed).

**Parameters:**
- `customerId` (path, required): Customer UUID
- `id` (path, required): Address UUID

**Response:** `204 No Content` (empty body)

**Business Rules:**
- Soft delete (sets `isDeleted` flag)
- Deleted addresses are excluded from GET operations
- Address must exist and belong to the customer and tenant

**Errors:**
- `404 Not Found` - Address not found

**Example:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/customers/{customerId}/addresses/{id}" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001"
```

---

### 6. Set Default Address

**PATCH** `/api/v1/customers/{customerId}/addresses/{id}/default`

Set an address as default for shipping or billing.

**Parameters:**
- `customerId` (path, required): Customer UUID
- `id` (path, required): Address UUID

**Request Body:**
```json
{
  "type": "shipping"
}
```

**Fields:**
- `type` (required, enum: `shipping`, `billing`)

**Response:** `200 OK`
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customerId": "123e4567-e89b-12d3-a456-426614174000",
  "tenantId": "00000000-0000-4000-8000-000000000001",
  "street": "123 Main St",
  "street2": "Apt 4B",
  "city": "New York",
  "state": "NY",
  "postalCode": "10001",
  "country": "US",
  "type": "shipping",
  "isDefaultShipping": true,
  "isDefaultBilling": false,
  "createdAt": "2024-01-15T10:30:00+00:00",
  "updatedAt": "2024-01-15T10:35:00+00:00"
}
```

**Business Rules:**
- Automatically unsets previous default of the same type
- Updates `updatedAt` timestamp
- Address must exist and belong to the customer and tenant

**Errors:**
- `400 Bad Request` - Invalid type or missing type field
- `404 Not Found` - Address not found

**Example:**
```bash
curl -X PATCH "http://localhost:8000/api/v1/customers/{customerId}/addresses/{id}/default" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "shipping"}'
```

---

## Error Responses

### Validation Error (422)
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "Validation Error",
  "hydra:description": "Invalid input data",
  "violations": [
    {
      "propertyPath": "country",
      "message": "Country must be exactly 2 characters (ISO 3166-1 alpha-2)",
      "code": "INVALID_LENGTH"
    }
  ]
}
```

### Not Found (404)
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "Not Found",
  "hydra:description": "Address not found"
}
```

### Forbidden (403)
```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "Forbidden",
  "hydra:description": "Insufficient permissions"
}
```

---

## Architecture

### Application Layer

**Commands:**
- `AddAddress` - Create new address
- `UpdateAddress` - Update existing address
- `RemoveAddress` - Soft-delete address
- `SetDefaultAddress` - Set default shipping/billing

**Queries:**
- `GetCustomerAddresses` - Retrieve customer's addresses (with optional type filter)
- `GetAddressById` - Retrieve single address

**DTOs:**
- `CustomerAddressDTO` - Data transfer object for addresses

### Presentation Layer

**API Resource:**
- `CustomerAddressResource` - API Platform resource with operations and OpenAPI documentation

**Processors:**
- `AddAddressProcessor` - Handles POST requests
- `UpdateAddressProcessor` - Handles PUT requests
- `RemoveAddressProcessor` - Handles DELETE requests
- `SetDefaultAddressProcessor` - Handles PATCH requests for default addresses

**Providers:**
- `CustomerAddressCollectionProvider` - Handles GET collection requests
- `CustomerAddressItemProvider` - Handles GET item requests

### Infrastructure Layer

**Entity:**
- `CustomerAddressEntity` - Doctrine ORM entity with database mappings

**Database Table:** `customer_addresses`

**Indexes:**
- `customer_id` - For querying customer's addresses
- `tenant_id` - For multi-tenant isolation
- `type` - For filtering by type
- `is_default_shipping` - For finding default shipping address
- `is_default_billing` - For finding default billing address
- `is_deleted` - For excluding deleted addresses

---

## Security

### Multi-Tenancy
- All operations require `X-Tenant-ID` header
- PostgreSQL RLS enforces tenant isolation at database level
- Tenant context is validated for each request

### Authorization
- Uses `CustomerVoter` for permission checks
- Required permissions:
  - `customer.view` - GET operations
  - `customer.edit` - POST, PUT, DELETE, PATCH operations

### Permission Matrix

| Role | View | Create | Update | Delete | Set Default |
|------|------|--------|--------|--------|-------------|
| SUPER_ADMIN | ✅ | ✅ | ✅ | ✅ | ✅ |
| ADMIN | ✅ | ✅ | ✅ | ✅ | ✅ |
| MANAGER | ✅ | ✅ | ✅ | ✅ | ✅ |
| TENANT_ADMIN | ✅ | ✅ | ✅ | ✅ | ✅ |
| VIEWER | ✅ | ❌ | ❌ | ❌ | ❌ |
| CUSTOMER | ✅ (own) | ✅ (own) | ✅ (own) | ✅ (own) | ✅ (own) |

---

## Implementation Files

### Application Layer
- `src/Customer/Application/Command/AddAddress/AddAddress.php`
- `src/Customer/Application/Command/AddAddress/AddAddressHandler.php`
- `src/Customer/Application/Command/UpdateAddress/UpdateAddress.php`
- `src/Customer/Application/Command/UpdateAddress/UpdateAddressHandler.php`
- `src/Customer/Application/Command/RemoveAddress/RemoveAddress.php`
- `src/Customer/Application/Command/RemoveAddress/RemoveAddressHandler.php`
- `src/Customer/Application/Command/SetDefaultAddress/SetDefaultAddress.php`
- `src/Customer/Application/Command/SetDefaultAddress/SetDefaultAddressHandler.php`
- `src/Customer/Application/Query/GetCustomerAddresses/GetCustomerAddresses.php`
- `src/Customer/Application/Query/GetCustomerAddresses/GetCustomerAddressesHandler.php`
- `src/Customer/Application/Query/GetAddressById/GetAddressById.php`
- `src/Customer/Application/Query/GetAddressById/GetAddressByIdHandler.php`
- `src/Customer/Application/DTO/CustomerAddressDTO.php`

### Presentation Layer
- `src/Customer/Presentation/Api/Resource/CustomerAddressResource.php`
- `src/Customer/Presentation/Api/Processor/AddAddressProcessor.php`
- `src/Customer/Presentation/Api/Processor/UpdateAddressProcessor.php`
- `src/Customer/Presentation/Api/Processor/RemoveAddressProcessor.php`
- `src/Customer/Presentation/Api/Processor/SetDefaultAddressProcessor.php`
- `src/Customer/Presentation/Api/Provider/CustomerAddressCollectionProvider.php`
- `src/Customer/Presentation/Api/Provider/CustomerAddressItemProvider.php`

### Infrastructure Layer
- `src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerAddressEntity.php` (already existed, updated)

---

## OpenAPI Documentation

Full OpenAPI specification is available at:
- **Swagger UI**: `http://localhost:8000/api/docs`
- **JSON**: `http://localhost:8000/api/docs.json`
- **YAML**: `http://localhost:8000/api/docs.yaml`

To regenerate OpenAPI spec:
```bash
symfony console api:openapi:export --yaml > openapi.yaml
symfony console api:openapi:export > openapi.json
```

---

## Testing

### Manual Testing

1. **Create test customer** (if needed):
```bash
curl -X POST "http://localhost:8000/api/v1/customers" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

2. **Create address**:
```bash
curl -X POST "http://localhost:8000/api/v1/customers/{customerId}/addresses" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001" \
  -H "Content-Type: application/json" \
  -d '{
    "street": "123 Main St",
    "city": "New York",
    "postalCode": "10001",
    "country": "US",
    "type": "shipping"
  }'
```

3. **List addresses**:
```bash
curl -X GET "http://localhost:8000/api/v1/customers/{customerId}/addresses" \
  -H "X-Tenant-ID: 00000000-0000-4000-8000-000000000001"
```

### Automated Testing

Run functional tests:
```bash
vendor/bin/phpunit tests/Functional/Api/CustomerAddressApiTest.php
```

---

## Notes

- All addresses use soft-delete (not permanently removed from database)
- Default addresses are managed automatically (only one default per type)
- Address IDs are generated as UUID v7 for time-ordered sortability
- Timestamps are in ISO 8601 format with timezone
- Country codes must be ISO 3166-1 alpha-2 (e.g., "US", "GB", "FR")
