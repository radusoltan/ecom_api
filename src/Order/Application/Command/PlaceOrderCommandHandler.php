<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Order\Application\Service\CheckoutValidationService;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Pricing\Application\Service\PromotionApplicationService;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\Service\TaxCalculatorInterface;
use App\Tax\Domain\Service\VatValidationServiceInterface;
use App\Tax\Domain\ValueObject\TaxCategory;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use App\Tax\Domain\ValueObject\VatNumber;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PlaceOrderCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
        private PromotionApplicationService $promotionService,
        private TaxCalculationService $taxCalculationService,
        private TaxCalculatorInterface $taxCalculator,
        private VatValidationServiceInterface $vatValidationService,
        private CheckoutValidationService $checkoutValidationService,
        private PerformanceProfiler $profiler,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PlaceOrderCommand $command): Order
    {
        $this->profiler->start('order.place');

        try {
            $tenantId = TenantId::fromString($command->tenantId);

            // Create order lines, fetching prices from Catalog if not provided
            $lines = array_map(
                function (array $lineData) use ($tenantId) {
                    $productId = ProductId::fromString($lineData['productId']);

                    // If price is not provided, fetch from Catalog
                    if (!isset($lineData['unitPriceAmount']) || !isset($lineData['unitPriceCurrency'])) {
                        $product = $this->productRepository->findById($productId, $tenantId);

                        if (null === $product) {
                            throw new \RuntimeException(sprintf('Product with ID %s not found', $productId->toString()));
                        }

                        $unitPrice = $product->price();
                    } else {
                        $unitPrice = Money::fromScalars($lineData['unitPriceAmount'], $lineData['unitPriceCurrency']);
                    }

                    return OrderLine::create(
                        $productId,
                        $lineData['productName'],
                        $lineData['quantity'],
                        $unitPrice
                    );
                },
                $command->lines
            );

            // Validate price freshness and stock availability before proceeding
            $this->checkoutValidationService->validate($command->lines, $tenantId);

            $shippingAddress = Address::create(
                $command->shippingAddress['street'],
                $command->shippingAddress['city'],
                $command->shippingAddress['state'],
                $command->shippingAddress['postalCode'],
                $command->shippingAddress['country']
            );

            $billingAddress = Address::create(
                $command->billingAddress['street'],
                $command->billingAddress['city'],
                $command->billingAddress['state'],
                $command->billingAddress['postalCode'],
                $command->billingAddress['country']
            );

            // Calculate subtotal
            $subtotal = Money::fromScalars(0, 'USD');
            foreach ($lines as $line) {
                $subtotal = $subtotal->add($line->total());
            }

            // Apply promotions if coupon code provided or context available
            $appliedPromotions = [];
            $discountAmount = null;
            $couponCode = $command->couponCode;

            if (null !== $couponCode || !empty($command->promotionContext)) {
                $promotionResult = $this->promotionService->applyPromotions(
                    $tenantId,
                    $subtotal,
                    $couponCode,
                    $command->promotionContext
                );

                $appliedPromotions = $promotionResult['appliedPromotions'];
                $discountAmount = $promotionResult['totalDiscount'];
                $couponCode = $promotionResult['couponCode'];
            }

            // Calculate taxable amount (subtotal - discount)
            $taxableAmount = $subtotal;
            if (null !== $discountAmount) {
                $taxableAmount = $taxableAmount->subtract($discountAmount);
            }

            // Calculate tax based on shipping address
            $taxAmount = null;
            $taxJurisdiction = null;
            $taxRuleId = null;
            $taxRate = 0.0;
            $isReverseCharge = false;
            $validatedVatNumber = null;

            // Create jurisdiction from shipping address (destination-based taxation)
            $jurisdiction = null !== $shippingAddress->state && '' !== $shippingAddress->state
                ? TaxJurisdiction::fromCountryAndRegion($shippingAddress->country, $shippingAddress->state)
                : TaxJurisdiction::fromCountry($shippingAddress->country);

            $b2bTaxHandled = false;

            // B2B VAT reverse charge handling
            if (null !== $command->vatNumber && '' !== $command->vatNumber) {
                try {
                    $vatNumber = VatNumber::fromString($command->vatNumber);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException(sprintf('Invalid VAT number format: %s', $command->vatNumber), 0, $e);
                }

                if ($this->vatValidationService->isAvailable()) {
                    $vatValidation = $this->vatValidationService->validate($vatNumber);

                    if ($vatValidation->isValid()) {
                        // Determine seller jurisdiction
                        $sellerJurisdiction = null !== $command->sellerCountryCode && '' !== $command->sellerCountryCode
                            ? TaxJurisdiction::fromCountry($command->sellerCountryCode)
                            : $jurisdiction;

                        // Calculate tax with B2B flag (may result in reverse charge for cross-border EU)
                        $taxResult = $this->taxCalculator->calculate(
                            amountInCents: $taxableAmount->getAmount(),
                            sellerJurisdiction: $sellerJurisdiction,
                            buyerJurisdiction: $jurisdiction,
                            category: TaxCategory::standard(),
                            isB2B: true,
                            buyerVatNumber: $vatNumber->getFull(),
                        );

                        $isReverseCharge = $taxResult->isReverseCharge();
                        $validatedVatNumber = $vatNumber->getFull();
                        $taxJurisdiction = $taxResult->taxJurisdiction->toString();
                        $taxRate = $taxResult->percentage();

                        if ($taxResult->taxAmountInCents > 0) {
                            $taxAmount = Money::fromScalars($taxResult->taxAmountInCents, $taxableAmount->getCurrency()->getCurrencyCode());
                        }

                        $b2bTaxHandled = true;
                    } else {
                        throw new \InvalidArgumentException(sprintf(
                            'VAT number validation failed for %s: %s',
                            $command->vatNumber,
                            $vatValidation->errorMessage ?? 'VIES validation failed'
                        ));
                    }
                } else {
                    // VIES service unavailable — graceful degradation, proceed with standard tax
                    $this->logger->warning('VIES validation service unavailable, applying standard tax calculation', [
                        'vatNumber' => $command->vatNumber,
                        'orderId' => $command->orderId,
                    ]);
                    $validatedVatNumber = $vatNumber->getFull();
                }
            }

            // Standard B2C tax calculation (if not handled by B2B flow)
            if (!$b2bTaxHandled) {
                $taxCalculation = $this->taxCalculationService->calculateTax(
                    amountInCents: $taxableAmount->getAmount(),
                    jurisdiction: $jurisdiction,
                    tenantId: $tenantId
                );

                if ($taxCalculation['taxAmount'] > 0) {
                    $taxAmount = Money::fromScalars($taxCalculation['taxAmount'], $taxableAmount->getCurrency()->getCurrencyCode());
                    $taxJurisdiction = $taxCalculation['jurisdiction'];
                    $taxRuleId = $taxCalculation['taxRuleId'];
                    $taxRate = $taxCalculation['taxRate'];
                }
            }

            $order = Order::place(
                OrderId::fromString($command->orderId),
                $tenantId,
                $command->customerEmail,
                $lines,
                $shippingAddress,
                $billingAddress,
                $appliedPromotions,
                $discountAmount,
                $couponCode,
                $taxAmount,
                $taxJurisdiction,
                $taxRuleId,
                $taxRate,
                $isReverseCharge,
                $validatedVatNumber,
            );

            $this->orderRepository->save($order);

            $metrics = $this->profiler->stop('order.place');

            // Log if slow (> 200ms)
            if ($metrics['duration_ms'] > 200) {
                $this->logger->warning('Slow order placement detected', [
                    'order_id' => $command->orderId,
                    'metrics' => $metrics,
                ]);
            }

            return $order;
        } catch (\Throwable $e) {
            $this->profiler->stop('order.place');

            throw $e;
        }
    }
}
