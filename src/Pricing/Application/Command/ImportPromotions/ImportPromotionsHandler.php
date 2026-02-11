<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ImportPromotions;

use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\DTO\ImportRow;
use App\Pricing\Application\Service\ImportValidationService;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\Repository\PromotionRepositoryInterface;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use Psr\Log\LoggerInterface;

/**
 * Handler for ImportPromotionsCommand.
 *
 * Processes bulk import of Promotions from CSV/JSON files
 */
final class ImportPromotionsHandler
{
    public function __construct(
        private readonly PromotionRepositoryInterface $repository,
        private readonly ImportValidationService $validationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ImportPromotionsCommand $command): ImportResult
    {
        $totalRows = count($command->rows);
        $successCount = 0;
        $errorCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $errors = [];

        $this->logger->info('Starting Promotions import', [
            'tenant_id' => $command->tenantId->toString(),
            'total_rows' => $totalRows,
        ]);

        foreach ($command->rows as $index => $rawRow) {
            $row = new ImportRow($index + 1, $rawRow);

            try {
                // Validate row
                $validationErrors = $this->validationService->validatePromotionRow($row);
                if (!empty($validationErrors)) {
                    $errorCount++;
                    $errors[$row->rowNumber] = $validationErrors;
                    continue;
                }

                $name = $row->getString('name') ?? '';

                // Check if Promotion already exists by name
                $existingPromotions = $this->repository->findAll($command->tenantId);
                $existingPromotion = $this->findByName($existingPromotions, $name);

                if (null !== $existingPromotion) {
                    if (!$command->updateExisting) {
                        $errorCount++;
                        $errors[$row->rowNumber] = ['Promotion already exists and updateExisting is false'];
                        continue;
                    }

                    // Update existing
                    $this->updatePromotion($existingPromotion, $row);
                    $this->repository->save($existingPromotion);
                    $updatedCount++;

                    $this->logger->info('Updated Promotion', ['name' => $name]);
                } else {
                    // Create new
                    $promotion = $this->createPromotion($command->tenantId, $row);
                    $this->repository->save($promotion);
                    $createdCount++;

                    $this->logger->info('Created Promotion', ['name' => $name]);
                }

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[$row->rowNumber] = [$e->getMessage()];

                $this->logger->error('Failed to import Promotion', [
                    'row' => $row->rowNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Promotions import completed', [
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
        ]);

        // Flatten errors array for ImportResult
        $flatErrors = [];
        foreach ($errors as $rowNum => $rowErrors) {
            $flatErrors[$rowNum] = implode('; ', $rowErrors);
        }

        return new ImportResult(
            totalRows: $totalRows,
            successCount: $successCount,
            errorCount: $errorCount,
            errors: $flatErrors,
            createdCount: $createdCount,
            updatedCount: $updatedCount
        );
    }

    /**
     * @param array<Promotion> $promotions
     */
    private function findByName(array $promotions, string $name): ?Promotion
    {
        foreach ($promotions as $promotion) {
            if ($promotion->name() === $name) {
                return $promotion;
            }
        }

        return null;
    }

    private function createPromotion(
        \App\Shared\Domain\ValueObject\TenantId $tenantId,
        ImportRow $row
    ): Promotion {
        $type = PromotionType::fromString($row->getString('type') ?? 'automatic');
        $discountType = $row->getString('discount_type') ?? 'percentage';
        $discountValue = $row->getFloat('discount_value') ?? 0.0;
        $discount = Discount::fromTypeAndValue($discountType, $discountValue);

        $couponCode = null;
        $couponCodeString = $row->getString('coupon_code');
        if ($type->isCoupon() && $couponCodeString) {
            $couponCode = CouponCode::fromString($couponCodeString);
        }

        $validFrom = $row->getString('valid_from') ? new \DateTimeImmutable($row->getString('valid_from')) : null;
        $validTo = $row->getString('valid_to') ? new \DateTimeImmutable($row->getString('valid_to')) : null;

        $conditionsJson = $row->getString('conditions');
        $conditions = [];
        if ($conditionsJson) {
            $conditions = json_decode($conditionsJson, true, 512, JSON_THROW_ON_ERROR);
        }

        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $tenantId,
            name: $row->getString('name') ?? '',
            type: $type,
            discount: $discount,
            priority: $row->getInt('priority') ?? 100,
            couponCode: $couponCode,
            conditions: $conditions,
            targetSegments: [], // TODO: Support segments in import
            validFrom: $validFrom,
            validTo: $validTo
        );

        // Activate if specified
        if ($row->getBool('is_active', false)) {
            $promotion->activate();
        }

        return $promotion;
    }

    private function updatePromotion(Promotion $promotion, ImportRow $row): void
    {
        $discountType = $row->getString('discount_type') ?? 'percentage';
        $discountValue = $row->getFloat('discount_value') ?? 0.0;
        $discount = Discount::fromTypeAndValue($discountType, $discountValue);

        $validFrom = $row->getString('valid_from') ? new \DateTimeImmutable($row->getString('valid_from')) : null;
        $validTo = $row->getString('valid_to') ? new \DateTimeImmutable($row->getString('valid_to')) : null;

        $conditionsJson = $row->getString('conditions');
        $conditions = [];
        if ($conditionsJson) {
            $conditions = json_decode($conditionsJson, true, 512, JSON_THROW_ON_ERROR);
        }

        $promotion->update(
            name: $row->getString('name') ?? '',
            discount: $discount,
            priority: $row->getInt('priority') ?? 100,
            conditions: $conditions,
            validFrom: $validFrom,
            validTo: $validTo
        );

        // Update active status
        $shouldBeActive = $row->getBool('is_active', false);
        if ($shouldBeActive && !$promotion->isActive()) {
            $promotion->activate();
        } elseif (!$shouldBeActive && $promotion->isActive()) {
            $promotion->deactivate();
        }
    }
}
