<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Api\Controller;

use App\Pricing\Application\Service\PromotionApplicationService;
use App\Pricing\Presentation\Api\Controller\ValidateCouponController;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

#[\PHPUnit\Framework\Attributes\CoversClass(ValidateCouponController::class)]
final class ValidateCouponControllerTest extends TestCase
{
    private PromotionApplicationService $promotionService;
    private LoggerInterface $logger;
    private string $tenantId = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->promotionService = $this->createMock(PromotionApplicationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function buildController(): ValidateCouponController
    {
        return new ValidateCouponController($this->promotionService, $this->logger);
    }

    private function buildRequest(
        string $body,
        ?string $tenantId = null,
    ): Request {
        $request = Request::create('/api/v1/validate-coupon', 'POST', [], [], [], [], $body);
        if (null !== $tenantId) {
            $request->headers->set('X-Tenant-ID', $tenantId);
        }

        return $request;
    }

    // ---------------------------------------------------------------
    // Missing tenant header
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenTenantIdHeaderMissing(): void
    {
        $request = $this->buildRequest('{}');

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['valid']);
        self::assertSame('Tenant ID is required', $data['message']);
    }

    // ---------------------------------------------------------------
    // Invalid JSON body
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenBodyIsInvalidJson(): void
    {
        $request = $this->buildRequest('not-json', $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['valid']);
        self::assertSame('Invalid JSON', $data['message']);
    }

    // ---------------------------------------------------------------
    // Missing coupon code
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenCouponCodeMissing(): void
    {
        $body = json_encode(['cartTotal' => 5000]);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['valid']);
        self::assertStringContainsString('required', $data['message']);
    }

    // ---------------------------------------------------------------
    // Missing cart total
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns400WhenCartTotalMissing(): void
    {
        $body = json_encode(['couponCode' => 'SAVE10']);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(400, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // Coupon not applied (discount = 0)
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns200WithValidFalseWhenCouponNotApplied(): void
    {
        $zeroMoney = Money::fromScalars(0, 'USD');
        $this->promotionService
            ->method('applyPromotions')
            ->willReturn([
                'totalDiscount' => $zeroMoney,
                'finalPrice' => Money::fromScalars(5000, 'USD'),
                'appliedPromotions' => [],
            ]);

        $body = json_encode(['couponCode' => 'INVALID', 'cartTotal' => 5000]);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['valid']);
        self::assertStringContainsString('invalid', $data['message']);
    }

    // ---------------------------------------------------------------
    // Coupon not applied (discount = null)
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns200WithValidFalseWhenDiscountIsNull(): void
    {
        $this->promotionService
            ->method('applyPromotions')
            ->willReturn([
                'totalDiscount' => null,
                'finalPrice' => Money::fromScalars(5000, 'USD'),
                'appliedPromotions' => [],
            ]);

        $body = json_encode(['couponCode' => 'NONE', 'cartTotal' => 5000]);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['valid']);
    }

    // ---------------------------------------------------------------
    // Happy path – coupon applied (fixed discount)
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns200WithValidTrueAndDiscountDetailsForFixedCoupon(): void
    {
        $discount = Money::fromScalars(500, 'USD');   // $5.00
        $finalPrice = Money::fromScalars(4500, 'USD');  // $45.00

        $this->promotionService
            ->method('applyPromotions')
            ->willReturn([
                'totalDiscount' => $discount,
                'finalPrice' => $finalPrice,
                'appliedPromotions' => [
                    ['promotionId' => 'p1', 'name' => 'Discount', 'type' => 'fixed', 'discountAmount' => 500],
                ],
            ]);

        $body = json_encode(['couponCode' => 'save5', 'cartTotal' => 5000, 'currency' => 'USD']);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['valid']);
        self::assertSame('SAVE5', $data['couponCode']);   // uppercased
        self::assertSame(500, $data['discountAmount']);
        self::assertSame(4500, $data['finalTotal']);
        self::assertSame('USD', $data['currency']);
    }

    // ---------------------------------------------------------------
    // Coupon applied – percentage discount detected
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itSetsDiscountTypeToPercentageForRoundPercentages(): void
    {
        // 10% discount: 1000 off 10000 = 10%
        $discount = Money::fromScalars(1000, 'USD');
        $finalPrice = Money::fromScalars(9000, 'USD');

        $this->promotionService
            ->method('applyPromotions')
            ->willReturn([
                'totalDiscount' => $discount,
                'finalPrice' => $finalPrice,
                'appliedPromotions' => [
                    ['promotionId' => 'p2', 'name' => '10% Off', 'type' => 'percentage'],
                ],
            ]);

        $body = json_encode(['couponCode' => 'TENOFF', 'cartTotal' => 10000]);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['valid']);
        self::assertSame('percentage', $data['discountType']);
        self::assertEquals(10, $data['discountPercentage']);
    }

    // ---------------------------------------------------------------
    // Exception → 500
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturns500WhenExceptionThrown(): void
    {
        $this->promotionService
            ->method('applyPromotions')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->logger->expects(self::once())->method('error');

        $body = json_encode(['couponCode' => 'CODE', 'cartTotal' => 1000]);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        self::assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['valid']);
    }

    // ---------------------------------------------------------------
    // Default currency is USD
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itUsesUsdAsDefaultCurrency(): void
    {
        $discount = Money::fromScalars(100, 'USD');
        $finalPrice = Money::fromScalars(900, 'USD');

        $this->promotionService
            ->method('applyPromotions')
            ->willReturn([
                'totalDiscount' => $discount,
                'finalPrice' => $finalPrice,
                'appliedPromotions' => [],
            ]);

        // No 'currency' key in body
        $body = json_encode(['couponCode' => 'TEST', 'cartTotal' => 1000]);
        $request = $this->buildRequest($body, $this->tenantId);

        $controller = $this->buildController();
        $response = $controller($request);

        $data = json_decode($response->getContent(), true);
        self::assertSame('USD', $data['currency']);
    }
}
