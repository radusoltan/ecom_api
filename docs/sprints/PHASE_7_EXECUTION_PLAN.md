# Phase 7: Customer Context - Execution Plan

**Version:** 1.0
**Created:** 2025-11-28
**Duration:** 6 weeks (3 sprints)
**Working Directory:** `/var/www/new_ecom/backend`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Agent Assignments](#2-agent-assignments)
3. [Sprint 7.1 Execution Tasks](#3-sprint-71-execution-tasks)
4. [Sprint 7.2 Execution Tasks](#4-sprint-72-execution-tasks)
5. [Sprint 7.3 Execution Tasks](#5-sprint-73-execution-tasks)
6. [Quality Gates](#6-quality-gates)
7. [Execution Commands](#7-execution-commands)
8. [Rollback Plan](#8-rollback-plan)

---

## 1. Executive Summary

### Phase Overview
Complete the Customer bounded context with advanced features: address management, loyalty programs, GDPR compliance, and notification preferences.

### Timeline
| Sprint | Focus | Duration | Story Points |
|--------|-------|----------|--------------|
| 7.1 | Addresses & Enhanced Profile | 2 weeks | 34 SP |
| 7.2 | Loyalty Programs & Points | 2 weeks | 42 SP |
| 7.3 | GDPR & Notifications | 2 weeks | 38 SP |

### Success Criteria
- [ ] All 114 story points delivered
- [ ] Test coverage >= 90%
- [ ] PHPStan level 8: 0 errors
- [ ] Deptrac: 0 violations
- [ ] API response time < 200ms (p95)

---

## 2. Agent Assignments

### Available Specialized Agents

| Agent | Responsibilities | Use For |
|-------|-----------------|---------|
| **DDD Architecture Specialist** | Domain models, aggregates, value objects, bounded contexts | Domain layer design, aggregate roots, domain events |
| **PHP/Symfony Specialist** | PHP 8.3, Symfony 7.3, services, handlers | Command/Query handlers, services, Doctrine entities |
| **API Designer** | REST API, API Platform, OpenAPI | API resources, processors, providers, documentation |
| **Database Engineer** | PostgreSQL, migrations, RLS, indexes | Database schema, migrations, query optimization |
| **Test Engineer** | PHPUnit, test coverage | Unit, integration, functional tests |
| **Security Auditor** | Auth, GDPR, data protection | GDPR compliance, anonymization, consent management |
| **Frontend Specialist** | Next.js 15, React, TypeScript | Admin UI, Storefront components |
| **Code Reviewer** | Code quality, DDD compliance | Code review, architecture validation |
| **Documentation Writer** | Technical docs, API docs | Documentation, guides |

---

## 3. Sprint 7.1 Execution Tasks

### Week 1-2: Customer Addresses & Enhanced Profile

---

### TASK 7.1.1: Customer Address Domain Model
**Agent:** `DDD Architecture Specialist`
**Priority:** P0
**Estimated Effort:** 8 hours

**Instructions:**
```
Create the domain layer for Customer Address management following DDD patterns.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- src/Customer/Domain/Model/Customer.php (understand current aggregate)
- src/Shared/Domain/ValueObject/Address.php (if exists, for reference)
- CLAUDE.md (architecture patterns)

Files to CREATE:

1. src/Customer/Domain/ValueObject/CustomerAddressId.php
   - UUID v7 value object
   - Factory methods: generate(), fromString()
   - Method: toString(), equals()

2. src/Customer/Domain/ValueObject/CustomerAddress.php
   - Immutable value object with properties:
     - id: CustomerAddressId
     - street: string (max 255)
     - street2: ?string (max 255)
     - city: string (max 100)
     - state: ?string (max 100)
     - postalCode: string (max 20)
     - country: string (ISO 2-letter code)
     - type: AddressType enum (shipping, billing, both)
     - isDefaultShipping: bool
     - isDefaultBilling: bool
   - Validation in constructor:
     - street required, non-empty
     - city required, non-empty
     - postalCode required, valid format per country
     - country must be valid ISO code
   - Factory method: create()
   - Method: equals(), toArray()

3. src/Customer/Domain/ValueObject/AddressType.php
   - Enum: SHIPPING, BILLING, BOTH
   - Methods: toString(), fromString()

4. Extend src/Customer/Domain/Model/Customer.php:
   - Add private array $addresses = []
   - Add methods:
     - addAddress(CustomerAddress $address): void
     - updateAddress(CustomerAddressId $id, CustomerAddress $address): void
     - removeAddress(CustomerAddressId $id): void
     - setDefaultShippingAddress(CustomerAddressId $id): void
     - setDefaultBillingAddress(CustomerAddressId $id): void
     - getAddresses(): array
     - getDefaultShippingAddress(): ?CustomerAddress
     - getDefaultBillingAddress(): ?CustomerAddress
   - Business rules:
     - Maximum 10 addresses per customer
     - Only one default shipping, one default billing
     - Cannot remove address if it's used in pending orders (check via domain service)
   - Record domain events:
     - CustomerAddressAdded
     - CustomerAddressUpdated
     - CustomerAddressRemoved
     - DefaultAddressChanged

5. Create domain events in src/Customer/Domain/Event/:
   - CustomerAddressAdded.php
   - CustomerAddressUpdated.php
   - CustomerAddressRemoved.php
   - DefaultAddressChanged.php

Acceptance Criteria:
- [ ] All value objects are immutable
- [ ] Validation throws DomainException with clear messages
- [ ] Maximum 10 addresses enforced
- [ ] Only one default per type
- [ ] Domain events recorded correctly
- [ ] No framework dependencies in domain layer
```

---

### TASK 7.1.2: Customer Address Infrastructure
**Agent:** `PHP/Symfony Specialist`
**Priority:** P0
**Estimated Effort:** 6 hours

**Instructions:**
```
Create the infrastructure layer for Customer Address persistence.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerEntity.php
- src/Customer/Domain/ValueObject/CustomerAddress.php (created in TASK 7.1.1)
- config/packages/doctrine.yaml

Files to CREATE:

1. src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerAddressEntity.php
   - Doctrine entity with ORM attributes
   - Properties matching domain value object
   - Relationships:
     - ManyToOne to CustomerEntity
     - tenant_id for RLS
   - Methods:
     - static fromDomainModel(CustomerAddress $address, CustomerEntity $customer): self
     - toDomainModel(): CustomerAddress
   - Soft delete support (is_deleted column)

2. src/Customer/Infrastructure/Persistence/Doctrine/Type/AddressTypeType.php
   - Custom Doctrine type for AddressType enum
   - Register in doctrine.yaml

3. Update src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerEntity.php:
   - Add OneToMany relationship to CustomerAddressEntity
   - Update fromDomainModel() and toDomainModel() to handle addresses

4. Update src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineCustomerRepository.php:
   - Handle address persistence in save() method
   - Cascade operations for addresses

Configuration to UPDATE:
- config/packages/doctrine.yaml: Register AddressTypeType

Acceptance Criteria:
- [ ] Entity correctly maps to domain model
- [ ] Bidirectional relationship works
- [ ] Soft delete implemented
- [ ] tenant_id included for RLS
- [ ] Custom type registered and working
```

---

### TASK 7.1.3: Customer Address Database Migration
**Agent:** `Database Engineer`
**Priority:** P0
**Estimated Effort:** 3 hours

**Instructions:**
```
Create database migration for customer addresses.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- migrations/ (existing migrations for patterns)
- TASK 7.1.2 entity (for schema)

Commands to RUN:
symfony console make:migration

Then EDIT the generated migration to include:

1. Create customer_addresses table:
   - id VARCHAR(36) PRIMARY KEY
   - customer_id VARCHAR(36) NOT NULL (FK to customers)
   - tenant_id VARCHAR(36) NOT NULL (FK to tenants)
   - street VARCHAR(255) NOT NULL
   - street2 VARCHAR(255) NULL
   - city VARCHAR(100) NOT NULL
   - state VARCHAR(100) NULL
   - postal_code VARCHAR(20) NOT NULL
   - country CHAR(2) NOT NULL
   - type VARCHAR(20) NOT NULL CHECK (shipping, billing, both)
   - is_default_shipping BOOLEAN DEFAULT FALSE
   - is_default_billing BOOLEAN DEFAULT FALSE
   - is_deleted BOOLEAN DEFAULT FALSE
   - created_at TIMESTAMP NOT NULL
   - updated_at TIMESTAMP NOT NULL

2. Create indexes:
   - idx_customer_addresses_customer (customer_id)
   - idx_customer_addresses_tenant (tenant_id)
   - idx_customer_addresses_default_shipping (customer_id, is_default_shipping) WHERE is_default_shipping = true
   - idx_customer_addresses_default_billing (customer_id, is_default_billing) WHERE is_default_billing = true

3. Enable RLS:
   ALTER TABLE customer_addresses ENABLE ROW LEVEL SECURITY;
   CREATE POLICY tenant_isolation ON customer_addresses
     USING (tenant_id::text = current_setting('app.tenant_id', true));

4. Add foreign keys:
   - customer_id -> customers(id) ON DELETE CASCADE
   - tenant_id -> tenants(id) ON DELETE CASCADE

Commands to RUN after:
symfony console doctrine:migrations:migrate

Acceptance Criteria:
- [ ] Migration executes without errors
- [ ] RLS policy created
- [ ] Indexes created
- [ ] Foreign keys with correct cascade
- [ ] Migration is idempotent (IF NOT EXISTS)
```

---

### TASK 7.1.4: Customer Address Commands & Handlers
**Agent:** `PHP/Symfony Specialist`
**Priority:** P1
**Estimated Effort:** 8 hours

**Instructions:**
```
Create CQRS commands and handlers for address management.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- src/Customer/Application/Command/ (existing commands for patterns)
- src/Customer/Domain/Model/Customer.php (with address methods)

Files to CREATE:

1. src/Customer/Application/Command/AddAddress/AddAddressCommand.php
   - Properties: customerId, street, street2, city, state, postalCode, country, type, isDefault

2. src/Customer/Application/Command/AddAddress/AddAddressCommandHandler.php
   - Inject: CustomerRepositoryInterface
   - Load customer, create CustomerAddress, add to customer, save
   - Dispatch domain events

3. src/Customer/Application/Command/UpdateAddress/UpdateAddressCommand.php
   - Properties: customerId, addressId, street, street2, city, state, postalCode, country, type

4. src/Customer/Application/Command/UpdateAddress/UpdateAddressCommandHandler.php

5. src/Customer/Application/Command/RemoveAddress/RemoveAddressCommand.php
   - Properties: customerId, addressId

6. src/Customer/Application/Command/RemoveAddress/RemoveAddressCommandHandler.php
   - Soft delete the address

7. src/Customer/Application/Command/SetDefaultAddress/SetDefaultAddressCommand.php
   - Properties: customerId, addressId, type (shipping or billing)

8. src/Customer/Application/Command/SetDefaultAddress/SetDefaultAddressCommandHandler.php

9. src/Customer/Application/Query/GetCustomerAddresses/GetCustomerAddressesQuery.php
   - Properties: customerId, type (optional filter)

10. src/Customer/Application/Query/GetCustomerAddresses/GetCustomerAddressesQueryHandler.php
    - Return array of CustomerAddressDTO

11. src/Customer/Application/DTO/CustomerAddressDTO.php
    - Properties matching address fields
    - static fromDomainModel(CustomerAddress $address): self
    - Method: toArray()

Register in config/services.yaml:
- All handlers as message handlers

Acceptance Criteria:
- [ ] Commands are immutable (readonly class)
- [ ] Handlers follow single responsibility
- [ ] Domain events dispatched
- [ ] DTOs used for responses
- [ ] Error handling with domain exceptions
```

---

### TASK 7.1.5: Customer Address API Endpoints
**Agent:** `API Designer`
**Priority:** P1
**Estimated Effort:** 6 hours

**Instructions:**
```
Create REST API endpoints for address management.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- src/Customer/Presentation/Api/ (existing API resources)
- src/Pricing/Presentation/Api/ (for patterns)

Files to CREATE:

1. src/Customer/Presentation/Api/Resource/CustomerAddressResource.php
   - API Platform resource with operations:
     - GetCollection: GET /api/v1/customers/{customerId}/addresses
     - Get: GET /api/v1/customers/{customerId}/addresses/{id}
     - Post: POST /api/v1/customers/{customerId}/addresses
     - Put: PUT /api/v1/customers/{customerId}/addresses/{id}
     - Delete: DELETE /api/v1/customers/{customerId}/addresses/{id}
     - Patch (setDefault): PATCH /api/v1/customers/{customerId}/addresses/{id}/default

2. src/Customer/Presentation/Api/Processor/AddAddressProcessor.php
   - Inject CommandBus
   - Dispatch AddAddressCommand
   - Return created address

3. src/Customer/Presentation/Api/Processor/UpdateAddressProcessor.php

4. src/Customer/Presentation/Api/Processor/RemoveAddressProcessor.php

5. src/Customer/Presentation/Api/Processor/SetDefaultAddressProcessor.php

6. src/Customer/Presentation/Api/Provider/CustomerAddressCollectionProvider.php
   - Inject QueryBus
   - Filter by customerId from URL
   - Optional type filter from query param

7. src/Customer/Presentation/Api/Provider/CustomerAddressItemProvider.php

OpenAPI documentation:
- Add proper descriptions
- Define request/response schemas
- Add validation constraints
- Add example responses

Security:
- Require authentication
- Check CustomerVoter permissions
- Verify customer belongs to current tenant

Acceptance Criteria:
- [ ] All CRUD endpoints working
- [ ] Proper HTTP status codes
- [ ] Validation errors return 422
- [ ] OpenAPI documentation complete
- [ ] Multi-tenant isolation enforced
```

---

### TASK 7.1.6: Customer Address Tests
**Agent:** `Test Engineer`
**Priority:** P2
**Estimated Effort:** 8 hours

**Instructions:**
```
Create comprehensive tests for address functionality.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- tests/Unit/Customer/ (existing tests)
- tests/Functional/Api/CustomerApiTest.php

Files to CREATE:

1. tests/Unit/Customer/Domain/ValueObject/CustomerAddressTest.php
   - Test cases:
     - testCreateValidAddress
     - testCreateWithInvalidStreetThrowsException
     - testCreateWithInvalidCityThrowsException
     - testCreateWithInvalidPostalCodeThrowsException
     - testCreateWithInvalidCountryThrowsException
     - testEquals
     - testToArray
   - Minimum 15 tests

2. tests/Unit/Customer/Domain/ValueObject/CustomerAddressIdTest.php
   - Test generate, fromString, toString, equals
   - Minimum 8 tests

3. tests/Unit/Customer/Domain/ValueObject/AddressTypeTest.php
   - Test all enum values
   - Test toString, fromString
   - Minimum 6 tests

4. tests/Unit/Customer/Domain/Model/CustomerAddressManagementTest.php
   - Test cases:
     - testAddAddress
     - testAddAddressExceedsMaximumThrowsException
     - testUpdateAddress
     - testRemoveAddress
     - testSetDefaultShippingAddress
     - testSetDefaultBillingAddress
     - testOnlyOneDefaultPerType
     - testGetAddresses
     - testGetDefaultShippingAddress
     - testGetDefaultBillingAddress
   - Minimum 20 tests

5. tests/Unit/Customer/Application/Command/AddAddressCommandHandlerTest.php
   - Test successful addition
   - Test validation failures
   - Test maximum addresses exceeded
   - Minimum 8 tests

6. tests/Functional/Customer/Api/CustomerAddressApiTest.php
   - Test all CRUD endpoints
   - Test validation errors
   - Test authorization
   - Test multi-tenant isolation
   - Minimum 15 tests

Run tests:
vendor/bin/phpunit tests/Unit/Customer/Domain/ValueObject/CustomerAddressTest.php
vendor/bin/phpunit tests/Functional/Customer/Api/CustomerAddressApiTest.php

Acceptance Criteria:
- [ ] All tests pass
- [ ] Coverage >= 95% for address functionality
- [ ] Edge cases covered
- [ ] Multi-tenant isolation tested
```

---

### TASK 7.1.7: Customer Preferences Domain & Infrastructure
**Agent:** `DDD Architecture Specialist`
**Priority:** P1
**Estimated Effort:** 6 hours

**Instructions:**
```
Implement customer preferences feature.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/ValueObject/CustomerPreferences.php
   - Properties:
     - languageCode: string (ISO 639-1, 2 chars)
     - currencyCode: string (ISO 4217, 3 chars)
     - timezone: string (e.g., 'Europe/Bucharest')
     - dateOfBirth: ?DateTimeImmutable
     - newsletterSubscribed: bool
   - Validation:
     - languageCode must be valid ISO code
     - currencyCode must be valid ISO code
     - timezone must be valid PHP timezone
   - Factory: create(), default()
   - Methods: toArray(), equals()

2. Extend src/Customer/Domain/Model/Customer.php:
   - Add private CustomerPreferences $preferences
   - Add method: updatePreferences(CustomerPreferences $preferences): void
   - Add method: getPreferences(): CustomerPreferences
   - Record event: CustomerPreferencesUpdated

3. src/Customer/Domain/Event/CustomerPreferencesUpdated.php

4. Update src/Customer/Infrastructure/Persistence/Doctrine/Entity/CustomerEntity.php:
   - Add columns: language_code, currency_code, timezone, date_of_birth, newsletter_subscribed
   - Update fromDomainModel() and toDomainModel()

5. src/Customer/Application/Command/UpdatePreferences/UpdatePreferencesCommand.php
   - Properties: customerId, languageCode, currencyCode, timezone, dateOfBirth, newsletterSubscribed

6. src/Customer/Application/Command/UpdatePreferences/UpdatePreferencesCommandHandler.php

7. API endpoint in existing CustomerResource or new:
   - PATCH /api/v1/customers/{id}/preferences

Acceptance Criteria:
- [ ] Preferences are immutable value object
- [ ] Valid ISO codes enforced
- [ ] Valid timezone enforced
- [ ] Event recorded on update
```

---

### TASK 7.1.8: Customer Search & Filtering
**Agent:** `API Designer`
**Priority:** P1
**Estimated Effort:** 5 hours

**Instructions:**
```
Implement advanced search and filtering for customers.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- src/Customer/Presentation/Api/Provider/CustomerCollectionProvider.php

Files to MODIFY:

1. src/Customer/Application/Query/SearchCustomers/SearchCustomersQuery.php
   - Properties:
     - email: ?string (partial match)
     - name: ?string (partial match)
     - segment: ?CustomerSegment
     - isActive: ?bool
     - registeredFrom: ?DateTimeImmutable
     - registeredTo: ?DateTimeImmutable
     - loyaltyPointsMin: ?int
     - loyaltyPointsMax: ?int
     - page: int = 1
     - limit: int = 20

2. src/Customer/Application/Query/SearchCustomers/SearchCustomersQueryHandler.php
   - Build criteria query
   - Return paginated results with total count

3. src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineCustomerRepository.php
   - Add method: search(SearchCustomersCriteria $criteria): PaginatedResult

4. Update CustomerCollectionProvider:
   - Accept all filter parameters
   - Pass to query handler

5. Add API Platform filters to CustomerEntity or CustomerResource:
   - SearchFilter for email, name
   - ExactFilter for segment, isActive
   - DateFilter for registeredFrom, registeredTo
   - RangeFilter for loyaltyPoints

API endpoint:
GET /api/v1/customers?email=john&segment=VIP&isActive=true&page=1&limit=20

Acceptance Criteria:
- [ ] All filters work independently and combined
- [ ] Partial match for email and name
- [ ] Pagination works correctly
- [ ] Results sorted by created_at DESC by default
```

---

### TASK 7.1.9: Customer Import/Export
**Agent:** `PHP/Symfony Specialist`
**Priority:** P2
**Estimated Effort:** 8 hours

**Instructions:**
```
Implement bulk import/export for customers.

Working directory: /var/www/new_ecom/backend

Files to READ first:
- src/Pricing/Application/Service/PricingImportService.php (for patterns)
- src/Pricing/Application/Service/PricingExportService.php

Files to CREATE:

1. src/Customer/Application/Service/CustomerImportService.php
   - Method: importFromCsv(string $csvContent, TenantId $tenantId): ImportResult
   - Method: importFromJson(string $jsonContent, TenantId $tenantId): ImportResult
   - Validation per row
   - Skip duplicates by email
   - Collect errors, don't fail on first error
   - Return ImportResult with success/error counts

2. src/Customer/Application/Service/CustomerExportService.php
   - Method: exportToCsv(TenantId $tenantId, ?ExportFilter $filter): string
   - Method: exportToJson(TenantId $tenantId, ?ExportFilter $filter): string
   - Method: getTemplate(): string (CSV template with headers)

3. src/Customer/Application/Command/ImportCustomers/ImportCustomersCommand.php
   - Properties: content, format (csv/json), tenantId

4. src/Customer/Application/Command/ImportCustomers/ImportCustomersCommandHandler.php

5. src/Customer/Application/Query/ExportCustomers/ExportCustomersQuery.php
   - Properties: tenantId, format, filter

6. src/Customer/Application/Query/ExportCustomers/ExportCustomersQueryHandler.php

7. src/Customer/Application/DTO/ImportResult.php
   - Properties: successCount, errorCount, errors[], totalProcessed

8. API Endpoints:
   - POST /api/v1/customers/import (multipart/form-data)
   - GET /api/v1/customers/export?format=csv
   - GET /api/v1/customers/import/template

9. Async processing for large imports:
   - Create ImportCustomersMessage for Messenger
   - Create ImportCustomersMessageHandler
   - Threshold: > 100 rows -> async

Acceptance Criteria:
- [ ] CSV and JSON formats supported
- [ ] Validation errors collected per row
- [ ] Duplicates skipped (by email)
- [ ] Async for large imports
- [ ] Template download available
```

---

## 4. Sprint 7.2 Execution Tasks

### Week 3-4: Loyalty Programs & Points System

---

### TASK 7.2.1: Loyalty Program Domain Model
**Agent:** `DDD Architecture Specialist`
**Priority:** P0
**Estimated Effort:** 8 hours

**Instructions:**
```
Create the Loyalty Program aggregate as a separate bounded context within Customer.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/Model/LoyaltyProgram.php
   - Aggregate root
   - Properties:
     - id: LoyaltyProgramId
     - tenantId: TenantId
     - name: string (3-100 chars)
     - description: ?string
     - earningRate: EarningRate (points per currency unit)
     - minOrderValue: Money (minimum order to earn points)
     - validityDays: ?int (null = never expires)
     - isActive: bool
     - tiers: array of LoyaltyTier
     - createdAt, updatedAt
   - Factory: create()
   - Methods:
     - update(name, description, earningRate, minOrderValue, validityDays): void
     - activate(): void
     - deactivate(): void
     - addTier(LoyaltyTier $tier): void
     - removeTier(LoyaltyTierId $tierId): void
     - calculatePointsForOrder(Money $orderTotal): int
     - getTierForPoints(int $points): ?LoyaltyTier
   - Domain events:
     - LoyaltyProgramCreated
     - LoyaltyProgramUpdated
     - LoyaltyProgramActivated
     - LoyaltyProgramDeactivated
     - LoyaltyTierAdded
     - LoyaltyTierRemoved

2. src/Customer/Domain/Model/LoyaltyProgramId.php
   - UUID v7 value object

3. src/Customer/Domain/ValueObject/EarningRate.php
   - Value: decimal (points per currency unit, e.g., 1.0 = 1 point per $1)
   - Validation: must be > 0
   - Methods: calculatePoints(Money $amount): int

4. src/Customer/Domain/Model/LoyaltyTier.php
   - Entity (not aggregate root)
   - Properties:
     - id: LoyaltyTierId
     - name: string (e.g., "Bronze", "Silver", "Gold", "Platinum")
     - threshold: int (points required)
     - discountPercentage: Discount
     - freeShippingMinOrder: ?Money
     - sortOrder: int
   - Factory: create()

5. src/Customer/Domain/Model/LoyaltyTierId.php
   - UUID v7 value object

6. src/Customer/Domain/Repository/LoyaltyProgramRepositoryInterface.php
   - Methods:
     - save(LoyaltyProgram $program): void
     - findById(LoyaltyProgramId $id): ?LoyaltyProgram
     - findByTenantId(TenantId $tenantId): ?LoyaltyProgram
     - delete(LoyaltyProgramId $id): void

7. Create domain events in src/Customer/Domain/Event/

Acceptance Criteria:
- [ ] One loyalty program per tenant (enforced)
- [ ] Earning rate calculates points correctly
- [ ] Tiers sorted by threshold ascending
- [ ] Tier lookup by points works correctly
- [ ] All domain events recorded
```

---

### TASK 7.2.2: Loyalty Program Infrastructure
**Agent:** `PHP/Symfony Specialist`
**Priority:** P0
**Estimated Effort:** 6 hours

**Instructions:**
```
Create infrastructure layer for Loyalty Program.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Infrastructure/Persistence/Doctrine/Entity/LoyaltyProgramEntity.php
   - Doctrine entity
   - OneToMany relationship to LoyaltyTierEntity
   - fromDomainModel(), toDomainModel()

2. src/Customer/Infrastructure/Persistence/Doctrine/Entity/LoyaltyTierEntity.php
   - Doctrine entity
   - ManyToOne to LoyaltyProgramEntity
   - fromDomainModel(), toDomainModel()

3. src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineLoyaltyProgramRepository.php
   - Implement LoyaltyProgramRepositoryInterface
   - Handle tier cascade

4. src/Customer/Infrastructure/Persistence/Doctrine/Type/EarningRateType.php
   - Custom Doctrine type

Register in config/services.yaml and config/packages/doctrine.yaml

Acceptance Criteria:
- [ ] Entities map correctly to domain models
- [ ] Cascade persist/remove for tiers
- [ ] tenant_id for RLS
- [ ] Repository saves and loads correctly
```

---

### TASK 7.2.3: Loyalty Program Database Migration
**Agent:** `Database Engineer`
**Priority:** P0
**Estimated Effort:** 3 hours

**Instructions:**
```
Create database migration for loyalty program.

Working directory: /var/www/new_ecom/backend

Create migration with:

1. loyalty_programs table:
   - id VARCHAR(36) PRIMARY KEY
   - tenant_id VARCHAR(36) NOT NULL UNIQUE (one per tenant)
   - name VARCHAR(100) NOT NULL
   - description TEXT
   - earning_rate DECIMAL(10,4) NOT NULL
   - min_order_value INTEGER DEFAULT 0
   - validity_days INTEGER
   - is_active BOOLEAN DEFAULT TRUE
   - created_at TIMESTAMP NOT NULL
   - updated_at TIMESTAMP NOT NULL
   - FK to tenants(id)
   - RLS policy

2. loyalty_tiers table:
   - id VARCHAR(36) PRIMARY KEY
   - program_id VARCHAR(36) NOT NULL
   - tenant_id VARCHAR(36) NOT NULL
   - name VARCHAR(50) NOT NULL
   - threshold INTEGER NOT NULL
   - discount_percentage DECIMAL(5,2) DEFAULT 0
   - free_shipping_min_order INTEGER
   - sort_order INTEGER DEFAULT 0
   - created_at TIMESTAMP NOT NULL
   - FK to loyalty_programs(id) CASCADE
   - FK to tenants(id)
   - RLS policy
   - Index on (program_id, threshold)

3. loyalty_point_transactions table:
   - id VARCHAR(36) PRIMARY KEY
   - customer_id VARCHAR(36) NOT NULL
   - tenant_id VARCHAR(36) NOT NULL
   - type VARCHAR(20) NOT NULL (earned, redeemed, expired, bonus, adjustment)
   - points INTEGER NOT NULL
   - balance_after INTEGER NOT NULL
   - reason VARCHAR(255) NOT NULL
   - order_id VARCHAR(36)
   - expires_at TIMESTAMP
   - created_at TIMESTAMP NOT NULL
   - FK to customers(id)
   - FK to tenants(id)
   - RLS policy
   - Indexes on customer_id, type, created_at

4. Alter customers table:
   - ADD current_tier_id VARCHAR(36)
   - ADD loyalty_points_balance INTEGER DEFAULT 0
   - FK current_tier_id to loyalty_tiers(id)

Acceptance Criteria:
- [ ] Migration executes without errors
- [ ] RLS policies created for all tables
- [ ] Proper indexes for performance
- [ ] One program per tenant enforced via UNIQUE
```

---

### TASK 7.2.4: Loyalty Program Commands & Handlers
**Agent:** `PHP/Symfony Specialist`
**Priority:** P1
**Estimated Effort:** 10 hours

**Instructions:**
```
Create CQRS commands and handlers for loyalty program.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Application/Command/CreateLoyaltyProgram/CreateLoyaltyProgramCommand.php
2. src/Customer/Application/Command/CreateLoyaltyProgram/CreateLoyaltyProgramCommandHandler.php

3. src/Customer/Application/Command/UpdateLoyaltyProgram/UpdateLoyaltyProgramCommand.php
4. src/Customer/Application/Command/UpdateLoyaltyProgram/UpdateLoyaltyProgramCommandHandler.php

5. src/Customer/Application/Command/ActivateLoyaltyProgram/ActivateLoyaltyProgramCommand.php
6. src/Customer/Application/Command/ActivateLoyaltyProgram/ActivateLoyaltyProgramCommandHandler.php

7. src/Customer/Application/Command/DeactivateLoyaltyProgram/DeactivateLoyaltyProgramCommand.php
8. src/Customer/Application/Command/DeactivateLoyaltyProgram/DeactivateLoyaltyProgramCommandHandler.php

9. src/Customer/Application/Command/AddLoyaltyTier/AddLoyaltyTierCommand.php
10. src/Customer/Application/Command/AddLoyaltyTier/AddLoyaltyTierCommandHandler.php

11. src/Customer/Application/Command/RemoveLoyaltyTier/RemoveLoyaltyTierCommand.php
12. src/Customer/Application/Command/RemoveLoyaltyTier/RemoveLoyaltyTierCommandHandler.php

13. src/Customer/Application/Query/GetLoyaltyProgram/GetLoyaltyProgramQuery.php
14. src/Customer/Application/Query/GetLoyaltyProgram/GetLoyaltyProgramQueryHandler.php

15. src/Customer/Application/DTO/LoyaltyProgramDTO.php
16. src/Customer/Application/DTO/LoyaltyTierDTO.php

Acceptance Criteria:
- [ ] All handlers follow CQRS pattern
- [ ] Domain events dispatched
- [ ] DTOs used for responses
- [ ] Tenant isolation enforced
```

---

### TASK 7.2.5: Loyalty Points Transaction History
**Agent:** `PHP/Symfony Specialist`
**Priority:** P1
**Estimated Effort:** 6 hours

**Instructions:**
```
Implement points transaction history.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/Model/LoyaltyPointTransaction.php
   - Entity
   - Properties: id, customerId, tenantId, type, points, balanceAfter, reason, orderId, expiresAt, createdAt
   - Types: EARNED, REDEEMED, EXPIRED, BONUS, ADJUSTMENT

2. src/Customer/Domain/Model/LoyaltyPointTransactionId.php

3. src/Customer/Domain/Repository/LoyaltyPointTransactionRepositoryInterface.php
   - Methods:
     - save(LoyaltyPointTransaction $transaction): void
     - findByCustomerId(CustomerId $customerId, ?int $page, ?int $limit): array
     - findByType(CustomerId $customerId, string $type): array
     - getBalance(CustomerId $customerId): int

4. src/Customer/Infrastructure/Persistence/Doctrine/Entity/LoyaltyPointTransactionEntity.php
5. src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineLoyaltyPointTransactionRepository.php

6. Update Customer aggregate:
   - Modify awardLoyaltyPoints() to create transaction
   - Add redeemLoyaltyPoints() method
   - Track balance_after

7. src/Customer/Application/Query/GetPointsHistory/GetPointsHistoryQuery.php
8. src/Customer/Application/Query/GetPointsHistory/GetPointsHistoryQueryHandler.php

9. API endpoint:
   - GET /api/v1/customers/{id}/loyalty/history

Acceptance Criteria:
- [ ] All point changes recorded as transactions
- [ ] Balance tracked correctly
- [ ] Pagination works
- [ ] Filter by type works
```

---

### TASK 7.2.6: Points Redemption with Pricing Integration
**Agent:** `DDD Architecture Specialist`
**Priority:** P1
**Estimated Effort:** 8 hours

**Instructions:**
```
Implement points redemption with ACL integration to Pricing context.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/ValueObject/RedemptionRule.php
   - Properties:
     - conversionRate: decimal (e.g., 100 points = $1)
     - minPointsToRedeem: int
     - maxPointsPerOrder: int

2. Add to LoyaltyProgram:
   - redemptionRule: RedemptionRule
   - Method: calculateDiscountForPoints(int $points): Money

3. src/Customer/Application/Command/RedeemPoints/RedeemPointsCommand.php
   - Properties: customerId, points, orderId

4. src/Customer/Application/Command/RedeemPoints/RedeemPointsCommandHandler.php
   - Validate: customer has enough points
   - Validate: points >= minPointsToRedeem
   - Validate: points <= maxPointsPerOrder
   - Create transaction (type: REDEEMED)
   - Update customer balance
   - Return discount amount

5. src/Customer/Application/Query/ValidateRedemption/ValidateRedemptionQuery.php
   - Properties: customerId, points

6. src/Customer/Application/Query/ValidateRedemption/ValidateRedemptionQueryHandler.php
   - Return: isValid, discountAmount, message

7. ACL Interface for Pricing context:
   src/Customer/Application/Service/LoyaltyPointsRedemptionServiceInterface.php
   - Method: validateRedemption(CustomerId $customerId, int $points): RedemptionValidationResult
   - Method: redeemPoints(CustomerId $customerId, int $points, OrderId $orderId): Money

8. Implementation in Pricing context (ACL consumer):
   src/Pricing/Infrastructure/ACL/CustomerLoyaltyAdapter.php
   - Implements interface needed by CartPricingService
   - Calls Customer context service

9. API endpoint:
   - POST /api/v1/customers/{id}/loyalty/redeem

Acceptance Criteria:
- [ ] Redemption rules enforced
- [ ] Balance updated correctly
- [ ] Transaction recorded
- [ ] ACL pattern used for cross-context
- [ ] Integration with CartPricingService
```

---

### TASK 7.2.7: Loyalty Program API Endpoints
**Agent:** `API Designer`
**Priority:** P1
**Estimated Effort:** 6 hours

**Instructions:**
```
Create REST API endpoints for loyalty program management.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Presentation/Api/Resource/LoyaltyProgramResource.php
   - Operations:
     - Get: GET /api/v1/loyalty-programs (tenant's program)
     - Post: POST /api/v1/loyalty-programs (admin only)
     - Put: PUT /api/v1/loyalty-programs/{id}
     - Patch activate: PATCH /api/v1/loyalty-programs/{id}/activate
     - Patch deactivate: PATCH /api/v1/loyalty-programs/{id}/deactivate

2. src/Customer/Presentation/Api/Resource/LoyaltyTierResource.php
   - Operations:
     - GetCollection: GET /api/v1/loyalty-programs/{programId}/tiers
     - Post: POST /api/v1/loyalty-programs/{programId}/tiers
     - Delete: DELETE /api/v1/loyalty-programs/{programId}/tiers/{id}

3. Processors and Providers for each operation

4. src/Customer/Presentation/Api/Resource/CustomerLoyaltyResource.php
   - Operations:
     - Get: GET /api/v1/customers/{id}/loyalty (balance, tier, history summary)
     - GetHistory: GET /api/v1/customers/{id}/loyalty/history
     - PostRedeem: POST /api/v1/customers/{id}/loyalty/redeem

Acceptance Criteria:
- [ ] All endpoints documented with OpenAPI
- [ ] Proper authorization (admin for program management)
- [ ] Customer can only access own loyalty data
```

---

### TASK 7.2.8: Loyalty Program Tests
**Agent:** `Test Engineer`
**Priority:** P2
**Estimated Effort:** 10 hours

**Instructions:**
```
Create comprehensive tests for loyalty program.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. tests/Unit/Customer/Domain/Model/LoyaltyProgramTest.php
   - 20+ tests

2. tests/Unit/Customer/Domain/Model/LoyaltyTierTest.php
   - 10+ tests

3. tests/Unit/Customer/Domain/ValueObject/EarningRateTest.php
   - 8+ tests

4. tests/Unit/Customer/Domain/ValueObject/RedemptionRuleTest.php
   - 8+ tests

5. tests/Unit/Customer/Application/Command/LoyaltyProgramCommandHandlerTests.php
   - Test all command handlers

6. tests/Unit/Customer/Application/Service/TierCalculationServiceTest.php

7. tests/Integration/Customer/Repository/LoyaltyProgramRepositoryTest.php

8. tests/Functional/Customer/Api/LoyaltyProgramApiTest.php
   - 15+ tests for all endpoints

9. tests/Functional/Customer/Api/CustomerLoyaltyApiTest.php
   - Test redemption flow
   - Test history endpoint

Acceptance Criteria:
- [ ] All tests pass
- [ ] Coverage >= 95%
- [ ] Edge cases covered (max points, tier boundaries)
```

---

## 5. Sprint 7.3 Execution Tasks

### Week 5-6: GDPR Compliance & Notifications

---

### TASK 7.3.1: GDPR Data Export
**Agent:** `Security Auditor`
**Priority:** P0
**Estimated Effort:** 10 hours

**Instructions:**
```
Implement GDPR-compliant data export functionality.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/Model/DataExportRequest.php
   - Entity
   - Properties: id, customerId, tenantId, status, filePath, downloadToken, expiresAt, createdAt, completedAt
   - Status: PENDING, PROCESSING, READY, EXPIRED, FAILED
   - Methods: markProcessing(), markReady(), markExpired(), markFailed()

2. src/Customer/Domain/Repository/DataExportRequestRepositoryInterface.php

3. src/Customer/Infrastructure/Persistence/Doctrine/Entity/DataExportRequestEntity.php
4. src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineDataExportRequestRepository.php

5. src/Customer/Application/Service/DataExportService.php
   - Method: collectCustomerData(CustomerId $customerId): array
     - Collect from: Customer profile, addresses, orders, loyalty points, preferences
   - Method: generateJsonExport(array $data): string
   - Method: generateDownloadToken(): string

6. src/Customer/Application/Command/RequestDataExport/RequestDataExportCommand.php
7. src/Customer/Application/Command/RequestDataExport/RequestDataExportCommandHandler.php
   - Check rate limit (1 per 24h)
   - Create pending request
   - Dispatch async message

8. src/Customer/Application/Message/GenerateDataExportMessage.php
9. src/Customer/Application/MessageHandler/GenerateDataExportMessageHandler.php
   - Collect data
   - Generate JSON
   - Store file
   - Update request status
   - Send notification email

10. API endpoints:
    - POST /api/v1/customers/{id}/data-export/request
    - GET /api/v1/customers/{id}/data-export/{requestId}/status
    - GET /api/v1/customers/{id}/data-export/{requestId}/download

11. Migration for data_export_requests table

Acceptance Criteria:
- [ ] Rate limiting enforced (1 per 24h)
- [ ] Async processing for large data
- [ ] Download token expires in 24h
- [ ] All PII included in export
- [ ] JSON format per GDPR requirements
```

---

### TASK 7.3.2: GDPR Account Deletion
**Agent:** `Security Auditor`
**Priority:** P0
**Estimated Effort:** 10 hours

**Instructions:**
```
Implement GDPR-compliant account deletion with anonymization.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/Model/DeletionRequest.php
   - Entity
   - Properties: id, customerId, tenantId, status, reason, holdReason, scheduledFor, confirmedAt, completedAt, createdAt
   - Status: PENDING, CONFIRMED, PROCESSING, COMPLETED, CANCELLED, ON_HOLD
   - Methods: confirm(), cancel(), putOnHold(), process(), complete()

2. src/Customer/Domain/Repository/DeletionRequestRepositoryInterface.php

3. src/Customer/Infrastructure/Persistence/Doctrine/Entity/DeletionRequestEntity.php
4. src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineDeletionRequestRepository.php

5. src/Customer/Application/Service/CustomerAnonymizationService.php
   - Method: anonymize(Customer $customer): void
     - Replace name with "Deleted Customer"
     - Replace email with "deleted_{uuid}@anonymized.local"
     - Clear phone, addresses
     - Keep order history with anonymized customer reference
     - Clear loyalty points history (keep totals for accounting)
   - Method: deletePersonalData(Customer $customer): void
     - Remove addresses
     - Remove preferences
     - Remove consents
     - Remove export requests

6. src/Customer/Application/Command/RequestAccountDeletion/RequestAccountDeletionCommand.php
7. src/Customer/Application/Command/RequestAccountDeletion/RequestAccountDeletionCommandHandler.php
   - Create pending request
   - Send confirmation email
   - Schedule deletion for 30 days

8. src/Customer/Application/Command/ConfirmAccountDeletion/ConfirmAccountDeletionCommand.php
9. src/Customer/Application/Command/ConfirmAccountDeletion/ConfirmAccountDeletionCommandHandler.php

10. src/Customer/Application/Command/CancelAccountDeletion/CancelAccountDeletionCommand.php
11. src/Customer/Application/Command/CancelAccountDeletion/CancelAccountDeletionCommandHandler.php

12. src/Customer/Application/Message/ExecuteDeletionMessage.php
13. src/Customer/Application/MessageHandler/ExecuteDeletionMessageHandler.php
    - Called by scheduler after 30 days
    - Anonymize customer
    - Delete personal data
    - Mark request completed

14. Admin endpoint for legal holds:
    - PUT /api/v1/admin/deletion-requests/{id}/hold
    - PUT /api/v1/admin/deletion-requests/{id}/release

15. API endpoints:
    - POST /api/v1/customers/{id}/deletion-request
    - DELETE /api/v1/customers/{id}/deletion-request (cancel)
    - POST /api/v1/customers/{id}/deletion-request/confirm

16. Migration for deletion_requests table

Acceptance Criteria:
- [ ] 30-day retention period before deletion
- [ ] Confirmation required
- [ ] Cancellation possible before deletion
- [ ] Admin can put on legal hold
- [ ] Anonymization preserves order history
- [ ] Audit trail maintained
```

---

### TASK 7.3.3: Consent Management
**Agent:** `Security Auditor`
**Priority:** P1
**Estimated Effort:** 6 hours

**Instructions:**
```
Implement GDPR consent management.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/ValueObject/CustomerConsent.php
   - Properties:
     - marketingEmail: bool
     - marketingSms: bool
     - thirdPartySharing: bool
     - grantedAt: DateTimeImmutable
     - ipAddress: ?string
     - userAgent: ?string

2. src/Customer/Domain/Model/ConsentHistory.php
   - Entity
   - Properties: id, customerId, tenantId, consentType, granted, ipAddress, userAgent, createdAt

3. Extend Customer aggregate:
   - Add consents: CustomerConsent
   - Method: updateConsent(string $type, bool $granted, string $ip, string $userAgent): void
   - Record event: CustomerConsentChanged

4. src/Customer/Domain/Event/CustomerConsentChanged.php

5. src/Customer/Infrastructure/Persistence/Doctrine/Entity/ConsentHistoryEntity.php
6. src/Customer/Infrastructure/Persistence/Doctrine/Repository/DoctrineConsentHistoryRepository.php

7. src/Customer/Application/Command/UpdateConsent/UpdateConsentCommand.php
8. src/Customer/Application/Command/UpdateConsent/UpdateConsentCommandHandler.php
   - Update customer consent
   - Create consent history record

9. src/Customer/Application/Query/GetConsentHistory/GetConsentHistoryQuery.php
10. src/Customer/Application/Query/GetConsentHistory/GetConsentHistoryQueryHandler.php

11. API endpoints:
    - GET /api/v1/customers/{id}/consents
    - PUT /api/v1/customers/{id}/consents
    - GET /api/v1/customers/{id}/consents/history

12. Migration for consent_history table and customer consent columns

Acceptance Criteria:
- [ ] All consent types tracked
- [ ] History preserved with timestamps
- [ ] IP and user agent recorded
- [ ] Easy consent withdrawal
```

---

### TASK 7.3.4: Notification Preferences
**Agent:** `PHP/Symfony Specialist`
**Priority:** P2
**Estimated Effort:** 8 hours

**Instructions:**
```
Implement notification preferences.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. src/Customer/Domain/ValueObject/NotificationPreferences.php
   - Properties:
     - orderUpdates: bool (required, always true)
     - shippingUpdates: bool
     - promotionalOffers: bool
     - priceDropAlerts: bool
     - backInStockAlerts: bool
     - securityAlerts: bool (required, always true)
     - viaSms: bool
     - weeklyDigest: bool
   - Factory: default() (sensible defaults)

2. Extend Customer aggregate:
   - Add notificationPreferences: NotificationPreferences
   - Method: updateNotificationPreferences(NotificationPreferences $prefs): void
   - Record event: NotificationPreferencesUpdated

3. src/Customer/Domain/Event/NotificationPreferencesUpdated.php

4. Update CustomerEntity with notification columns

5. src/Customer/Application/Command/UpdateNotificationPreferences/UpdateNotificationPreferencesCommand.php
6. src/Customer/Application/Command/UpdateNotificationPreferences/UpdateNotificationPreferencesCommandHandler.php

7. src/Customer/Application/Service/NotificationPreferenceService.php
   - Method: shouldSendEmail(CustomerId $customerId, string $notificationType): bool
   - Method: shouldSendSms(CustomerId $customerId, string $notificationType): bool

8. Update existing event subscribers to check preferences:
   - OrderPlacedSubscriber
   - OrderStatusChangedSubscriber
   - etc.

9. API endpoints:
    - GET /api/v1/customers/{id}/notification-preferences
    - PUT /api/v1/customers/{id}/notification-preferences

10. Migration for notification preference columns

Acceptance Criteria:
- [ ] Required notifications cannot be disabled
- [ ] Preferences checked before sending
- [ ] SMS toggle works
- [ ] Weekly digest option
```

---

### TASK 7.3.5: GDPR & Notifications Tests
**Agent:** `Test Engineer`
**Priority:** P2
**Estimated Effort:** 10 hours

**Instructions:**
```
Create comprehensive tests for GDPR and notifications.

Working directory: /var/www/new_ecom/backend

Files to CREATE:

1. tests/Unit/Customer/Application/Service/DataExportServiceTest.php
   - Test data collection
   - Test JSON generation
   - 10+ tests

2. tests/Unit/Customer/Application/Service/CustomerAnonymizationServiceTest.php
   - Test anonymization logic
   - Test data deletion
   - 12+ tests

3. tests/Unit/Customer/Domain/ValueObject/CustomerConsentTest.php
   - 8+ tests

4. tests/Unit/Customer/Domain/ValueObject/NotificationPreferencesTest.php
   - 10+ tests

5. tests/Unit/Customer/Application/Command/GDPR/*Test.php
   - Test all GDPR command handlers
   - 15+ tests

6. tests/Functional/Customer/Api/DataExportApiTest.php
   - Test request, status, download flow
   - Test rate limiting
   - 10+ tests

7. tests/Functional/Customer/Api/DeletionRequestApiTest.php
   - Test deletion flow
   - Test cancellation
   - Test admin hold
   - 12+ tests

8. tests/Functional/Customer/Api/ConsentApiTest.php
   - Test consent CRUD
   - Test history
   - 8+ tests

9. tests/Integration/Customer/Service/NotificationPreferenceServiceTest.php
   - Test preference checking
   - 8+ tests

Acceptance Criteria:
- [ ] All tests pass
- [ ] GDPR flows fully tested
- [ ] Edge cases covered
```

---

## 6. Quality Gates

### After Each Sprint

```bash
# Quality Gate Checklist

# 1. PHPStan (must pass)
vendor/bin/phpstan analyse src/Customer/ --level=8

# 2. Deptrac (must pass)
vendor/bin/deptrac analyse --config-file=deptrac.yaml

# 3. Tests (must pass with >= 90% coverage)
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Unit/Customer/ --coverage-text
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Integration/Customer/ --coverage-text
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Functional/Customer/ --coverage-text

# 4. Code Style (must pass)
vendor/bin/php-cs-fixer fix src/Customer/ --dry-run

# 5. Migrations (must execute without errors)
APP_ENV=test symfony console doctrine:migrations:migrate --no-interaction
```

### Sprint Sign-off Requirements

- [ ] All tasks completed
- [ ] PHPStan: 0 errors
- [ ] Deptrac: 0 violations
- [ ] Tests: >= 90% coverage, all passing
- [ ] Code review approved
- [ ] API documentation updated
- [ ] No regressions in existing functionality

---

## 7. Execution Commands

### Development Setup

```bash
# Navigate to backend
cd /var/www/new_ecom/backend

# Clear cache
symfony console cache:clear

# Run migrations
symfony console doctrine:migrations:migrate

# Start server
symfony server:start -d

# View logs
symfony server:log
```

### Running Tests

```bash
# All Customer tests
vendor/bin/phpunit tests/Unit/Customer/
vendor/bin/phpunit tests/Integration/Customer/
vendor/bin/phpunit tests/Functional/Customer/

# Specific test file
vendor/bin/phpunit tests/Unit/Customer/Domain/ValueObject/CustomerAddressTest.php

# With coverage
XDEBUG_MODE=coverage vendor/bin/phpunit tests/Unit/Customer/ --coverage-html coverage/
```

### Static Analysis

```bash
# PHPStan
vendor/bin/phpstan analyse src/Customer/ --level=8

# Deptrac
vendor/bin/deptrac analyse --config-file=deptrac.yaml

# PHP CS Fixer
vendor/bin/php-cs-fixer fix src/Customer/
```

### API Testing with curl

```bash
# Set tenant ID
TENANT_ID="00000000-0000-4000-8000-000000000001"

# Get customer addresses
curl -s http://127.0.0.1:8000/api/v1/customers/{customerId}/addresses \
  -H "Accept: application/ld+json" \
  -H "X-Tenant-ID: $TENANT_ID"

# Add address
curl -s -X POST http://127.0.0.1:8000/api/v1/customers/{customerId}/addresses \
  -H "Accept: application/ld+json" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{"street":"123 Main St","city":"New York","postalCode":"10001","country":"US","type":"shipping"}'

# Get loyalty program
curl -s http://127.0.0.1:8000/api/v1/loyalty-programs \
  -H "Accept: application/ld+json" \
  -H "X-Tenant-ID: $TENANT_ID"
```

---

## 8. Rollback Plan

### If Migration Fails

```bash
# Rollback last migration
symfony console doctrine:migrations:migrate prev

# Check status
symfony console doctrine:migrations:status
```

### If Tests Fail After Changes

```bash
# Stash changes
git stash

# Run tests to confirm baseline
vendor/bin/phpunit tests/Unit/Customer/

# Review changes
git stash show -p

# Apply and fix
git stash pop
```

### If Production Issue

1. Revert to previous deployment
2. Investigate root cause
3. Create hotfix branch
4. Apply fix with tests
5. Deploy hotfix

---

## 9. Post-Phase Checklist

### Final Verification

- [ ] All 114 story points delivered
- [ ] All tests passing (Unit, Integration, Functional)
- [ ] Test coverage >= 90%
- [ ] PHPStan level 8: 0 errors
- [ ] Deptrac: 0 violations
- [ ] API documentation complete
- [ ] Frontend Admin UI complete
- [ ] Frontend Storefront UI complete
- [ ] GDPR compliance verified
- [ ] Performance benchmarks met
- [ ] Code review approved
- [ ] Documentation updated (CLAUDE.md, CHECKLIST.md)

### Update Project Status

```bash
# Update CHECKLIST.md
# - Mark Phase 7 as Complete
# - Update test counts
# - Update API endpoint counts

# Update CLAUDE.md
# - Add Customer context achievements
# - Update test coverage summary
# - Add new ADRs
```

---

## 10. Cross-Context Integration

### Customer -> Pricing (ACL)

```php
// Interface in Customer context
interface CustomerSegmentProviderInterface {
    public function getSegmentForCustomer(CustomerId $customerId): CustomerSegment;
    public function getTierDiscountForCustomer(CustomerId $customerId): ?Discount;
}

// Adapter in Pricing context
class CustomerSegmentAdapter implements CustomerSegmentProviderInterface {
    // Calls Customer context query
}
```

### Order -> Customer (ACL)

```php
// Event subscriber in Customer context
class OrderCompletedSubscriber {
    public function onOrderCompleted(OrderCompleted $event): void {
        // Award loyalty points based on order total
    }
}
```

### Notification -> Customer (ACL)

```php
// Service in Notification context
class NotificationDispatcher {
    public function shouldSend(CustomerId $customerId, string $type): bool {
        // Calls Customer context to check preferences
    }
}
```

---

**Document Version:** 1.0
**Last Updated:** 2025-11-28
**Author:** Project Management Suite
