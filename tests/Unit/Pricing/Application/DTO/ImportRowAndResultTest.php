<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\DTO;

use App\Pricing\Application\DTO\ImportResult;
use App\Pricing\Application\DTO\ImportRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportRow::class)]
#[CoversClass(ImportResult::class)]
final class ImportRowAndResultTest extends TestCase
{
    // -----------------------------------------------------------------------
    // ImportRow::rowNumber and data
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesRowNumberAndData(): void
    {
        $row = new ImportRow(3, ['name' => 'Summer Sale', 'priority' => '100']);

        self::assertSame(3, $row->rowNumber);
        self::assertSame(['name' => 'Summer Sale', 'priority' => '100'], $row->data);
    }

    // -----------------------------------------------------------------------
    // ImportRow::has
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueWhenKeyExists(): void
    {
        $row = new ImportRow(1, ['name' => 'Test', 'empty' => null]);

        self::assertTrue($row->has('name'));
    }

    #[Test]
    public function itReturnsTrueForNullValuedKey(): void
    {
        $row = new ImportRow(1, ['empty' => null]);

        self::assertTrue($row->has('empty'));
    }

    #[Test]
    public function itReturnsFalseWhenKeyMissing(): void
    {
        $row = new ImportRow(1, ['name' => 'Test']);

        self::assertFalse($row->has('missing_key'));
    }

    // -----------------------------------------------------------------------
    // ImportRow::get
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsValueForExistingKey(): void
    {
        $row = new ImportRow(1, ['priority' => 42]);

        self::assertSame(42, $row->get('priority'));
    }

    #[Test]
    public function itReturnsDefaultWhenKeyMissing(): void
    {
        $row = new ImportRow(1, []);

        self::assertNull($row->get('missing'));
    }

    #[Test]
    public function itReturnsCustomDefaultWhenKeyMissing(): void
    {
        $row = new ImportRow(1, []);

        self::assertSame('fallback', $row->get('missing', 'fallback'));
    }

    #[Test]
    public function itReturnsDefaultEvenWhenStoredValueIsNull(): void
    {
        // get() uses ?? so a null value falls through to the default
        $row = new ImportRow(1, ['key' => null]);

        self::assertSame('default', $row->get('key', 'default'));
    }

    // -----------------------------------------------------------------------
    // ImportRow::getString
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsStringValue(): void
    {
        $row = new ImportRow(1, ['name' => 'Summer Sale']);

        self::assertSame('Summer Sale', $row->getString('name'));
    }

    #[Test]
    public function itCastsIntToString(): void
    {
        $row = new ImportRow(1, ['code' => 123]);

        self::assertSame('123', $row->getString('code'));
    }

    #[Test]
    public function itReturnsNullForMissingKey(): void
    {
        $row = new ImportRow(1, []);

        self::assertNull($row->getString('missing'));
    }

    #[Test]
    public function itReturnsNullForNullValue(): void
    {
        $row = new ImportRow(1, ['name' => null]);

        self::assertNull($row->getString('name'));
    }

    #[Test]
    public function itReturnsNullForEmptyString(): void
    {
        $row = new ImportRow(1, ['name' => '']);

        self::assertNull($row->getString('name'));
    }

    // -----------------------------------------------------------------------
    // ImportRow::getInt
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsIntegerValue(): void
    {
        $row = new ImportRow(1, ['priority' => 100]);

        self::assertSame(100, $row->getInt('priority'));
    }

    #[Test]
    public function itCastsStringToInt(): void
    {
        $row = new ImportRow(1, ['priority' => '50']);

        self::assertSame(50, $row->getInt('priority'));
    }

    #[Test]
    public function itReturnsNullForMissingIntKey(): void
    {
        $row = new ImportRow(1, []);

        self::assertNull($row->getInt('priority'));
    }

    #[Test]
    public function itReturnsNullForNullIntValue(): void
    {
        $row = new ImportRow(1, ['priority' => null]);

        self::assertNull($row->getInt('priority'));
    }

    #[Test]
    public function itReturnsNullForEmptyStringIntValue(): void
    {
        $row = new ImportRow(1, ['priority' => '']);

        self::assertNull($row->getInt('priority'));
    }

