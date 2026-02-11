# Phase 7: Customer Context - Sprint Plan

**Version:** 1.0
**Created:** 2025-11-28
**Duration:** 3 Sprints (6 weeks total)
**Status:** Planning

---

## Executive Summary

Phase 7 focuses on completing the Customer bounded context for the multi-tenant e-commerce platform. The Customer context already has a solid foundation with core domain models, CQRS commands/queries, and API endpoints. This phase will enhance the context with advanced features including loyalty programs, customer addresses, notification preferences, GDPR compliance, and deeper integration with other bounded contexts (Order, Pricing).

### Current State Assessment

**Already Implemented:**
- Customer aggregate with registration, profile updates, activation/deactivation
- CustomerSegment value object (REGULAR, VIP, WHOLESALE, PREMIUM)
- Basic loyalty points (award/redeem)
- Domain events (CustomerCreated, CustomerUpdated, CustomerSegmentChanged, LoyaltyPointsAwarded, CustomerActivated, CustomerDeactivated)
- Repository interface and implementation (DoctrineCustomerRepository)
- CustomerEntity with API Platform integration
- API endpoints: GET/POST customers, PATCH activate/deactivate/segment, POST award-points, GET orders, GET profile
- CustomerVoter for RBAC
- 24 unit tests for Customer domain model

**Gaps to Address:**
- Customer addresses management (billing/shipping)
- Loyalty program configuration (rules, tiers, redemption)
- Notification preferences
- GDPR compliance (data export, deletion requests, consent management)
- Customer preferences/settings
- Order history integration enhancement
- Event subscribers for notifications
- Integration tests and functional API tests
- Frontend Admin UI components
- Frontend Storefront components

---

## Sprint Structure

### Sprint 7.1: Customer Addresses & Enhanced Profile (2 weeks)

**Sprint Goal:** Implement complete address management and enhanced customer profile features

**Story Points:** 34

#### User Stories

##### US-7.1.1: Customer Address Management (13 SP)
**As a** customer
**I want to** manage my shipping and billing addresses
**So that** I can easily checkout with saved addresses

**Acceptance Criteria:**
- [ ] Add new address (billing/shipping/both)
- [ ] Edit existing address
- [ ] Delete address (soft delete, preserve for order history)
- [ ] Set default shipping address
- [ ] Set default billing address
- [ ] Maximum 10 addresses per customer
- [ ] Address validation (required fields, postal code format)
- [ ] Multi-tenant isolation enforced

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create CustomerAddress value object (street, city, state, postalCode, country, isDefault, type) | 2h |
| P0 | Create CustomerAddressId value object | 1h |
| P0 | Extend Customer aggregate with addresses collection | 3h |
| P0 | Create CustomerAddressEntity (Doctrine entity) | 2h |
| P0 | Create migration for customer_addresses table | 1h |
| P1 | Create AddAddressCommand + Handler | 2h |
| P1 | Create UpdateAddressCommand + Handler | 2h |
| P1 | Create RemoveAddressCommand + Handler | 1h |
| P1 | Create SetDefaultAddressCommand + Handler | 1h |
| P1 | Create GetCustomerAddressesQuery + Handler | 1h |
| P1 | API endpoints: POST /customers/{id}/addresses, GET /customers/{id}/addresses | 3h |
| P1 | API endpoints: PUT /customers/{id}/addresses/{addressId}, DELETE /customers/{id}/addresses/{addressId} | 2h |
| P2 | Unit tests for CustomerAddress value object | 2h |
| P2 | Unit tests for address management in Customer aggregate | 3h |
| P2 | Functional tests for address API endpoints | 3h |

##### US-7.1.2: Enhanced Customer Profile (8 SP)
**As a** customer
**I want to** update my profile settings
**So that** I can personalize my shopping experience

