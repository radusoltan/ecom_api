<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\CreateFlashSale;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\Message\ActivateFlashSaleMessage;
use App\Pricing\Application\Message\DeactivateFlashSaleMessage;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class CreateFlashSaleCommandHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(CreateFlashSaleCommand $command): void
    {
        try {
            $startTime = new \DateTimeImmutable($command->startTime);
            $endTime = new \DateTimeImmutable($command->endTime);

            $productIds = array_map(
                fn (string $productId) => ProductId::fromString($productId),
                $command->productIds
            );

            $flashSale = FlashSale::create(
                id: $command->flashSaleId,
                tenantId: $command->tenantId,
                name: $command->name,
                productIds: $productIds,
                discount: Discount::fromTypeAndValue($command->discountType, $command->discountValue),
                startTime: $startTime,
                endTime: $endTime
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $this->flashSaleRepository->save($flashSale);

        // Schedule activation message
        $now = new \DateTimeImmutable();
        $activationDelay = max(0, $startTime->getTimestamp() - $now->getTimestamp());

        $this->messageBus->dispatch(
            new ActivateFlashSaleMessage(
                $flashSale->id()->toString(),
                $flashSale->tenantId()->toString()
            ),
            [new DelayStamp($activationDelay * 1000)] // milliseconds
        );

        // Schedule deactivation message
        $deactivationDelay = max(0, $endTime->getTimestamp() - $now->getTimestamp());

        $this->messageBus->dispatch(
            new DeactivateFlashSaleMessage(
                $flashSale->id()->toString(),
                $flashSale->tenantId()->toString()
            ),
            [new DelayStamp($deactivationDelay * 1000)] // milliseconds
        );
    }
}