    #[Test]
    public function itCastsFloatStringToInt(): void
    {
        $row = new ImportRow(1, ['priority' => '7.9']);

        self::assertSame(7, $row->getInt('priority'));
    }

    // -----------------------------------------------------------------------
    // ImportRow::getFloat
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFloatValue(): void
    {
        $row = new ImportRow(1, ['discount_value' => 19.99]);

        self::assertSame(19.99, $row->getFloat('discount_value'));
    }

    #[Test]
    public function itCastsStringToFloat(): void
    {
        $row = new ImportRow(1, ['discount_value' => '15.5']);

        self::assertSame(15.5, $row->getFloat('discount_value'));
    }

    #[Test]
    public function itReturnsNullForMissingFloatKey(): void
    {
        $row = new ImportRow(1, []);

        self::assertNull($row->getFloat('discount_value'));
    }

    #[Test]
    public function itReturnsNullForNullFloatValue(): void
    {
        $row = new ImportRow(1, ['discount_value' => null]);

        self::assertNull($row->getFloat('discount_value'));
    }

    #[Test]
    public function itReturnsNullForEmptyStringFloatValue(): void
    {
        $row = new ImportRow(1, ['discount_value' => '']);

        self::assertNull($row->getFloat('discount_value'));
    }

    #[Test]
    public function itCastsIntToFloat(): void
    {
        $row = new ImportRow(1, ['discount_value' => 10]);

        self::assertSame(10.0, $row->getFloat('discount_value'));
    }