**Acceptance Criteria:**
- [ ] Update language preference
- [ ] Update currency preference
- [ ] Update timezone
- [ ] Profile picture upload (optional, future)
- [ ] Date of birth (optional, for birthday promotions)
- [ ] Newsletter subscription toggle

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create CustomerPreferences value object | 2h |
| P0 | Add preferences to Customer aggregate | 2h |
| P0 | Update CustomerEntity with preferences fields | 1h |
| P0 | Create migration for preferences columns | 1h |
| P1 | Create UpdatePreferencesCommand + Handler | 2h |
| P1 | API endpoint: PATCH /customers/{id}/preferences | 2h |
| P1 | Create CustomerPreferencesUpdated event | 1h |
| P2 | Unit tests for preferences | 2h |
| P2 | Functional tests for preferences API | 2h |

##### US-7.1.3: Customer Search & Filtering (5 SP)
**As an** admin
**I want to** search and filter customers
**So that** I can find specific customers quickly

**Acceptance Criteria:**
- [ ] Search by email (partial match)
- [ ] Search by name (partial match)
- [ ] Filter by segment
- [ ] Filter by active status
- [ ] Filter by registration date range
- [ ] Filter by loyalty points range
- [ ] Pagination support

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create SearchCustomersQuery with filters | 2h |
| P0 | Implement repository method with criteria builder | 3h |
| P1 | Update CustomerCollectionProvider with filters | 2h |
| P1 | Add API Platform filters to CustomerEntity | 2h |
| P2 | Functional tests for search/filter | 2h |

##### US-7.1.4: Customer Import/Export (8 SP)
**As an** admin
**I want to** bulk import/export customers
**So that** I can migrate data or perform bulk updates

**Acceptance Criteria:**
- [ ] Export customers to CSV
- [ ] Export customers to JSON
- [ ] Import customers from CSV (validate, skip duplicates)
- [ ] Async processing for large imports
- [ ] Progress tracking for imports
- [ ] Error report for failed imports

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P1 | Create ExportCustomersCommand + Handler | 3h |
| P1 | Create ImportCustomersCommand + Handler | 4h |
| P1 | Create async message handler for large imports | 2h |
| P1 | API endpoints: GET /customers/export, POST /customers/import | 3h |
| P2 | Unit tests for import/export logic | 2h |
| P2 | Functional tests for import/export API | 2h |

---

### Sprint 7.2: Loyalty Programs & Points System (2 weeks)

**Sprint Goal:** Implement comprehensive loyalty program with configurable rules and tiers

**Story Points:** 42

#### User Stories

##### US-7.2.1: Loyalty Program Configuration (13 SP)
**As a** tenant admin
**I want to** configure my loyalty program
**So that** I can reward customers according to my business rules

**Acceptance Criteria:**
- [ ] Create loyalty program with name and description
- [ ] Set points earning rate (points per currency unit spent)
- [ ] Set minimum order value for earning points
- [ ] Set points validity period (expiration)
- [ ] Enable/disable loyalty program per tenant
- [ ] Multi-tenant isolation

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create LoyaltyProgram aggregate | 3h |
| P0 | Create LoyaltyProgramId, EarningRate, ValidityPeriod value objects | 2h |
| P0 | Create LoyaltyProgramEntity (Doctrine) | 2h |
| P0 | Create LoyaltyProgramRepositoryInterface + implementation | 2h |
| P0 | Create migration for loyalty_programs table | 1h |
| P1 | Create CreateLoyaltyProgramCommand + Handler | 2h |
| P1 | Create UpdateLoyaltyProgramCommand + Handler | 2h |
| P1 | API endpoints: CRUD for /loyalty-programs | 4h |
| P2 | Unit tests for LoyaltyProgram aggregate | 3h |
| P2 | Functional tests for loyalty program API | 3h |

##### US-7.2.2: Loyalty Tiers (13 SP)
**As a** tenant admin
**I want to** create loyalty tiers
**So that** customers can progress and unlock better benefits

**Acceptance Criteria:**
- [ ] Create tiers (Bronze, Silver, Gold, Platinum)
- [ ] Set tier thresholds (points required for each tier)
- [ ] Set tier benefits (discount percentage, free shipping threshold)
- [ ] Automatic tier upgrade based on points
- [ ] Tier downgrade after inactivity (configurable)
- [ ] Tier badges/icons (future)

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create LoyaltyTier value object | 2h |
| P0 | Create LoyaltyTierConfig entity (within LoyaltyProgram) | 2h |
| P0 | Add tier tracking to Customer aggregate | 2h |
| P0 | Create CustomerTierChanged event | 1h |
| P1 | Create tier calculation service | 3h |
| P1 | Create UpgradeCustomerTierCommand + Handler | 2h |
| P1 | API endpoints: CRUD for /loyalty-programs/{id}/tiers | 3h |
| P1 | Event subscriber for automatic tier upgrades | 3h |
| P2 | Unit tests for tier logic | 3h |
| P2 | Functional tests for tier API | 2h |

