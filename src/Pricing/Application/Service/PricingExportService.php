<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\DTO\ExportFilter;
use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Application\DTO\PromotionDTO;

/**
 * Service for exporting pricing data to CSV/JSON files.
 *
 * Generates export files in the requested format
 */
final class PricingExportService
{
    /**
     * Export PriceLists to CSV format.
     *
     * @param array<PriceListDTO> $priceLists
     */
    public function exportPriceListsToCsv(array $priceLists): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for export');
        }

        try {
            // Write CSV header
            fputcsv($handle, [
                'name',
                'priority',
                'valid_from',
                'valid_to',
                'is_active',
                'scope',
                'scope_value',
                'discount_type',
                'discount_value',
                'condition_type',
                'condition_value',
            ]);

            // Write data rows
            foreach ($priceLists as $priceList) {
                // If no rules, write basic info
                if (empty($priceList->rules)) {
                    fputcsv($handle, [
                        $priceList->name,
                        $priceList->priority,
                        $priceList->validFrom ?? '',
                        $priceList->validTo ?? '',
                        $priceList->isActive ? 'true' : 'false',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                    ]);
                } else {
                    // Write one row per rule
                    foreach ($priceList->rules as $rule) {
                        fputcsv($handle, [
                            $priceList->name,
                            $priceList->priority,
                            $priceList->validFrom ?? '',
                            $priceList->validTo ?? '',
                            $priceList->isActive ? 'true' : 'false',
                            $rule['scope'] ?? '',
                            $rule['scope_value'] ?? '',
                            $rule['discount']['type'] ?? '',
                            $rule['discount']['value'] ?? '',
                            $rule['condition']['type'] ?? '',
                            $rule['condition']['value'] ?? '',
                        ]);
                    }
                }
            }

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read CSV content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Export PriceLists to JSON format.
     *
     * @param array<PriceListDTO> $priceLists
     */
    public function exportPriceListsToJson(array $priceLists): string
    {
        $data = array_map(fn (PriceListDTO $dto) => $dto->toArray(), $priceLists);

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Export Promotions to CSV format.
     *
     * @param array<PromotionDTO> $promotions
     */
    public function exportPromotionsToCsv(array $promotions): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for export');
        }

        try {
            // Write CSV header
            fputcsv($handle, [
                'name',
                'type',
                'discount_type',
                'discount_value',
                'priority',
                'is_active',
                'coupon_code',
                'valid_from',
                'valid_to',
                'conditions',
            ]);

            // Write data rows
            foreach ($promotions as $promotion) {
                fputcsv($handle, [
                    $promotion->name,
                    $promotion->type,
                    $promotion->discountType,
                    $promotion->discountValue,
                    $promotion->priority,
                    $promotion->isActive ? 'true' : 'false',
                    $promotion->couponCode ?? '',
                    $promotion->validFrom ?? '',
                    $promotion->validTo ?? '',
                    json_encode($promotion->conditions),
                ]);
            }

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read CSV content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Export Promotions to JSON format.
     *
     * @param array<PromotionDTO> $promotions
     */
    public function exportPromotionsToJson(array $promotions): string
    {
        $data = array_map(function (PromotionDTO $dto) {
            return [
                'id' => $dto->id,
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'type' => $dto->type,
                'discount_type' => $dto->discountType,
                'discount_value' => $dto->discountValue,
                'priority' => $dto->priority,
                'is_active' => $dto->isActive,
                'coupon_code' => $dto->couponCode,
                'valid_from' => $dto->validFrom,
                'valid_to' => $dto->validTo,
                'conditions' => $dto->conditions,
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ];
        }, $promotions);

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Get CSV template for PriceList imports.
     */
    public function getPriceListCsvTemplate(): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for template');
        }

        try {
            // Write header
            fputcsv($handle, [
                'name',
                'priority',
                'valid_from',
                'valid_to',
                'is_active',
                'scope',
                'scope_value',
                'discount_type',
                'discount_value',
                'condition_type',
                'condition_value',
            ]);

            // Write example row
            fputcsv($handle, [
                'Summer Sale',
                '100',
                '2024-06-01',
                '2024-08-31',
                'true',
                'product',
                'PROD-001',
                'percentage',
                '20',
                'min_quantity',
                '5',
            ]);

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read template content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get CSV template for Promotion imports.
     */
    public function getPromotionCsvTemplate(): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for template');
        }

        try {
            // Write header
            fputcsv($handle, [
                'name',
                'type',
                'discount_type',
                'discount_value',
                'priority',
                'is_active',
                'coupon_code',
                'valid_from',
                'valid_to',
                'conditions',
            ]);

            // Write example rows
            fputcsv($handle, [
                'Summer Discount',
                'automatic',
                'percentage',
                '15',
                '100',
                'true',
                '',
                '2024-06-01',
                '2024-08-31',
                '{"min_cart_value": 50}',
            ]);

            fputcsv($handle, [
                'Welcome Coupon',
                'coupon',
                'fixed',
                '10',
                '200',
                'true',
                'WELCOME10',
                '2024-01-01',
                '2024-12-31',
                '{"first_order_only": true}',
            ]);

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read template content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }
}
