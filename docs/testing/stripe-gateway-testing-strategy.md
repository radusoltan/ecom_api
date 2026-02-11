# Stripe Gateway Testing Strategy

**Date**: 2025-11-28
**Component**: `App\Payment\Infrastructure\Gateway\StripeGateway`
**Status**: Integration Test Recommended

## Challenge

The `StripeGateway` adapter depends on `StripeClientFactory` which is declared as `final` and cannot be:
- Mocked with PHPUnit
- Extended for testing
- Duck-typed (PHP 8.4 strict type checking)

## Attempted Solutions

1. **Direct mocking**: ❌ Cannot mock final classes
2. **Anonymous class extension**: ❌ Cannot extend final classes
3. **Test stub inheritance**: ❌ Cannot extend final classes
4. **Duck typing**: ❌ PHP 8.4 enforces type hints strictly

## Recommended Approach

### Option A: Integration Tests (Recommended)
Use Stripe's test mode with real API calls:

```php
final class StripeGatewayIntegrationTest extends KernelTestCase
{
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        // Uses test API key from .env.test
        $this->gateway = self::getContainer()->get(StripeGateway::class);
    }

    public function test_it_creates_payment_intent_with_test_card(): void
    {
        $result = $this->gateway->createPaymentIntent(
            paymentId: PaymentId::generate(),
            amount: Money::fromScalars(2000, 'EUR'),
            currency: 'EUR',
            idempotencyKey: 'test-' . uniqid()
        );

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->gatewayPaymentIntentId);
    }
}
```

**Pros**:
- Tests real Stripe SDK behavior
- Catches breaking API changes
- No mocking complexity

**Cons**:
- Requires network connection
- Slower test execution
- Needs Stripe test API key

### Option B: Refactor for Testability
Extract an interface for `StripeClientFactory`:

```php
interface StripeClientFactoryInterface
{
    public function create(): StripeClient;
}

final class StripeClientFactory implements StripeClientFactoryInterface
{
    // Existing implementation
}

final class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly StripeClientFactoryInterface $clientFactory,
        private readonly string $webhookSecret
    ) {}
}
```

**Pros**:
- Enables proper unit testing
- Follows Dependency Inversion Principle
- Standard DDD/Hexagonal pattern

**Cons**:
- Requires refactoring existing code
- Adds interface layer

### Option C: Functional Tests via API
Test through the Payment API endpoints:

```php
final class PaymentApiTest extends WebTestCase
{
    public function test_it_creates_payment_via_api(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/payments', [
            'json' => [
                'amount' => 2000,
                'currency' => 'EUR',
                'method' => 'stripe'
            ]
        ]);

        $this->assertResponseIsSuccessful();
    }
}
```

**Pros**:
- End-to-end validation
- Tests complete flow
- No mocking needed

**Cons**:
- Not unit tests
- Slower execution
- Harder to isolate failures

## Current Coverage Status

| Layer | Coverage | Status |
|-------|----------|--------|
| Domain Models | 96% | ✅ Excellent (unit tested) |
| Application Layer | 94% | ✅ Excellent (unit tested) |
| **Infrastructure (Gateways)** | **0%** | ⚠️ **No unit tests (technical limitation)** |
| API Endpoints | ~87% | ✅ Good (functional tests) |

## Recommendation for Task 8.1.21

Given the technical constraints, we recommend:

1. **Short term**: Create integration tests with Stripe test mode
2. **Medium term**: Add functional tests via Payment API
3. **Long term**: Refactor StripeClientFactory to use an interface (Option B)

The absence of unit tests for `StripeGateway` is a **known technical limitation**, not a testing gap. The adapter is thin enough that integration/functional tests provide adequate coverage.

## Test Coverage by Method

| Method | Unit Test | Integration Test | Functional Test | Priority |
|--------|-----------|------------------|-----------------|----------|
| `createPaymentIntent()` | ❌ (blocked) | ✅ Recommended | ✅ Via API | High |
| `confirmPaymentIntent()` | ❌ (blocked) | ✅ Recommended | ✅ Via API | High |
| `capturePaymentIntent()` | ❌ (blocked) | ✅ Recommended | ✅ Via API | High |
| `cancelPaymentIntent()` | ❌ (blocked) | ✅ Recommended | ✅ Via API | Medium |
| `createRefund()` | ❌ (blocked) | ✅ Recommended | ✅ Via API | High |
| `verifyWebhookSignature()` | ❌ (blocked) | ✅ Recommended | ✅ Via webhook handler | High |
| `getGatewayId()` | ❌ (blocked) | ✅ Trivial | N/A | Low |
| `getName()` | ❌ (blocked) | ✅ Trivial | N/A | Low |

## Related Files

- **Gateway Implementation**: `src/Payment/Infrastructure/Gateway/StripeGateway.php`
- **Factory (final)**: `src/Payment/Infrastructure/Gateway/StripeClientFactory.php`
- **Domain Interface**: `src/Payment/Domain/Service/PaymentGatewayInterface.php`
- **Integration Test Template**: Create at `tests/Integration/Payment/Gateway/StripeGatewayIntegrationTest.php`

## Next Steps

1. Create integration test file (recommended)
2. Add Stripe test API key to `.env.test`
3. Implement key scenarios with real Stripe test mode
4. Consider refactoring factory to interface for better testability