##### US-7.2.3: Points Transaction History (8 SP)
**As a** customer
**I want to** see my loyalty points history
**So that** I can track my rewards

**Acceptance Criteria:**
- [ ] View points earned per transaction
- [ ] View points redeemed per transaction
- [ ] View points expired
- [ ] View current balance
- [ ] View points breakdown by source (purchase, referral, bonus)
- [ ] Pagination support

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create LoyaltyPointTransaction entity | 2h |
| P0 | Create migration for loyalty_point_transactions table | 1h |
| P0 | Create LoyaltyPointTransactionRepositoryInterface + implementation | 2h |
| P1 | Modify awardLoyaltyPoints to create transaction record | 2h |
| P1 | Create GetPointsHistoryQuery + Handler | 2h |
| P1 | API endpoint: GET /customers/{id}/loyalty-points/history | 2h |
| P2 | Unit tests for transaction history | 2h |
| P2 | Functional tests for history API | 2h |

##### US-7.2.4: Points Redemption (8 SP)
**As a** customer
**I want to** redeem my loyalty points
**So that** I can get discounts on my purchases

**Acceptance Criteria:**
- [ ] Redeem points for discount (configurable conversion rate)
- [ ] Minimum points required for redemption
- [ ] Maximum points per order (configurable)
- [ ] Points cannot be partially refunded
- [ ] Integration with checkout process

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create RedemptionRule value object | 2h |
| P0 | Add redemption rules to LoyaltyProgram | 2h |
| P1 | Create RedeemPointsCommand + Handler | 3h |
| P1 | Create ValidateRedemptionQuery + Handler | 2h |
| P1 | API endpoint: POST /customers/{id}/loyalty-points/redeem | 2h |
| P1 | Integration with CartPricingService (ACL) | 4h |
| P2 | Unit tests for redemption | 2h |
| P2 | Functional tests | 2h |

---

### Sprint 7.3: GDPR Compliance & Notifications (2 weeks)

**Sprint Goal:** Implement GDPR compliance features and notification preferences

**Story Points:** 38

#### User Stories

##### US-7.3.1: GDPR Data Export (10 SP)
**As a** customer
**I want to** export all my personal data
**So that** I can exercise my right to data portability

**Acceptance Criteria:**
- [ ] Request data export via API
- [ ] Generate comprehensive data package (JSON + readable PDF)
- [ ] Include: profile, addresses, orders, loyalty points, preferences
- [ ] Async processing (large data sets)
- [ ] Notification when export is ready
- [ ] Download link with expiration (24h)
- [ ] Rate limiting (max 1 request per 24h)

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create DataExportRequest entity | 2h |
| P0 | Create DataExportRequestRepositoryInterface + implementation | 2h |
| P0 | Create migration for data_export_requests table | 1h |
| P0 | Create RequestDataExportCommand + Handler | 3h |
| P1 | Create data aggregation service (collect from all contexts) | 4h |
| P1 | Create JSON export generator | 2h |
| P1 | Create PDF export generator (optional, P2) | 3h |
| P1 | API endpoint: POST /customers/{id}/data-export/request | 2h |
| P1 | API endpoint: GET /customers/{id}/data-export/{requestId}/download | 2h |
| P1 | Async message handler for export generation | 2h |
| P1 | Email notification when export ready | 2h |
| P2 | Unit tests | 3h |
| P2 | Functional tests | 2h |

##### US-7.3.2: GDPR Data Deletion (Account Deletion) (10 SP)
**As a** customer
**I want to** delete my account and personal data
**So that** I can exercise my right to be forgotten

