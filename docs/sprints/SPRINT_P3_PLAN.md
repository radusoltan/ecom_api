# Sprint P3: Platform Stabilization and Notifications Foundation

**Sprint Goal**: Achieve 90%+ functional test pass rate, implement Notifications bounded context foundation, and prepare for frontend integration.

**Sprint Duration**: 5-7 days (estimated)

**Epic**: Epic 7 - Platform Stabilization and Notifications

---

## Executive Summary

Sprint P3 focuses on a balanced approach between stabilizing the existing platform (reducing technical debt) and laying the foundation for the Notifications bounded context. The sprint addresses critical functional test failures while beginning the event-driven notification system that will power email, SMS, and webhook integrations.

### Current State (After Epic 6)

| Metric | Current | Target | Gap |
|--------|---------|--------|-----|
| Unit Tests | 2,126 (100%) | 2,126 | - |
| Integration Tests | 220 (100%) | 220 | - |
| Functional Tests | 512 (~80%) | 512 (90%+) | +10% |
| PHPStan Errors | 0 | 0 | - |
| Bounded Contexts | 10 implemented | 11 (add Notifications) | +1 context |

### Strategic Rationale

**Why prioritize test stabilization over new features:**
1. **Technical Debt Cost**: 58 failing/erroring functional tests block CI/CD pipeline reliability
2. **Foundation Quality**: The Notifications context depends on stable event-driven infrastructure
3. **Frontend Readiness**: Storefront and admin integrations require stable API contracts
4. **Business Continuity**: Tax Rule API issues affect order checkout flow (revenue-critical)

**Why include Notifications foundation:**
1. **Password Reset Dependency**: 6 skipped tests await Messenger integration
2. **Event-Driven Architecture**: Platform already has domain events; Notifications completes the loop
3. **Customer Experience**: Email notifications are MVP requirement for order confirmations

---

## Sprint Backlog

### P0 (Must Have) - Test Stabilization

#### P3-001: Fix Tax Rule API Test Failures (14 failures)

**Priority**: P0 (Critical)
**Effort**: 4-6 hours
**Business Value**: HIGH - Tax calculation affects checkout

**Problem Analysis**:
The Tax Rule API has 14 test failures primarily related to:
1. Authentication/authorization edge cases
2. Validation error response format
3. Hydra pagination format inconsistencies

**Root Causes Identified**:
- `TaxRuleCollectionProvider` returns plain array instead of Hydra-formatted response
- Some validation tests expect 422, receiving 400 or 500
- Tenant isolation tests may have RLS context issues

**Acceptance Criteria**:
- [ ] All 30+ TaxRuleApiTest tests pass
- [ ] Hydra pagination format correct (`hydra:member`, `hydra:totalItems`, `hydra:view`)
- [ ] Validation errors return 422 with proper error structure
- [ ] Tenant isolation enforced correctly

**Technical Tasks**:
1. Update `TaxRuleCollectionProvider` to return proper Paginator
2. Add `countByTenant()` method to repository if missing
3. Standardize validation error responses (use API Platform constraints)
4. Verify RLS context in all test methods

**Files to Modify**:
- `src/Tax/Presentation/Api/Provider/TaxRuleCollectionProvider.php`
- `src/Tax/Infrastructure/Persistence/Doctrine/Repository/DoctrineTaxRuleRepository.php`
- `src/Tax/Presentation/Api/Resource/TaxRuleResource.php`

---

#### P3-002: Fix Catalog Variant API Test Failures (2 failures)

**Priority**: P0 (Critical)
**Effort**: 2-3 hours
**Business Value**: HIGH - Product variants are core catalog feature

**Problem Analysis**:
The VariantApiTest has 2 failures related to ConfigurableProduct lookup for UPDATE and DELETE operations.

**Root Causes**:
- `VariantItemProvider` cannot find parent ConfigurableProduct during PATCH/DELETE
- ProductId passed as query parameter may not be correctly extracted
- EntityManager identity map conflicts between test setup and API call

**Acceptance Criteria**:
- [ ] `testUpdateVariant()` passes
- [ ] `testDeleteVariant()` passes
- [ ] All 8 VariantApiTest tests pass consistently

