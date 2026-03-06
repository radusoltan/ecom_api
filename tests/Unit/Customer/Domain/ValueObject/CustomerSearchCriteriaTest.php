<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerSearchCriteria;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerSearchCriteria::class)]
final class CustomerSearchCriteriaTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
    }

    // ---------------------------------------------------------------
    // Valid creation
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesWithDefaultValues(): void
    {
        $criteria = new CustomerSearchCriteria(tenantId: $this->tenantId);

        self::assertTrue($this->tenantId->equals($criteria->tenantId));
        self::assertNull($criteria->searchTerm);
        self::assertNull($criteria->status);
        self::assertNull($criteria->segment);
        self::assertSame('createdAt', $criteria->sortBy);
        self::assertSame('DESC', $criteria->sortOrder);
        self::assertSame(1, $criteria->page);
        self::assertSame(20, $criteria->limit);
    }

    #[Test]
    public function itCreatesWithAllExplicitValues(): void
    {
        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-12-31');

        $criteria = new CustomerSearchCriteria(
            tenantId: $this->tenantId,
            searchTerm: 'alice',
            status: 'active',
            segment: 'vip',
            registeredFrom: $from,
            registeredTo: $to,
            hasOrders: true,
            minOrderCount: 2,
            maxOrderCount: 10,
            sortBy: 'email',
            sortOrder: 'ASC',
            page: 3,
            limit: 50,
        );

        self::assertSame('alice', $criteria->searchTerm);
        self::assertSame('active', $criteria->status);
        self::assertSame('vip', $criteria->segment);
        self::assertSame($from, $criteria->registeredFrom);
        self::assertSame($to, $criteria->registeredTo);
        self::assertTrue($criteria->hasOrders);
        self::assertSame(2, $criteria->minOrderCount);
        self::assertSame(10, $criteria->maxOrderCount);
        self::assertSame('email', $criteria->sortBy);
        self::assertSame('ASC', $criteria->sortOrder);
        self::assertSame(3, $criteria->page);
        self::assertSame(50, $criteria->limit);
    }

    // ---------------------------------------------------------------
    // Pagination validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPageIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be greater than 0');

        new CustomerSearchCriteria(tenantId: $this->tenantId, page: 0);
    }

    #[Test]
    public function itThrowsWhenLimitIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be greater than 0');

        new CustomerSearchCriteria(tenantId: $this->tenantId, limit: 0);
    }

    #[Test]
    public function itThrowsWhenLimitExceeds100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit cannot exceed 100');

        new CustomerSearchCriteria(tenantId: $this->tenantId, limit: 101);
    }

    #[Test]
    public function itAcceptsLimitOf100(): void
    {
        $criteria = new CustomerSearchCriteria(tenantId: $this->tenantId, limit: 100);

        self::assertSame(100, $criteria->limit);
    }

    // ---------------------------------------------------------------
    // Sort validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsForInvalidSortField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort field: "invalidField"');

        new CustomerSearchCriteria(tenantId: $this->tenantId, sortBy: 'invalidField');
    }

    #[Test]
    public function itThrowsForInvalidSortOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort order: "RANDOM"');

        new CustomerSearchCriteria(tenantId: $this->tenantId, sortOrder: 'RANDOM');
    }

    #[Test]
    public function itAcceptsAllAllowedSortFields(): void
    {
        $allowedFields = ['createdAt', 'updatedAt', 'firstName', 'lastName', 'email', 'loyaltyPoints'];

        foreach ($allowedFields as $field) {
            $criteria = new CustomerSearchCriteria(tenantId: $this->tenantId, sortBy: $field);
            self::assertSame($field, $criteria->sortBy);
        }
    }

    // ---------------------------------------------------------------
    // Date range validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenRegisteredFromIsAfterRegisteredTo(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('registeredFrom cannot be after registeredTo');

        new CustomerSearchCriteria(
            tenantId: $this->tenantId,
            registeredFrom: new \DateTimeImmutable('2025-12-31'),
            registeredTo: new \DateTimeImmutable('2025-01-01'),
        );
    }

    #[Test]
    public function itAllowsEqualRegisteredFromAndTo(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');

        $criteria = new CustomerSearchCriteria(
            tenantId: $this->tenantId,
            registeredFrom: $date,
            registeredTo: $date,
        );

        self::assertSame($date, $criteria->registeredFrom);
        self::assertSame($date, $criteria->registeredTo);
    }

    // ---------------------------------------------------------------
    // getOffset()
    // ---------------------------------------------------------------

    #[Test]
    public function itCalculatesOffsetForFirstPage(): void
    {
        $criteria = new CustomerSearchCriteria(tenantId: $this->tenantId, page: 1, limit: 20);

        self::assertSame(0, $criteria->getOffset());
    }

    #[Test]
    public function itCalculatesOffsetForSubsequentPages(): void
    {
        $criteria = new CustomerSearchCriteria(tenantId: $this->tenantId, page: 3, limit: 20);

        self::assertSame(40, $criteria->getOffset());
    }

    #[Test]
    public function itCalculatesOffsetWithCustomLimit(): void
    {
        $criteria = new CustomerSearchCriteria(tenantId: $this->tenantId, page: 5, limit: 10);

        self::assertSame(40, $criteria->getOffset());
    }
}