**Acceptance Criteria:**
- [ ] Request account deletion
- [ ] Soft delete with retention period (30 days for recovery)
- [ ] Anonymize order history (keep for accounting, remove PII)
- [ ] Delete addresses, preferences, loyalty history
- [ ] Confirmation email before deletion
- [ ] Admin override for legal holds
- [ ] Audit trail of deletion

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create DeletionRequest entity with status workflow | 2h |
| P0 | Create migration for deletion_requests table | 1h |
| P0 | Create RequestAccountDeletionCommand + Handler | 3h |
| P0 | Create anonymization service | 4h |
| P1 | Create scheduled job for deletion execution (after retention period) | 3h |
| P1 | API endpoint: POST /customers/{id}/deletion-request | 2h |
| P1 | API endpoint: DELETE /customers/{id}/deletion-request (cancel) | 1h |
| P1 | Confirmation email workflow | 2h |
| P1 | Admin API: PUT /admin/deletion-requests/{id}/hold | 2h |
| P2 | Unit tests | 3h |
| P2 | Functional tests | 2h |

##### US-7.3.3: Consent Management (8 SP)
**As a** customer
**I want to** manage my marketing consents
**So that** I can control how my data is used

**Acceptance Criteria:**
- [ ] Marketing email consent (opt-in/opt-out)
- [ ] SMS marketing consent
- [ ] Third-party data sharing consent
- [ ] Cookie preferences (stored backend for logged-in users)
- [ ] Consent history with timestamps
- [ ] Easy withdrawal of consent

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create CustomerConsent value object | 2h |
| P0 | Create ConsentHistory entity | 2h |
| P0 | Add consents to Customer aggregate | 2h |
| P0 | Create migrations | 1h |
| P1 | Create UpdateConsentCommand + Handler | 2h |
| P1 | Create GetConsentHistoryQuery + Handler | 1h |
| P1 | API endpoints: GET/PUT /customers/{id}/consents | 2h |
| P1 | Create ConsentChanged event | 1h |
| P2 | Unit tests | 2h |
| P2 | Functional tests | 2h |

##### US-7.3.4: Notification Preferences (10 SP)
**As a** customer
**I want to** configure my notification preferences
**So that** I receive only relevant communications

**Acceptance Criteria:**
- [ ] Order confirmation notifications (email required)
- [ ] Shipping updates (email/SMS toggle)
- [ ] Promotional offers (email/SMS toggle)
- [ ] Price drop alerts (email toggle)
- [ ] Back in stock alerts (email toggle)
- [ ] Account security alerts (email required)
- [ ] Weekly digest option

**Technical Tasks:**
| Priority | Task | Estimate |
|----------|------|----------|
| P0 | Create NotificationPreferences value object | 2h |
| P0 | Add notification preferences to Customer aggregate | 2h |
| P0 | Update CustomerEntity | 1h |
| P0 | Create migration | 1h |
| P1 | Create UpdateNotificationPreferencesCommand + Handler | 2h |
| P1 | Create notification preference service | 3h |
| P1 | API endpoint: GET/PUT /customers/{id}/notification-preferences | 2h |
| P1 | Event subscriber integration (check preferences before sending) | 3h |
| P2 | Unit tests | 2h |
| P2 | Functional tests | 2h |

---

## Technical Architecture Decisions

### ADR-7.1: Customer Addresses as Separate Entity

**Context:** Customers need multiple addresses for shipping and billing.

**Decision:** Create CustomerAddress as a separate entity linked to Customer via foreign key, not embedded in Customer aggregate.

**Rationale:**
- Addresses can be independently managed without loading entire customer
- Supports address history preservation for orders
- Allows indexing for address-based queries
- Better performance for large address collections

**Consequences:**
- CustomerAddressRepository needed
- Eventual consistency between Customer and addresses acceptable
- Address deletion is soft delete to preserve order references

### ADR-7.2: Loyalty Program as Separate Aggregate

**Context:** Loyalty program has its own lifecycle independent of customers.

**Decision:** Create LoyaltyProgram as a separate aggregate root.

**Rationale:**
- Program configuration is tenant-level, not customer-level
- Rules and tiers change independently of customers
- Allows multiple programs per tenant (future)
- Clear separation of concerns

