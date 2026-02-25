<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pricing;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
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

/**
 * Functional tests for Cart Pricing API endpoints.
 *
 * Tests:
 * - GET /cart/pricing - Get cart pricing breakdown
 * - POST /cart/apply-coupon - Apply coupon to cart
 */
final class CartPricingApiTest extends ApiTestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private PromotionRepositoryInterface $promotionRepository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $client = static::createClient();
        $container = $client->getContainer();

        $this->cartRepository = $container->get(CartRepositoryInterface::class);
        $this->promotionRepository = $container->get(PromotionRepositoryInterface::class);

        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);

        $connection = $container->get('doctrine')->getManager()->getConnection();
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", self::DEFAULT_TENANT_ID)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testGetCartPricingReturnsBreakdown(): void
    {
        $cart = $this->createCartWithItem();

        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/cart/pricing', [
            'headers' => [
                'X-Cart-ID' => $cart->id()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('cartId', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('subtotal', $data);
        $this->assertArrayHasKey('totalDiscounts', $data);
        $this->assertArrayHasKey('grandTotal', $data);

        $this->assertSame($cart->id()->toString(), $data['cartId']);
        $this->assertCount(1, $data['items']);
    }

    public function testGetCartPricingWithCouponCodes(): void
    {
        $cart = $this->createCartWithItem();
        $promotion = $this->createPromotion('COUPON' . strtoupper(substr(md5(uniqid()), 0, 6)));

        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/cart/pricing?coupons=' . $promotion->couponCode()->toString(), [
            'headers' => [
                'X-Cart-ID' => $cart->id()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('cartLevelDiscounts', $data);
    }

    public function testGetCartPricingReturns404ForNonexistentCart(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/cart/pricing', [
            'headers' => [
                'X-Cart-ID' => CartId::generate()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testApplyCouponReturnsDiscountDetails(): void
    {
        $cart = $this->createCartWithItem();
        $couponCode = 'APPLY' . strtoupper(substr(md5(uniqid()), 0, 6));
        $promotion = $this->createPromotion($couponCode);

        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/cart/apply-coupon', [
            'headers' => [
                'X-Cart-ID' => $cart->id()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'coupon_code' => $couponCode,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items']);
        $this->assertArrayHasKey('id', $data['items'][0]);
        $this->assertArrayHasKey('type', $data['items'][0]);
        $this->assertArrayHasKey('name', $data['items'][0]);
        $this->assertArrayHasKey('amount', $data['items'][0]);
        $this->assertSame('promotion', $data['items'][0]['type']);
    }

    public function testApplyCouponReturns400ForInvalidCoupon(): void
    {
        $cart = $this->createCartWithItem();

        $client = static::createClient();
        $client->request('POST', '/api/v1/cart/apply-coupon', [
            'headers' => [
                'X-Cart-ID' => $cart->id()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'coupon_code' => 'NOTEXIST0000',
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testApplyCouponReturns400WhenConditionsNotMet(): void
    {
        $cart = $this->createCartWithSmallItem();
        $couponCode = 'MINPUR' . strtoupper(substr(md5(uniqid()), 0, 6));
        $promotion = $this->createPromotion($couponCode, ['min_purchase' => 100.00]);

        $client = static::createClient();
        $client->request('POST', '/api/v1/cart/apply-coupon', [
            'headers' => [
                'X-Cart-ID' => $cart->id()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'coupon_code' => $couponCode,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testApplyCouponReturns404ForNonexistentCart(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/cart/apply-coupon', [
            'headers' => [
                'X-Cart-ID' => CartId::generate()->toString(),
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'json' => [
                'coupon_code' => 'TESTCOUPON',
            ],
        ]);

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
            Money::fromScalars(100, 'USD')
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
            Money::fromScalars(10, 'USD')
        );

        $this->cartRepository->save($cart);

        return $cart;
    }

    private function createPromotion(string $couponCode, array $conditions = []): Promotion
    {
        $promotion = Promotion::create(
            PromotionId::generate(),
            $this->tenantId,
            'Test Promotion ' . $couponCode,
            PromotionType::coupon(),
            Discount::percentage(10.0),
            100,
            CouponCode::fromString($couponCode),
            $conditions
        );

        $promotion->activate();
        $this->promotionRepository->save($promotion);

        return $promotion;
    }
}
