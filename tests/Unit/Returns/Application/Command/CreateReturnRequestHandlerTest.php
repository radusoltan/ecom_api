<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\Command;

use App\Returns\Application\Command\CreateReturnRequest;
use App\Returns\Application\Command\CreateReturnRequestHandler;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Tests for CreateReturnRequestHandler.
 */
final class CreateReturnRequestHandlerTest extends TestCase
{
    private ReturnRequestRepositoryInterface $repository;
    private CreateReturnRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);
        $this->handler = new CreateReturnRequestHandler($this->repository);
    }

    public function testHandleCreatesAndPersistsReturnRequest(): void
    {
        $returnRequestId = ReturnRequestId::generate()->toString();
        $tenantId = TenantId::generate()->toString();
        $orderId = Uuid::v4()->toString();

        $command = new CreateReturnRequest(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            orderId: $orderId,
            reason: 'Product arrived damaged and is not usable',
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ReturnRequest $rr) use ($returnRequestId, $orderId): bool {
                return $rr->id()->toString() === $returnRequestId
                    && $rr->orderId()->toString() === $orderId
                    && $rr->reason()->value() === 'Product arrived damaged and is not usable'
                    && $rr->status()->isRequested();
            }));

        ($this->handler)($command);
    }

    public function testHandleFailsWithShortReason(): void
    {
        $command = new CreateReturnRequest(
            returnRequestId: ReturnRequestId::generate()->toString(),
            tenantId: TenantId::generate()->toString(),
            orderId: Uuid::v4()->toString(),
            reason: 'Short',
        );

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($command);
    }
}