**Consequences:**
- LoyaltyProgram repository needed
- Customer references LoyaltyProgram by ID
- ACL between Customer and Pricing contexts for points redemption

### ADR-7.3: GDPR Deletion with Anonymization

**Context:** GDPR requires right to be forgotten while business needs order history.

**Decision:** Anonymize rather than hard delete, with configurable retention period.

**Rationale:**
- Legal requirement to maintain financial records
- Order history needed for accounting and returns
- Anonymization satisfies GDPR while preserving business data
- Retention period allows recovery from accidental deletion

**Consequences:**
- Anonymization service needed
- Scheduled job for actual deletion
- Admin override capability for legal holds

---

## API Endpoint Specifications

### Customer Addresses API

```yaml
/api/customers/{customerId}/addresses:
  get:
    summary: List customer addresses
    parameters:
      - name: X-Tenant-ID
        in: header
        required: true
      - name: type
        in: query
        schema:
          enum: [shipping, billing, both]
    responses:
      200:
        content:
          application/json:
            schema:
              type: array
              items:
                $ref: '#/components/schemas/CustomerAddress'
  post:
    summary: Add new address
    requestBody:
      content:
        application/json:
          schema:
            type: object
            required: [street, city, postalCode, country, type]
            properties:
              street: { type: string, maxLength: 255 }
              city: { type: string, maxLength: 100 }
              state: { type: string, maxLength: 100 }
              postalCode: { type: string, maxLength: 20 }
              country: { type: string, minLength: 2, maxLength: 2 }
              type: { enum: [shipping, billing, both] }
              isDefault: { type: boolean, default: false }
    responses:
      201:
        $ref: '#/components/schemas/CustomerAddress'

/api/customers/{customerId}/addresses/{addressId}:
  put:
    summary: Update address
  delete:
    summary: Remove address

/api/customers/{customerId}/addresses/{addressId}/default:
  patch:
    summary: Set as default address
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              type: { enum: [shipping, billing] }
```

### Loyalty Program API

```yaml
/api/loyalty-programs:
  get:
    summary: List loyalty programs (admin)
  post:
    summary: Create loyalty program
    requestBody:
      content:
        application/json:
          schema:
            type: object
            required: [name, earningRate]
            properties:
              name: { type: string }
              description: { type: string }
              earningRate: { type: number, minimum: 0.01 }
              minOrderValue: { type: integer, default: 0 }
              validityDays: { type: integer, nullable: true }
              isActive: { type: boolean, default: true }

/api/loyalty-programs/{id}/tiers:
  get:
    summary: List program tiers
  post:
    summary: Add tier
    requestBody:
      content:
        application/json:
          schema:
            type: object
            required: [name, threshold]
            properties:
              name: { type: string }
              threshold: { type: integer }
              discountPercentage: { type: number }
              freeShippingMinOrder: { type: integer }

/api/customers/{customerId}/loyalty-points/history:
  get:
    summary: Get points transaction history
    parameters:
      - name: page
        in: query
      - name: limit
        in: query
      - name: type
        in: query
        schema:
          enum: [earned, redeemed, expired, bonus]
    responses:
      200:
        content:
          application/json:
            schema:
              type: object
              properties:
                items:
                  type: array
                  items:
                    $ref: '#/components/schemas/LoyaltyPointTransaction'
                total: { type: integer }
                currentBalance: { type: integer }

/api/customers/{customerId}/loyalty-points/redeem:
  post:
    summary: Redeem loyalty points
    requestBody:
      content:
        application/json:
          schema:
            type: object
            required: [points]
            properties:
              points: { type: integer, minimum: 1 }
              orderId: { type: string, format: uuid }
```

### GDPR API

