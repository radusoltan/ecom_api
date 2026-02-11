<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Domain\Model;

use App\Search\Domain\Model\FacetBucket;
use PHPUnit\Framework\TestCase;

final class FacetBucketTest extends TestCase
{
    public function testItCreatesWithValidData(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'electronics',
            label: 'Electronics',
            count: 42,
            selected: false
        );

        // Assert
        $this->assertSame('electronics', $bucket->key);
        $this->assertSame('Electronics', $bucket->label);
        $this->assertSame(42, $bucket->count);
        $this->assertFalse($bucket->selected);
    }

    public function testItCreatesWithZeroCount(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'empty-category',
            label: 'Empty Category',
            count: 0
        );

        // Assert
        $this->assertSame(0, $bucket->count);
    }

    public function testItCreatesWithSelectedTrue(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'selected-category',
            label: 'Selected',
            count: 10,
            selected: true
        );

        // Assert
        $this->assertTrue($bucket->selected);
        $this->assertTrue($bucket->isSelected());
    }

    public function testItCreatesWithDefaultSelectedFalse(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'unselected',
            label: 'Unselected',
            count: 5
        );

        // Assert
        $this->assertFalse($bucket->selected);
        $this->assertFalse($bucket->isSelected());
    }

    public function testIsSelectedReturnsTrueWhenSelected(): void
    {
        // Arrange
        $bucket = new FacetBucket(
            key: 'test',
            label: 'Test',
            count: 1,
            selected: true
        );

        // Act & Assert
        $this->assertTrue($bucket->isSelected());
    }

    public function testIsSelectedReturnsFalseWhenNotSelected(): void
    {
        // Arrange
        $bucket = new FacetBucket(
            key: 'test',
            label: 'Test',
            count: 1,
            selected: false
        );

        // Act & Assert
        $this->assertFalse($bucket->isSelected());
    }

    public function testItThrowsExceptionWhenCountIsNegative(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bucket count cannot be negative');

        // Act
        new FacetBucket(
            key: 'invalid',
            label: 'Invalid',
            count: -1
        );
    }

    public function testItThrowsExceptionWhenCountIsLargeNegative(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bucket count cannot be negative');

        // Act
        new FacetBucket(
            key: 'invalid',
            label: 'Invalid',
            count: -999
        );
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $bucket = new FacetBucket(
            key: 'smartphones',
            label: 'Smartphones',
            count: 150,
            selected: true
        );

        // Act
        $array = $bucket->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('key', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertArrayHasKey('count', $array);
        $this->assertArrayHasKey('selected', $array);
        $this->assertSame('smartphones', $array['key']);
        $this->assertSame('Smartphones', $array['label']);
        $this->assertSame(150, $array['count']);
        $this->assertTrue($array['selected']);
    }

    public function testToArrayWithUnselectedBucket(): void
    {
        // Arrange
        $bucket = new FacetBucket(
            key: 'laptops',
            label: 'Laptops',
            count: 75
        );

        // Act
        $array = $bucket->toArray();

        // Assert
        $this->assertFalse($array['selected']);
    }

    public function testToArrayWithZeroCount(): void
    {
        // Arrange
        $bucket = new FacetBucket(
            key: 'empty',
            label: 'Empty',
            count: 0
        );

        // Act
        $array = $bucket->toArray();

        // Assert
        $this->assertSame(0, $array['count']);
    }

    public function testItCreatesWithLargeCount(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'popular',
            label: 'Popular',
            count: 999999
        );

        // Assert
        $this->assertSame(999999, $bucket->count);
    }

    public function testItCreatesWithEmptyKey(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: '',
            label: 'Empty Key',
            count: 1
        );

        // Assert - No exception should be thrown
        $this->assertSame('', $bucket->key);
    }

    public function testItCreatesWithEmptyLabel(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'empty-label',
            label: '',
            count: 1
        );

        // Assert - No exception should be thrown
        $this->assertSame('', $bucket->label);
    }

    public function testItCreatesWithSpecialCharactersInKey(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'category-with-dashes_and_underscores',
            label: 'Category with special chars',
            count: 10
        );

        // Assert
        $this->assertSame('category-with-dashes_and_underscores', $bucket->key);
    }

    public function testItCreatesWithUnicodeCharactersInLabel(): void
    {
        // Arrange & Act
        $bucket = new FacetBucket(
            key: 'electronics',
            label: 'Électronique & Informatique',
            count: 50
        );

        // Assert
        $this->assertSame('Électronique & Informatique', $bucket->label);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $bucket = new FacetBucket(
            key: 'test',
            label: 'Test',
            count: 1
        );

        // Assert - Properties are readonly
        $this->assertSame('test', $bucket->key);
        $this->assertSame('Test', $bucket->label);
        $this->assertSame(1, $bucket->count);
        $this->assertFalse($bucket->selected);
    }
}