    // -----------------------------------------------------------------------
    // ImportRow::getBool
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueForBooleanTrue(): void
    {
        $row = new ImportRow(1, ['is_active' => true]);

        self::assertTrue($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsFalseForBooleanFalse(): void
    {
        $row = new ImportRow(1, ['is_active' => false]);

        self::assertFalse($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsTrueForStringTrue(): void
    {
        $row = new ImportRow(1, ['is_active' => 'true']);

        self::assertTrue($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsTrueForStringOne(): void
    {
        $row = new ImportRow(1, ['is_active' => '1']);

        self::assertTrue($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsTrueForStringYes(): void
    {
        $row = new ImportRow(1, ['is_active' => 'yes']);

        self::assertTrue($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsTrueForStringOn(): void
    {
        $row = new ImportRow(1, ['is_active' => 'on']);

        self::assertTrue($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsTrueForStringTrueUppercase(): void
    {
        $row = new ImportRow(1, ['is_active' => 'TRUE']);

        self::assertTrue($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsFalseForStringFalse(): void
    {
        $row = new ImportRow(1, ['is_active' => 'false']);

        self::assertFalse($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsFalseForStringZero(): void
    {
        $row = new ImportRow(1, ['is_active' => '0']);

        self::assertFalse($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsFalseForStringNo(): void
    {
        $row = new ImportRow(1, ['is_active' => 'no']);

        self::assertFalse($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsDefaultForMissingBoolKey(): void
    {
        $row = new ImportRow(1, []);

        self::assertFalse($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsCustomDefaultForMissingBoolKey(): void
    {
        $row = new ImportRow(1, []);

        self::assertTrue($row->getBool('is_active', true));
    }

    #[Test]
    public function itReturnsDefaultForNullBoolValue(): void
    {
        $row = new ImportRow(1, ['is_active' => null]);

        self::assertFalse($row->getBool('is_active'));
    }

    #[Test]
    public function itReturnsDefaultForEmptyStringBoolValue(): void
    {
        $row = new ImportRow(1, ['is_active' => '']);

        self::assertFalse($row->getBool('is_active'));
    }

    // -----------------------------------------------------------------------
    // ImportResult construction
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesAllConstructorProperties(): void
    {
        $errors = [1 => 'Name is required', 3 => 'Priority out of range'];

        $result = new ImportResult(
            totalRows: 10,
            successCount: 8,
            errorCount: 2,
            errors: $errors,
            createdCount: 5,
            updatedCount: 3,
        );

        self::assertSame(10, $result->totalRows);
        self::assertSame(8, $result->successCount);
        self::assertSame(2, $result->errorCount);
        self::assertSame($errors, $result->errors);
        self::assertSame(5, $result->createdCount);
        self::assertSame(3, $result->updatedCount);
    }

    // -----------------------------------------------------------------------
    // ImportResult::isSuccessful
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsSuccessfulWhenErrorCountIsZero(): void
    {
        $result = new ImportResult(
            totalRows: 5,
            successCount: 5,
            errorCount: 0,
            errors: [],
            createdCount: 5,
            updatedCount: 0,
        );

        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function itIsNotSuccessfulWhenErrorCountIsNonZero(): void
    {
        $result = new ImportResult(
            totalRows: 5,
            successCount: 3,
            errorCount: 2,
            errors: [1 => 'error'],
            createdCount: 3,
            updatedCount: 0,
        );

        self::assertFalse($result->isSuccessful());
    }

    #[Test]
    public function itIsSuccessfulWhenTotalRowsIsZero(): void
    {
        $result = new ImportResult(
            totalRows: 0,
            successCount: 0,
            errorCount: 0,
            errors: [],
            createdCount: 0,
            updatedCount: 0,
        );

        self::assertTrue($result->isSuccessful());
    }

    // -----------------------------------------------------------------------
    // ImportResult::hasPartialSuccess
    // -----------------------------------------------------------------------

    #[Test]
    public function itHasPartialSuccessWhenBothSuccessAndErrorsExist(): void
    {
        $result = new ImportResult(
            totalRows: 5,
            successCount: 3,
            errorCount: 2,
            errors: [2 => 'err'],
            createdCount: 3,
            updatedCount: 0,
        );

        self::assertTrue($result->hasPartialSuccess());
    }

    #[Test]
    public function itDoesNotHavePartialSuccessWhenAllSucceed(): void
    {
        $result = new ImportResult(
            totalRows: 5,
            successCount: 5,
            errorCount: 0,
            errors: [],
            createdCount: 5,
            updatedCount: 0,
        );

        self::assertFalse($result->hasPartialSuccess());
    }

    #[Test]
    public function itDoesNotHavePartialSuccessWhenAllFail(): void
    {
        $result = new ImportResult(
            totalRows: 3,
            successCount: 0,
            errorCount: 3,
            errors: [1 => 'e1', 2 => 'e2', 3 => 'e3'],
            createdCount: 0,
            updatedCount: 0,
        );

        self::assertFalse($result->hasPartialSuccess());
    }

    #[Test]
    public function itDoesNotHavePartialSuccessWhenEmpty(): void
    {
        $result = new ImportResult(
            totalRows: 0,
            successCount: 0,
            errorCount: 0,
            errors: [],
            createdCount: 0,
            updatedCount: 0,
        );

        self::assertFalse($result->hasPartialSuccess());
    }

    // -----------------------------------------------------------------------
    // ImportResult::toArray
    // -----------------------------------------------------------------------

    #[Test]
    public function itConvertsToArrayWithAllFields(): void
    {
        $errors = [2 => 'Name is required'];

        $result = new ImportResult(
            totalRows: 10,
            successCount: 9,
            errorCount: 1,
            errors: $errors,
            createdCount: 7,
            updatedCount: 2,
        );

        $array = $result->toArray();

        self::assertSame(10, $array['total_rows']);
        self::assertSame(9, $array['success_count']);
        self::assertSame(1, $array['error_count']);
        self::assertSame(7, $array['created_count']);
        self::assertSame(2, $array['updated_count']);
        self::assertSame($errors, $array['errors']);
        self::assertFalse($array['is_successful']);
        self::assertTrue($array['has_partial_success']);
    }

    #[Test]
    public function itConvertsSuccessfulResultToArray(): void
    {
        $result = new ImportResult(
            totalRows: 5,
            successCount: 5,
            errorCount: 0,
            errors: [],
            createdCount: 3,
            updatedCount: 2,
        );

        $array = $result->toArray();

        self::assertTrue($array['is_successful']);
        self::assertFalse($array['has_partial_success']);
        self::assertSame([], $array['errors']);
        self::assertSame(3, $array['created_count']);
        self::assertSame(2, $array['updated_count']);
    }

    #[Test]
    public function toArrayKeysAreComplete(): void
    {
        $result = new ImportResult(0, 0, 0, [], 0, 0);
        $array = $result->toArray();

        $expectedKeys = [
            'total_rows',
            'success_count',
            'error_count',
            'created_count',
            'updated_count',
            'errors',
            'is_successful',
            'has_partial_success',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $array, "Missing key: $key");
        }
    }
}
