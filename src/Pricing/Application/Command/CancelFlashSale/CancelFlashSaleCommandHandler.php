<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\CancelFlashSale;

use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelFlashSaleCommandHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository,
    ) {
    }

    public function __invoke(CancelFlashSaleCommand $command): void
    {
        $flashSale = $this->flashSaleRepository->findById($command->flashSaleId, $command->tenantId);

        if (null === $flashSale) {
            throw new NotFoundHttpException('Flash sale not found');
        }

        try {
            $flashSale->cancel();
        } catch (\DomainException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $this->flashSaleRepository->save($flashSale);
    }
}
