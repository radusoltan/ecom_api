<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pricing;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Cart Pricing API endpoints.
 *
 * Tests:
 * - GET /cart/pricing - Get cart pricing breakdown
 * - POST /cart/apply-coupon - Apply coupon to cart
 */
final class CartPricingApiTest extends WebTestCase
{
    use TenantTestTrait;

    private CartRepositoryInterface $cartRepository;
    private PromotionRepositoryInterface $promotionRepository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->cartRepository = self::getContainer()->get(CartRepositoryInterface::class);
        $this->promotionRepository = self::getContainer()->get(PromotionRepositoryInterface::class);

        // Use default test tenant
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testGetCartPricingReturnsBreakdown(): void
    {
        $client = static::createClient();

        // Create a cart with items
        $cart = $this->createCartWithItem();

        // Make GET request
        $client->request('GET', '/api/cart/pricing', [], [], [
            'HTTP_X-Cart-ID' => $cart->id()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ]);

        $response = $client->getResponse();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('cart_id', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('subtotal', $data);
        $this->assertArrayHasKey('total_discounts', $data);
        $this->assertArrayHasKey('grand_total', $data);

        $this->assertSame($cart->id()->toString(), $data['cart_id']);
        $this->assertCount(1, $data['items']);
    }

    public function testGetCartPricingWithCouponCodes(): void
    {
        $client = static::createClient();

        // Create cart and active promotion
        $cart = $this->createCartWithItem();
        $promotion = $this->createActivePromotion();

        // Make GET request with coupon code
        $client->request('GET', '/api/cart/pricing', [
            'coupons' => $promotion->couponCode()->value(),
        ], [], [
            'HTTP_X-Cart-ID' => $cart->id()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Should have discounts applied
        $this->assertNotEmpty($data['cart_level_discounts']);
    }

    public function testGetCartPricingReturns404ForNonexistentCart(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/cart/pricing', [], [], [
            'HTTP_X-Cart-ID' => CartId::generate()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testApplyCouponReturnsDiscountDetails(): void
    {
        $client = static::createClient();

        // Create cart and active promotion
        $cart = $this->createCartWithItem();
        $promotion = $this->createActivePromotion();

        // Make POST request
        $client->request('POST', '/api/cart/apply-coupon', [], [], [
            'HTTP_X-Cart-ID' => $cart->id()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'coupon_code' => $promotion->couponCode()->value(),
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $data['items'][0]);
        $this->assertArrayHasKey('type', $data['items'][0]);
        $this->assertArrayHasKey('name', $data['items'][0]);
        $this->assertArrayHasKey('amount', $data['items'][0]);
        $this->assertSame('promotion', $data['items'][0]['type']);
    }

    public function testApplyCouponReturns400ForInvalidCoupon(): void
    {
        $client = static::createClient();

        // Create cart (no promotion)
        $cart = $this->createCartWithItem();

        // Make POST request with invalid coupon
        $client->request('POST', '/api/cart/apply-coupon', [], [], [
            'HTTP_X-Cart-ID' => $cart->id()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'coupon_code' => 'INVALID_COUPON',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testApplyCouponReturns400WhenConditionsNotMet(): void
    {
        $client = static::createClient();

        // Create cart with low total
        $cart = $this->createCartWithSmallItem();

        // Create promotion with minimum purchase requirement
        $promotion = $this->createPromotionWithMinPurchase(100.00);

        // Make POST request
        $client->request('POST', '/api/cart/apply-coupon', [], [], [
            'HTTP_X-Cart-ID' => $cart->id()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'coupon_code' => $promotion->couponCode()->value(),
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testApplyCouponReturns404ForNonexistentCart(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/cart/apply-coupon', [], [], [
            'HTTP_X-Cart-ID' => CartId::generate()->toString(),
            'HTTP_X-Tenant-ID' => $this->tenantId->toString(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'coupon_code' => 'TESTCOUPON',
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    // Helper methods

    private function createCartWithItem(): Cart
    {
        $cart = Cart::create(
            CartId::generate(),
            $this->tenantId,
            null,
            SessionId::generate()
        );

        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(100.00, 'USD')
        );

        $this->cartRepository->save($cart);

        return $cart;
    }

    private function createCartWithSmallItem(): Cart
    {
        $cart = Cart::create(
            CartId::generate(),
            $this->tenantId,
            null,
            SessionId::generate()
        );

        $cart->addItem(
            ProductId::generate(),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(10.00, 'USD')
        );

        $this->cartRepository->save($cart);

        return $cart;
    }

    private function createActivePromotion(): Promotion
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Test Promotion',
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString('TESTCOUPON'),
            []
        );

        $promotion->activate();
        $this->promotionRepository->save($promotion);

        return $promotion;
    }

    private function createPromotionWithMinPurchase(float $minPurchase): Promotion
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Min Purchase Promotion',
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString('MINPURCHASE'),
            ['min_purchase' => $minPurchase]
        );

        $promotion->activate();
        $this->promotionRepository->save($promotion);

        return $promotion;
    }

    protected function cleanupTestData(): void
    {
        // Clean up test data for isolation
        // Implementation depends on your cleanup strategy
    }
}
