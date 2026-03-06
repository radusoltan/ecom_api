<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command\ImportPromotions;

use App\Pricing\Application\Command\ImportPromotions\ImportPromotionsCommand;
use App\Pricing\Application\Command\ImportPromotions\ImportPromotionsHandler;
use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\Service\ImportValidationService;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ImportPromotionsHandlerTest extends TestCase
{
    private PromotionRepositoryInterface $repository;
    private ImportValidationService $validationService;
    private LoggerInterface $logger;
    private ImportPromotionsHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PromotionRepositoryInterface::class);
        $this->validationService = $this->createMock(ImportValidationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ImportPromotionsHandler(
            $this->repository,
            $this->validationService,
            $this->logger,
        );

        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testHandleCreatesCouponPromotionSuccessfully(): void
    {
        $rows = [
            [
                'name' => 'Coupon Promo',
                'type' => 'coupon',
                'discount_type' => 'percentage',
                'discount_value' => 10.0,
                'coupon_code' => 'SAVE10',
                'priority' => 100,
                'is_active' => false,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
            updateExisting: true,
        );

        $this->validationService
            ->expects($this->once())
            ->method('validatePromotionRow')
            ->willReturn([]);

        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->with($this->tenantId)
            ->willReturn([]);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Promotion $promotion) {
                return 'Coupon Promo' === $promotion->name()
                    && $promotion->type()->isCoupon();
            }));

        $result = ($this->handler)($command);

        $this->assertInstanceOf(ImportResult::class, $result);
        $this->assertSame(1, $result->totalRows);
        $this->assertSame(1, $result->successCount);
        $this->assertSame(0, $result->errorCount);
        $this->assertSame(1, $result->createdCount);
        $this->assertSame(0, $result->updatedCount);
        $this->assertEmpty($result->errors);
    }

    public function testHandleCreatesAndActivatesPromotionWhenIsActiveIsTrue(): void
    {
        $rows = [
            [
                'name' => 'Hot Deal',
                'type' => 'cart_rule',
                'discount_type' => 'percentage',
                'discount_value' => 25.0,
                'priority' => 200,
                'is_active' => true,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
        );

        $this->validationService->method('validatePromotionRow')->willReturn([]);
        $this->repository->method('findAll')->willReturn([]);

        $savedPromotion = null;
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Promotion $promotion) use (&$savedPromotion) {
                $savedPromotion = $promotion;
            });

        $result = ($this->handler)($command);

        $this->assertSame(1, $result->successCount);
        $this->assertSame(1, $result->createdCount);
        $this->assertNotNull($savedPromotion);
        $this->assertTrue($savedPromotion->isActive());
    }

    public function testHandleSkipsRowWithValidationErrors(): void
    {
        $rows = [
            [
                'name' => '',
                'type' => 'coupon',
                'discount_type' => 'percentage',
                'discount_value' => 10.0,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
        );

        $this->validationService
            ->expects($this->once())
            ->method('validatePromotionRow')
            ->willReturn(['Name is required']);

        $this->repository->expects($this->never())->method('findAll');
        $this->repository->expects($this->never())->method('save');

        $result = ($this->handler)($command);

        $this->assertSame(1, $result->totalRows);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(1, $result->errorCount);
        $this->assertSame(0, $result->createdCount);
        $this->assertArrayHasKey(1, $result->errors);
        $this->assertStringContainsString('Name is required', $result->errors[1]);
    }

    public function testHandleSkipsExistingPromotionWhenUpdateExistingIsFalse(): void
    {
        $rows = [
            [
                'name' => 'Existing Promo',
                'type' => 'coupon',
                'discount_type' => 'percentage',
                'discount_value' => 15.0,
                'coupon_code' => 'PROMO15',
                'priority' => 100,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
            updateExisting: false,
        );

        $existingPromotion = $this->buildCouponPromotion('Existing Promo');

        $this->validationService->method('validatePromotionRow')->willReturn([]);
        $this->repository->method('findAll')->willReturn([$existingPromotion]);
        $this->repository->expects($this->never())->method('save');

        $result = ($this->handler)($command);

        $this->assertSame(1, $result->totalRows);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(1, $result->errorCount);
        $this->assertSame(0, $result->updatedCount);
        $this->assertArrayHasKey(1, $result->errors);
        $this->assertStringContainsString('updateExisting is false', $result->errors[1]);
    }

    public function testHandleUpdatesExistingPromotionWhenUpdateExistingIsTrue(): void
    {
        $rows = [
            [
                'name' => 'Existing Promo',
                'type' => 'coupon',
                'discount_type' => 'percentage',
                'discount_value' => 20.0,
                'coupon_code' => 'PROMO20',
                'priority' => 150,
                'is_active' => false,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
            updateExisting: true,
        );

        $existingPromotion = $this->buildCouponPromotion('Existing Promo');

        $this->validationService->method('validatePromotionRow')->willReturn([]);
        $this->repository->method('findAll')->willReturn([$existingPromotion]);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Promotion $promotion) {
                return 'Existing Promo' === $promotion->name();
            }));

        $result = ($this->handler)($command);

        $this->assertSame(1, $result->totalRows);
        $this->assertSame(1, $result->successCount);
        $this->assertSame(0, $result->errorCount);
        $this->assertSame(0, $result->createdCount);
        $this->assertSame(1, $result->updatedCount);
    }

    public function testHandleProcessesMultipleRowsWithMixedResults(): void
    {
        $rows = [
            [
                'name' => 'Valid Coupon',
                'type' => 'coupon',
                'discount_type' => 'percentage',
                'discount_value' => 10.0,
                'coupon_code' => 'VALID10',
                'priority' => 100,
            ],
            [
                'name' => '',
                'type' => 'coupon',
                'discount_type' => 'percentage',
                'discount_value' => 10.0,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
        );

        $this->validationService
            ->method('validatePromotionRow')
            ->willReturnOnConsecutiveCalls([], ['Name is required']);

        $this->repository->method('findAll')->willReturn([]);
        $this->repository->expects($this->once())->method('save');

        $result = ($this->handler)($command);

        $this->assertSame(2, $result->totalRows);
        $this->assertSame(1, $result->successCount);
        $this->assertSame(1, $result->errorCount);
        $this->assertSame(1, $result->createdCount);
        $this->assertSame(0, $result->updatedCount);
    }

    public function testHandleWithEmptyRowsReturnsZeroCounts(): void
    {
        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: [],
        );

        $this->validationService->expects($this->never())->method('validatePromotionRow');
        $this->repository->expects($this->never())->method('save');

        $result = ($this->handler)($command);

        $this->assertSame(0, $result->totalRows);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(0, $result->errorCount);
        $this->assertSame(0, $result->createdCount);
        $this->assertSame(0, $result->updatedCount);
        $this->assertEmpty($result->errors);
    }

    public function testHandleCatchesExceptionDuringSaveAndRecordsError(): void
    {
        $rows = [
            [
                'name' => 'Bad Promo',
                'type' => 'cart_rule',
                'discount_type' => 'percentage',
                'discount_value' => 10.0,
                'priority' => 100,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
        );

        $this->validationService->method('validatePromotionRow')->willReturn([]);
        $this->repository->method('findAll')->willReturn([]);
        $this->repository
            ->method('save')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = ($this->handler)($command);

        $this->assertSame(1, $result->totalRows);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(1, $result->errorCount);
        $this->assertArrayHasKey(1, $result->errors);
        $this->assertStringContainsString('Database error', $result->errors[1]);
    }

    public function testHandleFlattensMultipleValidationErrorsToSemicolonSeparatedString(): void
    {
        $rows = [
            [
                'name' => '',
                'type' => 'invalid',
                'discount_type' => 'bad',
                'discount_value' => -1.0,
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
        );

        $this->validationService
            ->method('validatePromotionRow')
            ->willReturn(['Name is required', 'Invalid type', 'Invalid discount type']);

        $result = ($this->handler)($command);

        $this->assertSame(1, $result->errorCount);
        $this->assertArrayHasKey(1, $result->errors);
        $this->assertStringContainsString('Name is required', $result->errors[1]);
        $this->assertStringContainsString('Invalid type', $result->errors[1]);
        $this->assertStringContainsString('; ', $result->errors[1]);
    }

    public function testHandleResultIsNotSuccessfulWhenErrorsExist(): void
    {
        $rows = [
            [
                'name' => '',
            ],
        ];

        $command = new ImportPromotionsCommand(
            tenantId: $this->tenantId,
            rows: $rows,
        );

        $this->validationService->method('validatePromotionRow')->willReturn(['Name is required']);

        $result = ($this->handler)($command);

        $this->assertFalse($result->isSuccessful());
        $this->assertFalse($result->hasPartialSuccess());
    }

    /**
     * Build a coupon Promotion for use in test scenarios that need an existing promotion.
     */
    private function buildCouponPromotion(string $name): Promotion
    {
        return Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: PromotionType::coupon(),
            discount: Discount::fromTypeAndValue('percentage', 10.0),
            priority: 100,
            couponCode: CouponCode::fromString('CODE123'),
        );
    }
}
