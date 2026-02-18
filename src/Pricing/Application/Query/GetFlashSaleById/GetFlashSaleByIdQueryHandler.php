<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\GetFlashSaleById;

use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetFlashSaleByIdQueryHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository,
    ) {
    }

    public function __invoke(GetFlashSaleByIdQuery $query): FlashSale
    {
        $flashSale = $this->flashSaleRepository->findById($query->flashSaleId, $query->tenantId);

        if (null === $flashSale) {
            throw new NotFoundHttpException('Flash sale not found');
        }

        return $flashSale;
    }
}
