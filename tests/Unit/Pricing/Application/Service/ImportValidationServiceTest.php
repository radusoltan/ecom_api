<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\DTO\ImportRow;
use App\Pricing\Application\Service\ImportValidationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportValidationService::class)]
final class ImportValidationServiceTest extends TestCase
{
    private ImportValidationService $service;

    protected function setUp(): void
    {
        $this->service = new ImportValidationService();
    }

    // -----------------------------------------------------------------------
    // validatePriceListRow — valid data
    // -----------------------------------------------------------------------

    #[Test]
    public function priceListRowWithMinimalValidDataReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Summer Sale',
            'priority' => '100',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithAllValidFieldsReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'VIP Prices',
            'priority' => '500',
            'valid_from' => '2025-01-01',
            'valid_to' => '2025-12-31',
            'scope' => 'product',
            'discount_type' => 'percentage',
            'discount_value' => '20',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithPriorityZeroReturnsNoErrors(): void
    {
        $row = new ImportRow(1, ['name' => 'Test', 'priority' => '0']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithPriority1000ReturnsNoErrors(): void
    {
        $row = new ImportRow(1, ['name' => 'Test', 'priority' => '1000']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithScopeCategoryReturnsNoErrors(): void
    {
        $row = new ImportRow(1, ['name' => 'Cat Sale', 'priority' => '10', 'scope' => 'category']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithScopeAllReturnsNoErrors(): void
    {
        $row = new ImportRow(1, ['name' => 'All Sale', 'priority' => '10', 'scope' => 'all']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithFixedDiscountTypeReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Fixed',
            'priority' => '10',
            'discount_type' => 'fixed',
            'discount_value' => '5.00',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function priceListRowWithPercentageAt100ReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Free',
            'priority' => '1',
            'discount_type' => 'percentage',
            'discount_value' => '100',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertSame([], $errors);
    }

    // -----------------------------------------------------------------------
    // validatePriceListRow — missing required fields
    // -----------------------------------------------------------------------

    #[Test]
    public function priceListRowMissingNameReturnsError(): void
    {
        $row = new ImportRow(1, ['priority' => '100']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('Name is required', $errors);
    }

    #[Test]
    public function priceListRowWithEmptyNameReturnsError(): void
    {
        $row = new ImportRow(1, ['name' => '', 'priority' => '100']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('Name is required', $errors);
    }

    #[Test]
    public function priceListRowMissingPriorityReturnsError(): void
    {
        $row = new ImportRow(1, ['name' => 'Test']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('Priority is required', $errors);
    }

    #[Test]
    public function priceListRowWithNullPriorityReturnsError(): void
    {
        $row = new ImportRow(1, ['name' => 'Test', 'priority' => null]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('Priority is required', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePriceListRow — priority out of range
    // -----------------------------------------------------------------------

    #[Test]
    public function priceListRowWithNegativePriorityReturnsError(): void
    {
        $row = new ImportRow(1, ['name' => 'Test', 'priority' => '-1']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('Priority must be between 0 and 1000', $errors);
    }

    #[Test]
    public function priceListRowWithPriorityAbove1000ReturnsError(): void
    {
        $row = new ImportRow(1, ['name' => 'Test', 'priority' => '1001']);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('Priority must be between 0 and 1000', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePriceListRow — date validation
    // -----------------------------------------------------------------------

    #[Test]
    public function priceListRowWithInvalidValidFromReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'valid_from' => 'not-a-date',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('valid_from must be a valid date (Y-m-d format)', $errors);
    }

    #[Test]
    public function priceListRowWithInvalidValidToReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'valid_to' => 'not-a-date',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('valid_to must be a valid date (Y-m-d format)', $errors);
    }

    #[Test]
    public function priceListRowWithValidFromAfterValidToReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'valid_from' => '2025-12-31',
            'valid_to' => '2025-01-01',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('valid_from must be before valid_to', $errors);
    }

    #[Test]
    public function priceListRowWithValidFromEqualToValidToReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'valid_from' => '2025-06-01',
            'valid_to' => '2025-06-01',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('valid_from must be before valid_to', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePriceListRow — scope validation
    // -----------------------------------------------------------------------

    #[Test]
    public function priceListRowWithInvalidScopeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'scope' => 'invalid_scope',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('scope must be one of: product, category, all', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePriceListRow — discount validation
    // -----------------------------------------------------------------------

    #[Test]
    public function priceListRowWithInvalidDiscountTypeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'discount_type' => 'invalid',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('discount_type must be one of: percentage, fixed', $errors);
    }

    #[Test]
    public function priceListRowWithNegativeDiscountValueReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'discount_value' => '-5',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('discount_value must be non-negative', $errors);
    }

    #[Test]
    public function priceListRowWithPercentageDiscountOver100ReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'discount_type' => 'percentage',
            'discount_value' => '101',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertContains('discount_value cannot exceed 100 for percentage discounts', $errors);
    }

    #[Test]
    public function priceListRowWithFixedDiscountOver100DoesNotReturnPercentageError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test',
            'priority' => '100',
            'discount_type' => 'fixed',
            'discount_value' => '200',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertNotContains('discount_value cannot exceed 100 for percentage discounts', $errors);
    }

    #[Test]
    public function priceListRowCanHaveMultipleErrors(): void
    {
        $row = new ImportRow(1, [
            // missing name, missing priority, invalid scope
            'scope' => 'bad_scope',
        ]);

        $errors = $this->service->validatePriceListRow($row);

        self::assertGreaterThanOrEqual(3, count($errors));
        self::assertContains('Name is required', $errors);
        self::assertContains('Priority is required', $errors);
        self::assertContains('scope must be one of: product, category, all', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — valid data
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowWithMinimalValidDataReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Summer Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function promotionRowWithCouponTypeAndCodeReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Welcome Coupon',
            'type' => 'coupon',
            'coupon_code' => 'WELCOME10',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function promotionRowWithFlashSaleTypeReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Flash Deal',
            'type' => 'flash_sale',
            'discount_type' => 'fixed',
            'discount_value' => '5',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function promotionRowWithValidPriorityRangeReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'priority' => '500',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertSame([], $errors);
    }

    #[Test]
    public function promotionRowWithValidDatesReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Dated Promo',
            'type' => 'automatic',
            'discount_type' => 'fixed',
            'discount_value' => '5',
            'valid_from' => '2025-01-01',
            'valid_to' => '2025-12-31',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertSame([], $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — name validation
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowMissingNameReturnsError(): void
    {
        $row = new ImportRow(1, [
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Name is required', $errors);
    }

    #[Test]
    public function promotionRowWithEmptyNameReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => '',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Name is required', $errors);
    }

    #[Test]
    public function promotionRowWithTooShortNameReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'AB',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Name must be between 3 and 100 characters', $errors);
    }

    #[Test]
    public function promotionRowWithTooLongNameReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => str_repeat('A', 101),
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Name must be between 3 and 100 characters', $errors);
    }

    #[Test]
    public function promotionRowWithNameExactly3CharsReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'ABC',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Name must be between 3 and 100 characters', $errors);
    }

    #[Test]
    public function promotionRowWithNameExactly100CharsReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => str_repeat('A', 100),
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Name must be between 3 and 100 characters', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — type validation
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowMissingTypeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Type is required', $errors);
    }

    #[Test]
    public function promotionRowWithInvalidTypeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'invalid_type',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Type must be one of: automatic, coupon, flash_sale', $errors);
    }

    #[Test]
    public function promotionRowWithCouponTypeMissingCouponCodeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Coupon Promo',
            'type' => 'coupon',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Coupon code is required for coupon type promotions', $errors);
    }

    #[Test]
    public function promotionRowWithAutomaticTypeNoCouponCodeReturnsNoError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Auto Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Coupon code is required for coupon type promotions', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — discount_type validation
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowMissingDiscountTypeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Discount type is required', $errors);
    }

    #[Test]
    public function promotionRowWithInvalidDiscountTypeReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'bogo',
            'discount_value' => '10',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Discount type must be one of: percentage, fixed', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — discount_value validation
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowMissingDiscountValueReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Discount value is required', $errors);
    }

    #[Test]
    public function promotionRowWithNegativeDiscountValueReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '-1',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Discount value must be non-negative', $errors);
    }

    #[Test]
    public function promotionRowWithPercentageDiscountValueOver100ReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '150',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Discount value cannot exceed 100 for percentage discounts', $errors);
    }

    #[Test]
    public function promotionRowWithFixedDiscountValueOver100ReturnsNoPercentageError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'fixed',
            'discount_value' => '500',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Discount value cannot exceed 100 for percentage discounts', $errors);
    }

    #[Test]
    public function promotionRowWithDiscountValueZeroReturnsNoErrors(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Zero Promo',
            'type' => 'automatic',
            'discount_type' => 'fixed',
            'discount_value' => '0',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertSame([], $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — priority validation
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowWithPriorityZeroReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'priority' => '0',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Priority must be between 1 and 1000', $errors);
    }

    #[Test]
    public function promotionRowWithPriorityAbove1000ReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'priority' => '1001',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('Priority must be between 1 and 1000', $errors);
    }

    #[Test]
    public function promotionRowWithNullPriorityReturnsNoError(): void
    {
        // Priority is optional for promotions — null means no priority set
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'priority' => null,
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Priority must be between 1 and 1000', $errors);
    }

    #[Test]
    public function promotionRowWithPriority1ReturnsNoError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'priority' => '1',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Priority must be between 1 and 1000', $errors);
    }

    #[Test]
    public function promotionRowWithPriority1000ReturnsNoError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'priority' => '1000',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertNotContains('Priority must be between 1 and 1000', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — date validation
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowWithInvalidValidFromReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'valid_from' => 'not-a-date',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('valid_from must be a valid date (Y-m-d format)', $errors);
    }

    #[Test]
    public function promotionRowWithInvalidValidToReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'valid_to' => 'not-a-date',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('valid_to must be a valid date (Y-m-d format)', $errors);
    }

    #[Test]
    public function promotionRowWithValidFromAfterValidToReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'valid_from' => '2025-12-31',
            'valid_to' => '2025-01-01',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('valid_from must be before valid_to', $errors);
    }

    #[Test]
    public function promotionRowWithValidFromEqualToValidToReturnsError(): void
    {
        $row = new ImportRow(1, [
            'name' => 'Test Promo',
            'type' => 'automatic',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'valid_from' => '2025-06-01',
            'valid_to' => '2025-06-01',
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertContains('valid_from must be before valid_to', $errors);
    }

    // -----------------------------------------------------------------------
    // validatePromotionRow — multiple errors
    // -----------------------------------------------------------------------

    #[Test]
    public function promotionRowCanHaveMultipleErrors(): void
    {
        $row = new ImportRow(1, [
            // missing name, missing type, missing discount_type, missing discount_value
        ]);

        $errors = $this->service->validatePromotionRow($row);

        self::assertGreaterThanOrEqual(4, count($errors));
        self::assertContains('Name is required', $errors);
        self::assertContains('Type is required', $errors);
        self::assertContains('Discount type is required', $errors);
        self::assertContains('Discount value is required', $errors);
    }
}
