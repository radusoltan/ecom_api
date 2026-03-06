<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\Query\GetDeletionRequestStatus\GetDeletionRequestStatusQuery;
use App\Customer\Application\Query\GetDeletionRequestStatus\GetDeletionRequestStatusQueryHandler;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetDeletionRequestStatusQueryHandlerTest extends TestCase
{
    public function testItReturnsNullWhenNoRequest(): void
    {
        $repo = $this->createStub(DeletionRequestRepositoryInterface::class);
        $repo->method('findByCustomerId')->willReturn(null);

        $handler = new GetDeletionRequestStatusQueryHandler($repo);
        $result = ($handler)(new GetDeletionRequestStatusQuery(CustomerId::generate(), TenantId::generate()));

        self::assertNull($result);
    }
}