```yaml
/api/customers/{customerId}/data-export/request:
  post:
    summary: Request data export
    responses:
      202:
        content:
          application/json:
            schema:
              type: object
              properties:
                requestId: { type: string }
                status: { enum: [pending, processing, ready, expired] }
                estimatedCompletionTime: { type: string, format: datetime }

/api/customers/{customerId}/data-export/{requestId}/download:
  get:
    summary: Download data export
    responses:
      200:
        content:
          application/octet-stream:
            schema:
              type: string
              format: binary

/api/customers/{customerId}/deletion-request:
  post:
    summary: Request account deletion
    responses:
      202:
        content:
          application/json:
            schema:
              type: object
              properties:
                requestId: { type: string }
                confirmationRequired: { type: boolean }
                deletionScheduledFor: { type: string, format: datetime }
  delete:
    summary: Cancel deletion request

/api/customers/{customerId}/consents:
  get:
    summary: Get current consents
  put:
    summary: Update consents
    requestBody:
      content:
        application/json:
          schema:
            type: object
            properties:
              marketingEmail: { type: boolean }
              marketingSms: { type: boolean }
              thirdPartySharing: { type: boolean }
```

---

## Database Schema Changes

### New Tables

```sql
-- Customer Addresses
CREATE TABLE customer_addresses (
    id VARCHAR(36) PRIMARY KEY,
    customer_id VARCHAR(36) NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    street VARCHAR(255) NOT NULL,
    street2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20) NOT NULL,
    country CHAR(2) NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('shipping', 'billing', 'both')),
    is_default_shipping BOOLEAN DEFAULT FALSE,
    is_default_billing BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_customer_addresses_customer ON customer_addresses(customer_id);
CREATE INDEX idx_customer_addresses_tenant ON customer_addresses(tenant_id);
ALTER TABLE customer_addresses ENABLE ROW LEVEL SECURITY;

-- Loyalty Programs
CREATE TABLE loyalty_programs (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    name VARCHAR(100) NOT NULL,
    description TEXT,
    earning_rate DECIMAL(10,4) NOT NULL,
    min_order_value INTEGER DEFAULT 0,
    validity_days INTEGER,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    UNIQUE(tenant_id)
);

ALTER TABLE loyalty_programs ENABLE ROW LEVEL SECURITY;

-- Loyalty Tiers
CREATE TABLE loyalty_tiers (
    id VARCHAR(36) PRIMARY KEY,
    program_id VARCHAR(36) NOT NULL REFERENCES loyalty_programs(id) ON DELETE CASCADE,
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    name VARCHAR(50) NOT NULL,
    threshold INTEGER NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    free_shipping_min_order INTEGER,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_loyalty_tiers_program ON loyalty_tiers(program_id);
ALTER TABLE loyalty_tiers ENABLE ROW LEVEL SECURITY;

-- Loyalty Point Transactions
CREATE TABLE loyalty_point_transactions (
    id VARCHAR(36) PRIMARY KEY,
    customer_id VARCHAR(36) NOT NULL REFERENCES customers(id),
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    type VARCHAR(20) NOT NULL CHECK (type IN ('earned', 'redeemed', 'expired', 'bonus', 'adjustment')),
    points INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    reason VARCHAR(255) NOT NULL,
    order_id VARCHAR(36),
    expires_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_loyalty_transactions_customer ON loyalty_point_transactions(customer_id);
CREATE INDEX idx_loyalty_transactions_type ON loyalty_point_transactions(type);
ALTER TABLE loyalty_point_transactions ENABLE ROW LEVEL SECURITY;

-- Data Export Requests
CREATE TABLE data_export_requests (
    id VARCHAR(36) PRIMARY KEY,
    customer_id VARCHAR(36) NOT NULL REFERENCES customers(id),
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    status VARCHAR(20) NOT NULL CHECK (status IN ('pending', 'processing', 'ready', 'expired', 'failed')),
    file_path VARCHAR(500),
    download_token VARCHAR(100),
    expires_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP
);

CREATE INDEX idx_data_export_customer ON data_export_requests(customer_id);
ALTER TABLE data_export_requests ENABLE ROW LEVEL SECURITY;

-- Deletion Requests
CREATE TABLE deletion_requests (
    id VARCHAR(36) PRIMARY KEY,
    customer_id VARCHAR(36) NOT NULL REFERENCES customers(id),
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    status VARCHAR(20) NOT NULL CHECK (status IN ('pending', 'confirmed', 'processing', 'completed', 'cancelled', 'on_hold')),
    reason TEXT,
    hold_reason TEXT,
    scheduled_for TIMESTAMP,
    confirmed_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_deletion_requests_customer ON deletion_requests(customer_id);
CREATE INDEX idx_deletion_requests_status ON deletion_requests(status);
ALTER TABLE deletion_requests ENABLE ROW LEVEL SECURITY;

-- Consent History
CREATE TABLE consent_history (
    id VARCHAR(36) PRIMARY KEY,
    customer_id VARCHAR(36) NOT NULL REFERENCES customers(id),
    tenant_id VARCHAR(36) NOT NULL REFERENCES tenants(id),
    consent_type VARCHAR(50) NOT NULL,
    granted BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_consent_history_customer ON consent_history(customer_id);
ALTER TABLE consent_history ENABLE ROW LEVEL SECURITY;
```