**Technical Tasks**:
1. Debug `VariantItemProvider::provide()` for PATCH/DELETE operations
2. Ensure productId is correctly passed through uriVariables or query params
3. Add proper error handling for missing ConfigurableProduct
4. Verify EntityManager state between repository save and API call

**Files to Modify**:
- `src/Catalog/Presentation/Api/Provider/VariantItemProvider.php`
- `src/Catalog/Presentation/Api/Processor/VariantProcessor.php`
- `tests/Functional/Catalog/Api/VariantApiTest.php` (if needed for clarity)

---

#### P3-003: Fix Password Reset Messenger Integration (6 skipped)

**Priority**: P0 (Critical)
**Effort**: 4-6 hours
**Business Value**: HIGH - Password reset is security-critical user flow

**Problem Analysis**:
Password reset tests are skipped due to `HandlerFailedException` when processing async messages. The implementation exists but has integration issues with Symfony Messenger.

**Root Causes Identified**:
- `RequestPasswordResetHandler` may throw exception during token persistence
- `PasswordResetTokenEntity` schema may not be correctly migrated
- Messenger async routing may need sync override for tests

**Technical Approach**:
Based on [Symfony Messenger best practices](https://symfony.com/doc/current/messenger.html), we should:
1. Ensure `password_reset_tokens` table exists with correct schema
2. Configure sync transport for test environment
3. Add proper exception handling in handler

**Acceptance Criteria**:
- [ ] All 6 PasswordResetApiTest tests run (not skipped)
- [ ] Password reset request returns 200/202
- [ ] Token-based password reset works end-to-end
- [ ] Tests use sync Messenger transport

**Technical Tasks**:
1. Verify/create migration for `password_reset_tokens` table
2. Configure `config/packages/test/messenger.yaml` with sync transport
3. Debug `RequestPasswordResetHandler` token persistence
4. Add `PasswordResetTokenRepository` if missing

**Files to Modify**:
- `src/User/Infrastructure/Persistence/Doctrine/Entity/PasswordResetTokenEntity.php`
- `src/User/Application/Command/RequestPasswordReset/RequestPasswordResetHandler.php`
- `config/packages/test/messenger.yaml`
- `migrations/VersionXXXX_CreatePasswordResetTokens.php` (if needed)

---

### P1 (Should Have) - Notifications Foundation

#### P3-004: Create Notifications Bounded Context Structure

**Priority**: P1 (Important)
**Effort**: 3-4 hours
**Business Value**: MEDIUM-HIGH - Foundation for all notification features

**Description**:
Create the foundational structure for the Notifications bounded context following DDD/CQRS patterns.

**Directory Structure**:
```
src/Notifications/
├── Domain/
│   ├── Model/
│   │   ├── Notification.php              # Aggregate root
│   │   ├── NotificationId.php            # Value object
│   │   ├── NotificationType.php          # Enum (email, sms, webhook)
│   │   └── NotificationStatus.php        # Enum (pending, sent, failed)
│   ├── Repository/
│   │   └── NotificationRepositoryInterface.php
│   └── Event/
│       ├── NotificationCreated.php
│       ├── NotificationSent.php
│       └── NotificationFailed.php
├── Application/
│   ├── Command/
│   │   ├── SendNotification/
│   │   │   ├── SendNotification.php
│   │   │   └── SendNotificationHandler.php
│   │   └── MarkNotificationSent/
│   │       ├── MarkNotificationSent.php
│   │       └── MarkNotificationSentHandler.php
│   ├── Query/
│   │   └── GetNotificationsByTenant/
│   │       ├── GetNotificationsByTenant.php
│   │       └── GetNotificationsByTenantHandler.php
│   └── EventSubscriber/
│       ├── OrderPlacedNotificationSubscriber.php
│       └── PasswordResetNotificationSubscriber.php
└── Infrastructure/
    ├── Persistence/Doctrine/
    │   ├── Entity/NotificationEntity.php
    │   └── Repository/DoctrineNotificationRepository.php
    ├── Messenger/
    │   └── NotificationMessageHandler.php
    └── Email/
        └── SymfonyEmailSender.php
```

**Acceptance Criteria**:
- [ ] Domain model with Notification aggregate created
- [ ] Value objects (NotificationId, NotificationType, NotificationStatus) implemented
- [ ] Repository interface defined
- [ ] Basic command/query structure in place
- [ ] Deptrac rules updated to include Notifications context

**Files to Create**:
- All files in the directory structure above
- Update `deptrac.yaml` with Notifications layer rules

---

#### P3-005: Implement Email Notification Infrastructure

**Priority**: P1 (Important)
**Effort**: 4-5 hours
**Business Value**: MEDIUM-HIGH - Email is primary notification channel

**Description**:
Implement the email sending infrastructure using Symfony Mailer with Messenger async support.

**Technical Approach**:
Following [Symfony Mailer async documentation](https://symfony.com/doc/current/mailer.html), configure:
1. Mailer DSN for email provider (Mailtrap for dev, production provider TBD)
2. Messenger routing for `SendEmailMessage`
3. Email templates using Twig

**Configuration**:
```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        envelope:
            sender: '%env(MAILER_SENDER)%'

# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
        routing:
            'Symfony\Component\Mailer\Messenger\SendEmailMessage': async
            'App\Notifications\Application\Command\SendNotification\SendNotification': async
```

**Email Templates**:
```
templates/emails/
├── order_confirmation.html.twig
├── order_confirmation.txt.twig
├── password_reset.html.twig
├── password_reset.txt.twig
├── welcome.html.twig
└── welcome.txt.twig
```

**Acceptance Criteria**:
- [ ] Mailer configured with test DSN
- [ ] Messenger async routing configured
- [ ] Base email templates created
- [ ] `SymfonyEmailSender` service implemented
- [ ] Integration test for email sending passes

---

#### P3-006: Connect Domain Events to Notifications

**Priority**: P1 (Important)
**Effort**: 3-4 hours
**Business Value**: HIGH - Enables automatic notifications on business events

**Description**:
Create event subscribers that listen to domain events and dispatch notification commands.

**Event Mappings**:
| Domain Event | Notification Type | Template |
|--------------|-------------------|----------|
| `OrderPlaced` | Email | order_confirmation |
| `OrderStatusChanged` | Email | order_status_update |
| `UserRegistered` | Email | welcome |
| `PasswordResetRequested` | Email | password_reset |

**Implementation Pattern**:
```php
final class OrderPlacedNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly CustomerRepositoryInterface $customerRepository,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [OrderPlaced::class => 'onOrderPlaced'];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $customer = $this->customerRepository->findById($event->customerId());

        $command = new SendNotification(
            notificationId: NotificationId::generate(),
            tenantId: $event->tenantId(),
            type: NotificationType::EMAIL,
            recipientEmail: $customer->email(),
            templateName: 'order_confirmation',
            context: [
                'orderId' => $event->orderId()->toString(),
                'orderNumber' => $event->orderNumber(),
                'totalAmount' => $event->totalAmount()->format(),
            ],
        );

        $this->commandBus->dispatch($command);
    }
}
```

**Acceptance Criteria**:
- [ ] OrderPlacedNotificationSubscriber implemented
- [ ] PasswordResetNotificationSubscriber implemented
- [ ] Notifications recorded in database
- [ ] Email sent via Messenger async queue
- [ ] Unit tests for subscribers at 100% coverage

---

### P2 (Nice to Have) - Developer Experience

#### P3-007: Add Notification API Endpoints

**Priority**: P2 (Nice to Have)
**Effort**: 2-3 hours
**Business Value**: MEDIUM - Admin visibility into notifications

**Description**:
Create API Platform resources for viewing notification status (admin only).

**Endpoints**:
- `GET /api/v1/notifications` - List notifications for tenant (paginated)
- `GET /api/v1/notifications/{id}` - Get single notification details
- `POST /api/v1/notifications/{id}/retry` - Retry failed notification

**Acceptance Criteria**:
- [ ] NotificationResource with API Platform attributes
- [ ] Collection provider with pagination
- [ ] Item provider for single notification
- [ ] Retry processor for failed notifications
- [ ] Functional tests for all endpoints

---

#### P3-008: Documentation and Testing

**Priority**: P2 (Nice to Have)
**Effort**: 2-3 hours
**Business Value**: MEDIUM - Long-term maintainability

**Description**:
Update documentation and ensure comprehensive test coverage.

**Documentation Updates**:
1. Update `CLAUDE.md` with Notifications context details
2. Create `docs/guides/notifications.md` implementation guide
3. Add ADR for notification architecture decisions
4. Update API documentation (OpenAPI)

**Test Coverage Targets**:
| Component | Target Coverage |
|-----------|-----------------|
| Notification domain model | 100% |
| Value objects | 100% |
| Event subscribers | 100% |
| Command handlers | 100% |
| API endpoints | 90%+ |

**Acceptance Criteria**:
- [ ] CLAUDE.md updated with Notifications context
- [ ] notifications.md guide created
- [ ] ADR-012 for notifications created
- [ ] Test coverage meets targets

---

## Sprint Execution Plan

### Parallel Execution Matrix

```
Day 1-2 (Stabilization Focus):
  [Track A] P3-001: Tax Rule API Fixes --------->
  [Track B] P3-002: Variant API Fixes ---------->
  [Track C] P3-003: Password Reset Integration ->

Day 3-4 (Notifications Foundation):
  [Track A] P3-004: Notifications Context Structure ------>
  [Track B] P3-005: Email Infrastructure --------------->
  [Track C] Integration Testing & Bug Fixes ------------>

Day 5-6 (Integration & Polish):
  [Track A] P3-006: Domain Event -> Notification Connection
  [Track B] P3-007: Notification API Endpoints
  [Track C] P3-008: Documentation & Testing

Day 7 (Buffer):
  [All Tracks] Final integration, edge cases, documentation
```

### Task Dependencies Graph

```
P3-001 (Tax API) --------\
                          \
P3-002 (Variant API) ------+---> P3-006 (Event Integration)
                          /            |
P3-003 (Password Reset) -/             v
        |                       P3-007 (Notification API)
        |                              |
        v                              v
P3-004 (Context Structure) -----> P3-008 (Documentation)
        |
        v
P3-005 (Email Infrastructure) -> P3-006 (Event Integration)
```

---

## Effort Estimation

| Task | Min Hours | Max Hours | Expected | Priority |
|------|-----------|-----------|----------|----------|
| P3-001: Tax Rule API | 4h | 6h | 5h | P0 |
| P3-002: Variant API | 2h | 3h | 2.5h | P0 |
| P3-003: Password Reset | 4h | 6h | 5h | P0 |
| P3-004: Context Structure | 3h | 4h | 3.5h | P1 |
| P3-005: Email Infrastructure | 4h | 5h | 4.5h | P1 |
| P3-006: Event Integration | 3h | 4h | 3.5h | P1 |
| P3-007: Notification API | 2h | 3h | 2.5h | P2 |
| P3-008: Documentation | 2h | 3h | 2.5h | P2 |
| Integration Testing | 2h | 4h | 3h | - |
| Buffer | 2h | 4h | 3h | - |
| **Total** | **28h** | **42h** | **35h** | - |

**Sprint Capacity**: 5-7 days with 1-2 parallel work tracks

---

## Risk Assessment

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Tax Rule fixes cascade to other endpoints | Medium | Low | Run full test suite after changes |
| Messenger integration complex | High | Medium | Use sync transport for tests, iterate |
| Email provider rate limiting | Low | Medium | Use Mailtrap for dev, configure retries |
| Domain event integration breaks existing flows | High | Low | Add integration tests first |
| Notification persistence impacts performance | Medium | Low | Index notification table, use async |

---

## Success Metrics

| Metric | Before | Target | Verification |
|--------|--------|--------|--------------|
| Functional Test Pass Rate | ~80% | 90%+ | `vendor/bin/phpunit tests/Functional/` |
| Skipped Tests | 6 | 0 | No `markTestSkipped` in output |
| Tax Rule API Tests | 16/30 passing | 30/30 | TaxRuleApiTest all green |
| Variant API Tests | 6/8 passing | 8/8 | VariantApiTest all green |
| Password Reset Tests | 0/6 (skipped) | 6/6 | PasswordResetApiTest all pass |
| Notifications Context | 0% | 100% structure | Directory structure complete |
| Email Infrastructure | N/A | Working | Integration test passes |

---

## Definition of Done

### Per Task
- [ ] Code implemented following DDD/CQRS patterns
- [ ] Unit tests at 100% coverage for domain logic
- [ ] Integration tests for repository operations
- [ ] Functional tests for API endpoints
- [ ] PHPStan passes at level 8
- [ ] Deptrac validation passes
- [ ] Code review completed
- [ ] Documentation updated

### Sprint Complete
- [ ] All P0 tasks completed
- [ ] 90%+ functional test pass rate
- [ ] Notifications context structure in place
- [ ] Email infrastructure tested
- [ ] CLAUDE.md updated
- [ ] Sprint completion report created

---

## Post-Sprint Actions

1. **Update CLAUDE.md** with:
   - Notifications bounded context documentation
   - Updated test counts
   - New email/notification patterns

2. **Create Sprint P3 Completion Report** documenting:
   - Final test pass rates
   - Notifications context status
   - Remaining technical debt

3. **Plan Sprint P4** options:
   - Option A: Complete Notifications (SMS, webhooks)
   - Option B: Frontend integration sprint
   - Option C: Performance optimization
   - Option D: Fulfillment bounded context

4. **Technical Debt Tracking**:
   - Document any workarounds used
   - Create tickets for deferred improvements

---

## Quick Reference Commands

```bash
# Test database reset
./tests/reset_test_db.sh

# Run specific test files
vendor/bin/phpunit tests/Functional/Tax/Api/TaxRuleApiTest.php
vendor/bin/phpunit tests/Functional/Catalog/Api/VariantApiTest.php
vendor/bin/phpunit tests/Functional/User/Api/PasswordResetApiTest.php

# Run all functional tests
vendor/bin/phpunit tests/Functional/

# Run PHPStan
vendor/bin/phpstan analyse

# Run Deptrac
vendor/bin/deptrac analyse --config-file=deptrac.yaml

# Messenger commands (for debugging)
symfony console messenger:failed:show
symfony console messenger:consume async -vv --limit=10

# Email testing (when Mailtrap configured)
symfony console mailer:test admin@example.com
```

---

## Appendix: Notifications Domain Model

### Aggregate Root: Notification

```php
final class Notification
{
    private function __construct(
        private NotificationId $id,
        private TenantId $tenantId,
        private NotificationType $type,
        private string $recipientEmail,
        private ?string $recipientPhone,
        private string $subject,
        private string $body,
        private NotificationStatus $status,
        private int $attemptCount,
        private ?\DateTimeImmutable $sentAt,
        private ?\DateTimeImmutable $failedAt,
        private ?string $failureReason,
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        NotificationId $id,
        TenantId $tenantId,
        NotificationType $type,
        string $recipientEmail,
        string $subject,
        string $body,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            type: $type,
            recipientEmail: $recipientEmail,
            recipientPhone: null,
            subject: $subject,
            body: $body,
            status: NotificationStatus::PENDING,
            attemptCount: 0,
            sentAt: null,
            failedAt: null,
            failureReason: null,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function markAsSent(): void
    {
        $this->status = NotificationStatus::SENT;
        $this->sentAt = new \DateTimeImmutable();
        $this->attemptCount++;
    }

    public function markAsFailed(string $reason): void
    {
        $this->status = NotificationStatus::FAILED;
        $this->failedAt = new \DateTimeImmutable();
        $this->failureReason = $reason;
        $this->attemptCount++;
    }

    public function retry(): void
    {
        if ($this->status !== NotificationStatus::FAILED) {
            throw new \DomainException('Can only retry failed notifications');
        }
        $this->status = NotificationStatus::PENDING;
        $this->failedAt = null;
        $this->failureReason = null;
    }
}
```

### Value Objects

```php
enum NotificationType: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case WEBHOOK = 'webhook';
    case PUSH = 'push';
}

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```

---

**Document Version**: 1.0
**Created**: 2025-11-27
**Sprint Status**: PLANNED
**Author**: Product Strategy Analysis
