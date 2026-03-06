<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\Query\GetFlashSaleById\GetFlashSaleByIdQuery;
use App\Pricing\Application\Query\GetFlashSaleById\GetFlashSaleByIdQueryHandler;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(GetFlashSaleByIdQueryHandler::class)]
final class GetFlashSaleByIdQueryHandlerTest extends TestCase
{
    private FlashSaleRepositoryInterface $repository;
    private GetFlashSaleByIdQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FlashSaleRepositoryInterface::class);
        $this->handler = new GetFlashSaleByIdQueryHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFlashSaleWhenFound(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $flashSale = $this->createMock(FlashSale::class);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($flashSaleId, $this->tenantId)
            ->willReturn($flashSale);

        $query = new GetFlashSaleByIdQuery($flashSaleId, $this->tenantId);
        $result = ($this->handler)($query);

        self::assertSame($flashSale, $result);
    }

    // -----------------------------------------------------------------------
    // Not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsNotFoundExceptionWhenFlashSaleDoesNotExist(): void
    {
        $flashSaleId = FlashSaleId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($flashSaleId, $this->tenantId)
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Flash sale not found');

        $query = new GetFlashSaleByIdQuery($flashSaleId, $this->tenantId);
        ($this->handler)($query);
    }

    #[Test]
    public function itPassesBothFlashSaleIdAndTenantIdToRepository(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $tenantId = TenantId::generate();
        $flashSale = $this->createMock(FlashSale::class);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn ($id) => $id->toString() === $flashSaleId->toString()),
                self::callback(fn ($tid) => $tid->toString() === $tenantId->toString()),
            )
            ->willReturn($flashSale);

        $query = new GetFlashSaleByIdQuery($flashSaleId, $tenantId);
        $result = ($this->handler)($query);

        self::assertInstanceOf(FlashSale::class, $result);
    }
}