### Alter Existing Tables

```sql
-- Add preferences to customers table
ALTER TABLE customers ADD COLUMN IF NOT EXISTS language_code CHAR(2) DEFAULT 'en';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS currency_code CHAR(3) DEFAULT 'USD';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'UTC';
ALTER TABLE customers ADD COLUMN IF NOT EXISTS date_of_birth DATE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS newsletter_subscribed BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS current_tier_id VARCHAR(36);

-- Add notification preferences
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notify_order_updates BOOLEAN DEFAULT TRUE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notify_shipping_updates BOOLEAN DEFAULT TRUE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notify_promotions BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notify_price_drops BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notify_back_in_stock BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS notify_via_sms BOOLEAN DEFAULT FALSE;

-- Add consents
ALTER TABLE customers ADD COLUMN IF NOT EXISTS consent_marketing_email BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS consent_marketing_sms BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS consent_third_party BOOLEAN DEFAULT FALSE;

-- Add GDPR fields
ALTER TABLE customers ADD COLUMN IF NOT EXISTS anonymized_at TIMESTAMP;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS deletion_requested_at TIMESTAMP;
```

---

## Test Strategy

### Unit Tests (Target: 95% coverage)

**Domain Layer:**
- CustomerAddress value object validation
- CustomerPreferences value object
- Customer aggregate address management methods
- LoyaltyProgram aggregate
- LoyaltyTier calculations
- Points redemption logic
- Anonymization service
- Consent management

**Application Layer:**
- All command handlers
- All query handlers
- Data export service
- Tier calculation service

### Integration Tests (Target: 85% coverage)

**Repository Tests:**
- CustomerAddressRepository CRUD operations
- LoyaltyProgramRepository operations
- LoyaltyPointTransactionRepository queries
- DataExportRequestRepository
- DeletionRequestRepository

**Database Tests:**
- RLS policy enforcement
- Foreign key constraints
- Cascade deletes

### Functional Tests (Target: 90% coverage)

**API Tests:**
- All new endpoints
- Authentication/authorization checks
- Validation error responses
- Pagination
- Filter combinations

**Integration Scenarios:**
- Full customer registration flow
- Address management flow
- Loyalty points earning and redemption
- Data export request and download
- Account deletion workflow

---

## Frontend Components

### Admin UI (Next.js 15 - /var/www/new_ecom/admin)

| Component | Priority | Description |
|-----------|----------|-------------|
| CustomerList | P0 | Data table with search, filters, pagination |
| CustomerDetail | P0 | Full customer profile view |
| CustomerAddresses | P1 | Address list and management |
| CustomerLoyalty | P1 | Points history, tier status |
| LoyaltyProgramConfig | P1 | Program settings form |
| LoyaltyTierConfig | P1 | Tier management table |
| CustomerImport | P2 | Bulk import wizard |
| GDPRRequests | P1 | Deletion/export request management |
| CustomerSegments | P1 | Segment management and bulk actions |

### Storefront UI (Next.js 15 - /var/www/new_ecom/storefront)

| Component | Priority | Description |
|-----------|----------|-------------|
| AccountDashboard | P0 | Overview with orders, points, tier |
| AddressBook | P0 | Address management |
| LoyaltyDashboard | P1 | Points balance, history, rewards |
| NotificationSettings | P1 | Notification preferences form |
| PrivacySettings | P1 | Consent management, data export/delete |
| ProfileEdit | P0 | Profile update form |
| TierProgress | P2 | Visual tier progress indicator |

