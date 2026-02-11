# Invoice API Platform Integration

## Overview

This directory contains the API Platform integration for the Invoice bounded context, providing REST API endpoints for invoice management including CRUD operations and PDF download functionality.

## Architecture

Following the **Hexagonal Architecture** pattern:
- **State Processors**: Handle write operations (POST requests)
- **State Providers**: Handle read operations (GET requests for special cases like PDF download)
- **DTOs**: Request/Response data transfer objects with validation
- **Entity**: InvoiceEntity with API Platform attributes and serialization groups

## API Endpoints

### 1. List Invoices
```http
GET /api/invoices
```

**Security**: Requires `ROLE_USER`

**Response**: Paginated collection of invoices
```json
{
  "@context": "/api/contexts/InvoiceEntity",
  "@id": "/api/invoices",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/api/invoices/{id}",
      "@type": "InvoiceEntity",
      "id": "01234567-89ab-cdef-0123-456789abcdef",
      "tenantId": "01234567-89ab-cdef-0123-456789abcdef",
      "orderId": "01234567-89ab-cdef-0123-456789abcdef",
      "customerId": "01234567-89ab-cdef-0123-456789abcdef",
      "invoiceNumber": "INV-2025-000001",
      "status": "issued",
      "billingAddress": {
        "name": "John Doe",
        "addressLine1": "123 Main St",
        "addressLine2": null,
        "city": "New York",
        "postalCode": "10001",
        "country": "US",
        "vatNumber": "US123456789"
      },
      "subtotalAmount": 10000,
      "subtotalCurrency": "USD",
      "taxAmount": 1900,
      "taxCurrency": "USD",
      "totalAmount": 11900,
      "totalCurrency": "USD",
      "taxBreakdown": {
        "19.0": 1900
      },
      "isReverseCharge": false,
      "issueDate": "2025-11-28T10:00:00+00:00",
      "dueDate": "2025-12-28T10:00:00+00:00",
      "paidDate": null,
      "pdfPath": "/storage/invoices/2025/INV-2025-000001.pdf",
      "creditedInvoiceId": null,
      "notes": null,
      "createdAt": "2025-11-28T09:30:00+00:00",
      "updatedAt": "2025-11-28T10:00:00+00:00",
      "lines": [
        {
          "id": "01234567-89ab-cdef-0123-456789abcdef",
          "productId": "01234567-89ab-cdef-0123-456789abcdef",
          "sku": "PROD-001",
          "description": "Product Name",
          "quantity": 2,
          "unitPriceAmount": 5000,
          "unitPriceCurrency": "USD",
          "taxRate": 0.19,
          "taxAmount": 1900,
          "taxCurrency": "USD",
          "lineTotalAmount": 11900,
          "lineTotalCurrency": "USD",
          "position": 1
        }
      ]
    }
  ],
  "hydra:totalItems": 42,
  "hydra:view": {
    "@id": "/api/invoices?page=1",
    "@type": "hydra:PartialCollectionView",
    "hydra:first": "/api/invoices?page=1",
    "hydra:last": "/api/invoices?page=2",
    "hydra:next": "/api/invoices?page=2"
  }
}
```

**Pagination**: 30 items per page (default)

---

### 2. Get Single Invoice
```http
GET /api/invoices/{id}
```

**Security**: Requires `ROLE_USER`

**Response**: Single invoice entity (same structure as collection item)

---

### 3. Create Draft Invoice
```http
POST /api/invoices
```

**Security**: Requires `ROLE_ADMIN` or `ROLE_MANAGER`

**Request Body** (`CreateInvoiceRequest`):
```json
{
  "orderId": "01234567-89ab-cdef-0123-456789abcdef",
  "customerId": "01234567-89ab-cdef-0123-456789abcdef",
  "billingAddress": {
    "name": "John Doe",
    "addressLine1": "123 Main St",
    "addressLine2": "Apt 4B",
    "city": "New York",
    "postalCode": "10001",
    "country": "US",
    "vatNumber": "US123456789"
  },
  "lines": [
    {
      "description": "Product Name",
      "quantity": 2,
      "unitPriceAmount": 5000,
      "unitPriceCurrency": "USD",
      "taxRate": 0.19,
      "productId": "01234567-89ab-cdef-0123-456789abcdef",
      "sku": "PROD-001"
    }
  ],
  "isReverseCharge": false,
  "notes": "Optional notes"
}
```

**Response**: Created invoice in DRAFT status (201 Created)

**Processor**: `CreateInvoiceProcessor`

**Command**: `GenerateInvoiceCommand`

**Business Rules**:
- Invoice starts in DRAFT status
- At least one line item is required
- Billing address must be valid
- Reverse charge applies when VAT number is provided and country differs from tenant

---

### 4. Issue Draft Invoice
```http
POST /api/invoices/{id}/issue
```

**Security**: Requires `ROLE_ADMIN` or `ROLE_MANAGER`

**Request Body** (`IssueInvoiceRequest`):
```json
{
  "dueDate": "2025-12-31T23:59:59Z"
}
```

**Response**: Updated invoice in ISSUED status (200 OK)

**Processor**: `IssueInvoiceProcessor`

**Command**: `IssueInvoiceCommand`

**Business Rules**:
- Only draft invoices can be issued
- Assigns a sequential invoice number
- Generates PDF document
- Changes status from DRAFT to ISSUED
- Invoice becomes immutable after issue
- Due date defaults to issue date + 30 days if not provided

---

