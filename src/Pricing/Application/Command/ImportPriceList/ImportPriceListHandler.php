<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ImportPriceList;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\DTO\ImportRow;
use App\Pricing\Application\Service\ImportValidationService;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\PricingRule;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for ImportPriceListCommand.
 *
 * Processes bulk import of PriceLists from CSV/JSON files
 */
final class ImportPriceListHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $repository,
        private readonly ImportValidationService $validationService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ImportPriceListCommand $command): ImportResult
    {
        $totalRows = count($command->rows);
        $successCount = 0;
        $errorCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $errors = [];

        $this->logger->info('Starting PriceList import', [
            'tenant_id' => $command->tenantId->toString(),
            'total_rows' => $totalRows,
        ]);

        // Group rows by PriceList name (multiple rows can belong to same PriceList)
        $groupedRows = $this->groupRowsByPriceList($command->rows);

        foreach ($groupedRows as $name => $rows) {
            try {
                $firstRow = $rows[0];

                // Validate first row (common fields)
                $validationErrors = $this->validationService->validatePriceListRow($firstRow);
                if (!empty($validationErrors)) {
                    $errorCount += count($rows);
                    $errors[$firstRow->rowNumber] = $validationErrors;
                    continue;
                }

                // Check if PriceList already exists
                $existingPriceLists = $this->repository->findByTenant($command->tenantId);
                $existingPriceList = $this->findByName($existingPriceLists, $name);

                if (null !== $existingPriceList) {
                    if (!$command->updateExisting) {
                        $errorCount += count($rows);
                        $errors[$firstRow->rowNumber] = ['PriceList already exists and updateExisting is false'];
                        continue;
                    }

                    // Update existing
                    $priceList = $this->updatePriceList($existingPriceList, $rows);
                    $updatedCount++;
                } else {
                    // Create new
                    $priceList = $this->createPriceList($command->tenantId, $rows);
                    $createdCount++;
                }

                $this->repository->save($priceList);
                $successCount += count($rows);

                $this->logger->info('Imported PriceList', [
                    'name' => $name,
                    'action' => null !== $existingPriceList ? 'updated' : 'created',
                ]);
            } catch (\Exception $e) {
                $errorCount += count($rows);
                $errors[$rows[0]->rowNumber] = [$e->getMessage()];

                $this->logger->error('Failed to import PriceList', [
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('PriceList import completed', [
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
     * @param array<array<string, mixed>> $rawRows
     * @return array<string, array<ImportRow>>
     */
    private function groupRowsByPriceList(array $rawRows): array
    {
        $grouped = [];

        foreach ($rawRows as $index => $rawRow) {
            $row = new ImportRow($index + 1, $rawRow);
            $name = $row->getString('name') ?? 'unnamed';
            $grouped[$name][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<PriceList> $priceLists
     */
    private function findByName(array $priceLists, string $name): ?PriceList
    {
        foreach ($priceLists as $priceList) {
            if ($priceList->name()->value() === $name) {
                return $priceList;
            }
        }

        return null;
    }

    /**
     * @param array<ImportRow> $rows
     */
    private function createPriceList(
        \App\Shared\Domain\ValueObject\TenantId $tenantId,
        array $rows
    ): PriceList {
        $firstRow = $rows[0];

        $validFrom = $firstRow->getString('valid_from') ? new \DateTimeImmutable($firstRow->getString('valid_from')) : null;
        $validTo = $firstRow->getString('valid_to') ? new \DateTimeImmutable($firstRow->getString('valid_to')) : null;

        $priceList = PriceList::create(
            id: PriceListId::generate(),
            tenantId: $tenantId,
            name: PriceListName::fromString($firstRow->getString('name') ?? ''),
            priority: $firstRow->getInt('priority') ?? 100,
            validFrom: $validFrom,
            validTo: $validTo
        );

        // Add rules from all rows
        foreach ($rows as $row) {
            if ($this->hasRuleData($row)) {
                $rule = $this->createPricingRule($row);
                $priceList->addRule($rule);
            }
        }

        // Activate if specified
        if ($firstRow->getBool('is_active', false)) {
            $priceList->activate();
        }

        return $priceList;
    }

    /**
     * @param array<ImportRow> $rows
     */
    private function updatePriceList(PriceList $priceList, array $rows): PriceList
    {
        $firstRow = $rows[0];

        $validFrom = $firstRow->getString('valid_from') ? new \DateTimeImmutable($firstRow->getString('valid_from')) : null;
        $validTo = $firstRow->getString('valid_to') ? new \DateTimeImmutable($firstRow->getString('valid_to')) : null;

        // Update basic fields
        $priceList->update(
            name: PriceListName::fromString($firstRow->getString('name') ?? ''),
            priority: $firstRow->getInt('priority') ?? 100,
            validFrom: $validFrom,
            validTo: $validTo
        );

        // Clear existing rules and add new ones
        $existingRulesCount = count($priceList->rules());
        for ($i = 0; $i < $existingRulesCount; $i++) {
            $priceList->removeRule(0); // Always remove first rule
        }

        foreach ($rows as $row) {
            if ($this->hasRuleData($row)) {
                $rule = $this->createPricingRule($row);
                $priceList->addRule($rule);
            }
        }

        // Update active status
        $shouldBeActive = $firstRow->getBool('is_active', false);
        if ($shouldBeActive && !$priceList->isActive()) {
            $priceList->activate();
        } elseif (!$shouldBeActive && $priceList->isActive()) {
            $priceList->deactivate();
        }

        return $priceList;
    }

    private function hasRuleData(ImportRow $row): bool
    {
        return null !== $row->getString('scope')
            || null !== $row->getString('discount_type')
            || null !== $row->getFloat('discount_value');
    }

    private function createPricingRule(ImportRow $row): PricingRule
    {
        $scope = $row->getString('scope') ?? 'all';
        $scopeValue = $row->getString('scope_value');
        $discountType = $row->getString('discount_type') ?? 'percentage';
        $discountValue = $row->getFloat('discount_value') ?? 0.0;
        $conditionType = $row->getString('condition_type');
        $conditionValue = $row->getString('condition_value');

        // Build discount using fromTypeAndValue factory method
        $discount = Discount::fromTypeAndValue($discountType, $discountValue);

        // Parse min_quantity from condition if present
        $minQuantity = 1;
        if ('min_quantity' === $conditionType && null !== $conditionValue) {
            $minQuantity = (int) $conditionValue;
        }

        // Build rule based on scope
        return match ($scope) {
            'product' => PricingRule::forProduct(
                ProductId::fromString($scopeValue ?? ''),
                $discount,
                $minQuantity
            ),
            'category' => PricingRule::forCategory(
                $scopeValue ?? '',
                $discount,
                $minQuantity
            ),
            default => PricingRule::forAll($discount, $minQuantity),
        };
    }
}