---

## Definition of Done

### User Story
- [ ] All acceptance criteria met
- [ ] Unit tests written and passing (>=95% coverage)
- [ ] Integration tests written and passing
- [ ] Functional API tests written and passing
- [ ] Code reviewed and approved
- [ ] PHPStan level 8 passing
- [ ] Deptrac validation passing
- [ ] Documentation updated
- [ ] No regressions in existing tests

### Sprint
- [ ] All committed stories complete (DoD met)
- [ ] Sprint demo conducted
- [ ] Retrospective completed
- [ ] Sprint documentation updated
- [ ] All tests passing in CI
- [ ] Performance benchmarks met (<200ms API response)

---

## Success Metrics

### Quality Metrics
| Metric | Target | Measurement |
|--------|--------|-------------|
| Unit Test Coverage | >=95% | PHPUnit coverage report |
| Integration Test Coverage | >=85% | PHPUnit coverage report |
| Functional Test Coverage | >=90% | PHPUnit coverage report |
| PHPStan Level | 8 | CI pipeline |
| API Response Time | <200ms p95 | Performance tests |

### Business Metrics
| Metric | Target | Measurement |
|--------|--------|-------------|
| Customer Address Usage | >60% customers with saved addresses | Database query |
| Loyalty Program Participation | >30% customers earning points | Analytics |
| GDPR Compliance | 100% requests handled within 30 days | Request tracking |
| Email Delivery Rate | >95% | Email service metrics |

---

## Risks and Mitigations

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| GDPR anonymization complexity | Medium | High | Start with simple anonymization, iterate |
| Loyalty integration with Pricing | Medium | Medium | Use ACL pattern, minimal coupling |
| Large data export performance | Medium | Medium | Async processing, chunked generation |
| Address validation complexity | Low | Low | Use third-party validation service (future) |
| Points calculation edge cases | Medium | Medium | Comprehensive unit tests, business rule validation |

---

## Dependencies

### External Dependencies
- None for Phase 7 (self-contained)

### Internal Dependencies
- Order context (for points earning on purchase)
- Pricing context (for points redemption)
- Notification context (for sending emails)
- User context (for authentication)

### Cross-Context Integration Points

**Customer -> Pricing (ACL):**
```php
// Customer segment pricing
interface CustomerSegmentProviderInterface {
    public function getSegmentForCustomer(CustomerId $customerId): CustomerSegment;
    public function getTierDiscountForCustomer(CustomerId $customerId): ?Discount;
}
```

**Order -> Customer (ACL):**
```php
// Loyalty points on order completion
interface OrderCompletedHandler {
    public function handleOrderCompleted(OrderCompleted $event): void;
    // Awards loyalty points based on order total
}
```

---

## Sprint Velocity Planning

| Sprint | Story Points | Team Capacity | Confidence |
|--------|-------------|---------------|------------|
| 7.1 | 34 | 40 | High |
| 7.2 | 42 | 40 | Medium (new complexity) |
| 7.3 | 38 | 40 | High |

**Total:** 114 Story Points over 6 weeks

---

## References

### External Documentation
- [GDPR and CCPA in Loyalty Programs (2025)](https://rewardtheworld.net/gdpr-and-ccpa-in-loyalty-programs-2025-update/)
- [Loyalty Program Compliance Guide](https://www.brierley.com/blog/loyalty-program-compliance-with-data-security-privacy-regulations)
- [E-commerce GDPR Compliance Guide](https://www.gelato.com/blog/ecommerce-gdpr-compliance)
- [Data Privacy in Loyalty Programs](https://www.talon.one/blog/mastering-data-privacy-in-loyalty-and-promotional-strategies)

### Internal Documentation
- CLAUDE.md - Project guidelines and patterns
- docs/architecture/ddd-patterns-summary.md
- docs/guides/new-aggregate.md
- docs/technical/testing-guide.md

### Related Contexts
- src/Customer/ - Current implementation
- src/Pricing/ - Integration patterns (CartPricingService)
- src/Order/ - Event subscriber patterns (OrderPlacedSubscriber)
