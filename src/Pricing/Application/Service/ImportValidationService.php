<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\DTO\ImportRow;

/**
 * Validates import data before processing.
 *
 * Centralized validation logic for import operations
 */
final class ImportValidationService
{
    /**
     * Validate PriceList import row.
     *
     * @return array<string> Array of validation error messages (empty if valid)
     */
    public function validatePriceListRow(ImportRow $row): array
    {
        $errors = [];

        // Validate required fields
        if (!$row->getString('name')) {
            $errors[] = 'Name is required';
        }

        // Validate priority
        $priority = $row->getInt('priority');
        if (null === $priority) {
            $errors[] = 'Priority is required';
        } elseif ($priority < 0 || $priority > 1000) {
            $errors[] = 'Priority must be between 0 and 1000';
        }

        // Validate dates
        $validFrom = $row->getString('valid_from');
        $validTo = $row->getString('valid_to');

        if ($validFrom && !$this->isValidDate($validFrom)) {
            $errors[] = 'valid_from must be a valid date (Y-m-d format)';
        }

        if ($validTo && !$this->isValidDate($validTo)) {
            $errors[] = 'valid_to must be a valid date (Y-m-d format)';
        }

        if ($validFrom && $validTo) {
            $fromDate = new \DateTimeImmutable($validFrom);
            $toDate = new \DateTimeImmutable($validTo);
            if ($fromDate >= $toDate) {
                $errors[] = 'valid_from must be before valid_to';
            }
        }

        // Validate scope if provided
        $scope = $row->getString('scope');
        if ($scope && !in_array($scope, ['product', 'category', 'all'], true)) {
            $errors[] = 'scope must be one of: product, category, all';
        }

        // Validate discount_type if provided
        $discountType = $row->getString('discount_type');
        if ($discountType && !in_array($discountType, ['percentage', 'fixed'], true)) {
            $errors[] = 'discount_type must be one of: percentage, fixed';
        }

        // Validate discount_value if provided
        $discountValue = $row->getFloat('discount_value');
        if (null !== $discountValue && $discountValue < 0) {
            $errors[] = 'discount_value must be non-negative';
        }

        if ('percentage' === $discountType && null !== $discountValue && $discountValue > 100) {
            $errors[] = 'discount_value cannot exceed 100 for percentage discounts';
        }

        return $errors;
    }

    /**
     * Validate Promotion import row.
     *
     * @return array<string> Array of validation error messages (empty if valid)
     */
    public function validatePromotionRow(ImportRow $row): array
    {
        $errors = [];

        // Validate required fields
        $name = $row->getString('name');
        if (!$name) {
            $errors[] = 'Name is required';
        } else {
            $nameLength = mb_strlen($name);
            if ($nameLength < 3 || $nameLength > 100) {
                $errors[] = 'Name must be between 3 and 100 characters';
            }
        }

        // Validate type
        $type = $row->getString('type');
        if (!$type) {
            $errors[] = 'Type is required';
        } elseif (!in_array($type, ['automatic', 'coupon', 'flash_sale'], true)) {
            $errors[] = 'Type must be one of: automatic, coupon, flash_sale';
        }

        // Validate coupon code for coupon type
        if ($type === 'coupon' && !$row->getString('coupon_code')) {
            $errors[] = 'Coupon code is required for coupon type promotions';
        }

        // Validate discount_type
        $discountType = $row->getString('discount_type');
        if (!$discountType) {
            $errors[] = 'Discount type is required';
        } elseif (!in_array($discountType, ['percentage', 'fixed'], true)) {
            $errors[] = 'Discount type must be one of: percentage, fixed';
        }

        // Validate discount_value
        $discountValue = $row->getFloat('discount_value');
        if (null === $discountValue) {
            $errors[] = 'Discount value is required';
        } elseif ($discountValue < 0) {
            $errors[] = 'Discount value must be non-negative';
        }

        if ('percentage' === $discountType && null !== $discountValue && $discountValue > 100) {
            $errors[] = 'Discount value cannot exceed 100 for percentage discounts';
        }

        // Validate priority
        $priority = $row->getInt('priority');
        if (null !== $priority && ($priority < 1 || $priority > 1000)) {
            $errors[] = 'Priority must be between 1 and 1000';
        }

        // Validate dates
        $validFrom = $row->getString('valid_from');
        $validTo = $row->getString('valid_to');

        if ($validFrom && !$this->isValidDate($validFrom)) {
            $errors[] = 'valid_from must be a valid date (Y-m-d format)';
        }

        if ($validTo && !$this->isValidDate($validTo)) {
            $errors[] = 'valid_to must be a valid date (Y-m-d format)';
        }

        if ($validFrom && $validTo) {
            $fromDate = new \DateTimeImmutable($validFrom);
            $toDate = new \DateTimeImmutable($validTo);
            if ($fromDate >= $toDate) {
                $errors[] = 'valid_from must be before valid_to';
            }
        }

        return $errors;
    }

    private function isValidDate(string $date): bool
    {
        try {
            new \DateTimeImmutable($date);
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