### 5. Mark Invoice as Paid
```http
POST /api/invoices/{id}/paid
```

**Security**: Requires `ROLE_ADMIN` or `ROLE_MANAGER`

**Request Body** (`MarkInvoicePaidRequest`):
```json
{
  "paidDate": "2025-12-01T14:30:00Z"
}
```

**Response**: Updated invoice in PAID status (200 OK)

**Processor**: `MarkInvoicePaidProcessor`

**Command**: `MarkInvoicePaidCommand`

**Business Rules**:
- Only issued invoices can be marked as paid
- Paid date cannot be before issue date
- Invoice cannot be modified after being marked as paid

---

### 6. Cancel Invoice
```http
POST /api/invoices/{id}/cancel
```

**Security**: Requires `ROLE_ADMIN` or `ROLE_MANAGER`

**Response**: Updated invoice in CANCELLED status (200 OK)

**Processor**: `CancelInvoiceProcessor`

**Command**: `CancelInvoiceCommand`

**Business Rules**:
- Only issued invoices can be cancelled
- Paid invoices cannot be cancelled (use credit note instead)
- Draft invoices should be deleted, not cancelled
- Cancelled invoices remain in the system for audit purposes

---

### 7. Download Invoice PDF
```http
GET /api/invoices/{id}/pdf?locale=en
```

**Security**: Requires `ROLE_USER`

**Query Parameters**:
- `locale` (optional): Locale for PDF generation (default: `en`)

**Response**: PDF file stream

**Headers**:
```
Content-Type: application/pdf
Content-Disposition: inline; filename="INV-2025-000001.pdf"
Cache-Control: private, max-age=3600
```

**Provider**: `InvoicePdfProvider`

**Query**: `DownloadInvoicePdfQuery`

**Business Rules**:
- PDF is generated on-the-fly if not already cached
- Generated PDFs can be cached on filesystem
- Supports localization (invoice in customer's language)

---

## File Structure

```
src/Invoice/Infrastructure/ApiPlatform/
├── Dto/
│   ├── CreateInvoiceRequest.php      # Request DTO for creating draft invoices
│   ├── InvoiceLineRequest.php        # Request DTO for invoice line items
│   ├── IssueInvoiceRequest.php       # Request DTO for issuing invoices
│   └── MarkInvoicePaidRequest.php    # Request DTO for marking invoices paid
└── State/
    ├── CreateInvoiceProcessor.php    # POST /api/invoices
    ├── IssueInvoiceProcessor.php     # POST /api/invoices/{id}/issue
    ├── MarkInvoicePaidProcessor.php  # POST /api/invoices/{id}/paid
    ├── CancelInvoiceProcessor.php    # POST /api/invoices/{id}/cancel
    └── InvoicePdfProvider.php        # GET /api/invoices/{id}/pdf
```

## Serialization Groups

- **invoice:read**: All properties of InvoiceEntity and InvoiceLineEntity (for GET responses)
- **invoice:write**: Request properties for CreateInvoiceRequest and other request DTOs

## Validation

All request DTOs use Symfony Validator constraints:

### CreateInvoiceRequest
- `orderId`: NotBlank, Uuid
- `customerId`: NotBlank, Uuid
- `billingAddress`: NotBlank, Collection (with nested validations)
- `lines`: NotBlank, Count(min=1), Valid
- `notes`: Length(max=5000)

### InvoiceLineRequest
- `description`: NotBlank, Length(max=500)
- `quantity`: NotBlank, GreaterThan(0)
- `unitPriceAmount`: NotBlank, GreaterThan(0)
- `unitPriceCurrency`: NotBlank, Currency
- `taxRate`: NotBlank, GreaterThanOrEqual(0), LessThanOrEqual(1)
- `productId`: Uuid (optional)
- `sku`: Length(max=255) (optional)

### IssueInvoiceRequest
- `dueDate`: DateTime (optional)

### MarkInvoicePaidRequest
- `paidDate`: NotBlank, DateTime

## Error Responses

All processors follow RFC 7807 error format:

```json
{
  "@context": "/api/contexts/Error",
  "@type": "hydra:Error",
  "hydra:title": "An error occurred",
  "hydra:description": "Invoice not found",
  "trace": []
}
```

**Common HTTP Status Codes**:
- `200 OK`: Successful GET/POST operation
- `201 Created`: Successful invoice creation
- `400 Bad Request`: Invalid request data or missing required fields
- `401 Unauthorized`: Missing or invalid authentication token
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Invoice not found
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Server-side error

## Multi-Tenancy

All endpoints respect multi-tenancy via:
- `X-Tenant-ID` header (injected by `TenantContextProcessor`)
- PostgreSQL Row-Level Security (RLS) policies
- Tenant context validation in processors

## Testing

PHPStan Level 8 compliance:
```bash
vendor/bin/phpstan analyse src/Invoice/Infrastructure/ApiPlatform --level=8
```

## Integration with Application Layer

All processors delegate to the application layer:
- **Write operations** → Commands via `MessageBusInterface`
- **Read operations** → Queries via `MessageBusInterface`
- **No business logic** in processors (thin controllers)

## Related Documentation

- Command Handlers: `src/Invoice/Application/Command/*/`
- Query Handlers: `src/Invoice/Application/Query/*/`
- Domain Models: `src/Invoice/Domain/Model/`
- Repository: `src/Invoice/Domain/Repository/InvoiceRepositoryInterface.php`
- Entity Mapping: `src/Invoice/Infrastructure/Persistence/Doctrine/Entity/`
