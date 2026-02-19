<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Application\Command\PlaceOrderCommandHandler;
use App\Order\Application\Service\CheckoutValidationService;
use App\Order\Domain\Exception\CheckoutValidationException;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Pricing\Application\Service\PromotionApplicationService;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Service\TaxCalculationResult;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\Service\TaxCalculatorInterface;
use App\Tax\Domain\Service\VatValidationResult;
use App\Tax\Domain\Service\VatValidationServiceInterface;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\TaxRate;
use App\Tax\Domain\ValueObject\VatNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PlaceOrderCommandHandler::class)]
final class PlaceOrderCommandHandlerTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;
    private ProductRepositoryInterface $productRepository;
    private PromotionApplicationService $promotionService;
    private TaxCalculationService $taxCalculationService;
    private TaxCalculatorInterface $taxCalculator;
    private VatValidationServiceInterface $vatValidationService;
    private CheckoutValidationService $checkoutValidationService;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private PlaceOrderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->promotionService = $this->createMock(PromotionApplicationService::class);
        $this->taxCalculationService = $this->createMock(TaxCalculationService::class);
        $this->taxCalculator = $this->createMock(TaxCalculatorInterface::class);
        $this->vatValidationService = $this->createMock(VatValidationServiceInterface::class);
        $this->checkoutValidationService = $this->createMock(CheckoutValidationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // PerformanceProfiler is final — use real instance with mock logger
        $this->profiler = new PerformanceProfiler($this->createMock(LoggerInterface::class));

        $this->handler = new PlaceOrderCommandHandler(
            orderRepository: $this->orderRepository,
            productRepository: $this->productRepository,
            promotionService: $this->promotionService,
            taxCalculationService: $this->taxCalculationService,
            taxCalculator: $this->taxCalculator,
            vatValidationService: $this->vatValidationService,
            checkoutValidationService: $this->checkoutValidationService,
            profiler: $this->profiler,
            logger: $this->logger,
        );
    }

    #[Test]
    public function itPlacesB2COrderWithStandardTax(): void
    {
        $command = $this->createCommand();

        $this->taxCalculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->willReturn([
                'taxAmount' => 190,
                'taxRate' => 19.0,
                'jurisdiction' => 'DE',
                'taxRuleId' => 'rule-1',
                'taxRuleName' => 'DE Standard',
            ]);

        $this->vatValidationService
            ->expects($this->never())
            ->method('isAvailable');

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Order $order): bool {
                $this->assertFalse($order->isReverseCharge());
                $this->assertNull($order->vatNumber());
                $this->assertNotNull($order->taxAmount());
                $this->assertSame(190, $order->taxAmount()->getAmount());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itPlacesB2BOrderWithReverseChargeForValidCrossBorderEuVat(): void
    {
        $command = $this->createCommand(
            vatNumber: 'FR12345678901',
            sellerCountryCode: 'DE',
            shippingCountry: 'FR',
        );

        $this->vatValidationService
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $this->vatValidationService
            ->expects($this->once())
            ->method('validate')
            ->willReturn(VatValidationResult::valid(
                VatNumber::fromString('FR12345678901'),
                'Acme France SAS',
                '123 Rue de Paris, 75001 Paris',
            ));

        // Composite tax calculator returns reverse charge
        $buyerJurisdiction = TaxJurisdiction::fromCountry('FR');
        $this->taxCalculator
            ->expects($this->once())
            ->method('calculate')
            ->with(
                $this->isType('int'),
                $this->callback(fn (TaxJurisdiction $j) => 'DE' === $j->getCountryCode()),
                $this->callback(fn (TaxJurisdiction $j) => 'FR' === $j->getCountryCode()),
                $this->isInstanceOf(TaxCategory::class),
                true,
                'FR12345678901',
            )
            ->willReturn(TaxCalculationResult::reverseCharge(
                amountInCents: 1000,
                theoreticalRate: TaxRate::fromPercentage(20.0),
                jurisdiction: $buyerJurisdiction,
                category: TaxCategory::standard(),
            ));

        // Standard tax calculation should NOT be called
        $this->taxCalculationService
            ->expects($this->never())
            ->method('calculateTax');

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Order $order): bool {
                $this->assertTrue($order->isReverseCharge());
                $this->assertSame('FR12345678901', $order->vatNumber());
                $this->assertNull($order->taxAmount()); // 0 tax for reverse charge
                $this->assertSame(0.0, $order->taxRate());
                $this->assertSame('FR', $order->taxJurisdiction());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsExceptionForInvalidVatNumber(): void
    {
        $command = $this->createCommand(
            vatNumber: 'FR12345678901',
            sellerCountryCode: 'DE',
            shippingCountry: 'FR',
        );

        $this->vatValidationService
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $this->vatValidationService
            ->expects($this->once())
            ->method('validate')
            ->willReturn(VatValidationResult::invalid(
                VatNumber::fromString('FR12345678901'),
                'VAT number not found in VIES database',
            ));

        $this->orderRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('VAT number validation failed for FR12345678901');

        ($this->handler)($command);
    }

    #[Test]
    public function itFallsBackToStandardTaxWhenViesUnavailable(): void
    {
        $command = $this->createCommand(
            vatNumber: 'FR12345678901',
            sellerCountryCode: 'DE',
            shippingCountry: 'FR',
        );

        $this->vatValidationService
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        // Should NOT call validate when unavailable
        $this->vatValidationService
            ->expects($this->never())
            ->method('validate');

        // Should log warning
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('VIES validation service unavailable, applying standard tax calculation', $this->isType('array'));

        // Falls back to standard tax calculation
        $this->taxCalculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->willReturn([
                'taxAmount' => 200,
                'taxRate' => 20.0,
                'jurisdiction' => 'FR',
                'taxRuleId' => 'rule-fr',
                'taxRuleName' => 'FR Standard',
            ]);

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Order $order): bool {
                $this->assertFalse($order->isReverseCharge());
                // VAT number is still stored even without VIES validation
                $this->assertSame('FR12345678901', $order->vatNumber());
                $this->assertNotNull($order->taxAmount());
                $this->assertSame(200, $order->taxAmount()->getAmount());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsExceptionForMalformedVatNumber(): void
    {
        $command = $this->createCommand(
            vatNumber: 'XX',
            sellerCountryCode: 'DE',
            shippingCountry: 'FR',
        );

        $this->orderRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid VAT number format: XX');

        ($this->handler)($command);
    }

    #[Test]
    public function itPlacesOrderWithoutVatNumberUsingStandardTax(): void
    {
        $command = $this->createCommand(vatNumber: null);

        $this->taxCalculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->willReturn([
                'taxAmount' => 0,
                'taxRate' => 0.0,
                'jurisdiction' => 'US',
                'taxRuleId' => null,
                'taxRuleName' => null,
            ]);

        $this->vatValidationService
            ->expects($this->never())
            ->method('isAvailable');

        $this->taxCalculator
            ->expects($this->never())
            ->method('calculate');

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Order $order): bool {
                $this->assertFalse($order->isReverseCharge());
                $this->assertNull($order->vatNumber());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itPlacesB2BOrderWithTaxWhenSameCountry(): void
    {
        // Same-country B2B: VAT is valid but no reverse charge (seller DE, buyer DE)
        $command = $this->createCommand(
            vatNumber: 'DE123456789',
            sellerCountryCode: 'DE',
            shippingCountry: 'DE',
        );

        $this->vatValidationService
            ->expects($this->once())
            ->method('isAvailable')
            ->willReturn(true);

        $this->vatValidationService
            ->expects($this->once())
            ->method('validate')
            ->willReturn(VatValidationResult::valid(
                VatNumber::fromString('DE123456789'),
                'Test GmbH',
                'Berlin, Germany',
            ));

        // Same-country B2B: normal VAT applies (not reverse charge)
        $jurisdiction = TaxJurisdiction::fromCountry('DE');
        $this->taxCalculator
            ->expects($this->once())
            ->method('calculate')
            ->willReturn(TaxCalculationResult::create(
                netAmountInCents: 1000,
                taxAmountInCents: 190,
                appliedRate: TaxRate::fromPercentage(19.0),
                taxJurisdiction: $jurisdiction,
                category: TaxCategory::standard(),
                taxType: 'vat',
            ));

        $this->taxCalculationService
            ->expects($this->never())
            ->method('calculateTax');

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Order $order): bool {
                $this->assertFalse($order->isReverseCharge());
                $this->assertSame('DE123456789', $order->vatNumber());
                $this->assertNotNull($order->taxAmount());
                $this->assertSame(190, $order->taxAmount()->getAmount());
                $this->assertSame(19.0, $order->taxRate());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itHandlesEmptyVatNumberAsB2C(): void
    {
        $command = $this->createCommand(vatNumber: '');

        $this->taxCalculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->willReturn([
                'taxAmount' => 100,
                'taxRate' => 10.0,
                'jurisdiction' => 'US-CA',
                'taxRuleId' => 'rule-us',
                'taxRuleName' => 'US California',
            ]);

        $this->vatValidationService
            ->expects($this->never())
            ->method('isAvailable');

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Order $order): bool {
                $this->assertFalse($order->isReverseCharge());
                $this->assertNull($order->vatNumber());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itCallsCheckoutValidationBeforeOrderCreation(): void
    {
        $command = $this->createCommand();

        $this->checkoutValidationService
            ->expects($this->once())
            ->method('validate')
            ->with(
                $command->lines,
                $this->callback(fn (TenantId $id) => '00000000-0000-4000-8000-000000000001' === $id->toString()),
            );

        $this->taxCalculationService
            ->method('calculateTax')
            ->willReturn([
                'taxAmount' => 190,
                'taxRate' => 19.0,
                'jurisdiction' => 'US',
                'taxRuleId' => 'rule-1',
                'taxRuleName' => 'US Standard',
            ]);

        $this->orderRepository
            ->expects($this->once())
            ->method('save');

        ($this->handler)($command);
    }

    #[Test]
    public function itPropagatesCheckoutValidationException(): void
    {
        $command = $this->createCommand();

        $this->checkoutValidationService
            ->expects($this->once())
            ->method('validate')
            ->willThrowException(CheckoutValidationException::withIssues(
                [['productId' => '550e8400-e29b-41d4-a716-446655440001', 'productName' => 'Test Product', 'oldPrice' => 1000, 'newPrice' => 1500, 'currency' => 'USD']],
                [],
            ));

        $this->orderRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(CheckoutValidationException::class);

        ($this->handler)($command);
    }

    private function createCommand(
        ?string $vatNumber = null,
        ?string $sellerCountryCode = null,
        string $shippingCountry = 'US',
    ): PlaceOrderCommand {
        return new PlaceOrderCommand(
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            tenantId: '00000000-0000-4000-8000-000000000001',
            customerEmail: 'test@example.com',
            lines: [
                [
                    'productId' => '550e8400-e29b-41d4-a716-446655440001',
                    'productName' => 'Test Product',
                    'quantity' => 1,
                    'unitPriceAmount' => 1000,
                    'unitPriceCurrency' => 'USD',
                ],
            ],
            shippingAddress: [
                'street' => '123 Test St',
                'city' => 'Test City',
                'state' => 'CA',
                'postalCode' => '12345',
                'country' => $shippingCountry,
            ],
            billingAddress: [
                'street' => '456 Bill St',
                'city' => 'Bill City',
                'state' => 'CA',
                'postalCode' => '67890',
                'country' => $shippingCountry,
            ],
            vatNumber: $vatNumber,
            sellerCountryCode: $sellerCountryCode,
        );
    }
}
