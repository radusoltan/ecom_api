<?php

declare(strict_types=1);

namespace App\Pricing\Application\MessageHandler;

use App\Pricing\Application\Message\ActivateFlashSaleMessage;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ActivateFlashSaleMessageHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ActivateFlashSaleMessage $message): void
    {
        $flashSaleId = $message->getFlashSaleId();
        $tenantId = $message->getTenantId();

        $this->logger->info('Activating flash sale', [
            'flash_sale_id' => $flashSaleId->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);

        $flashSale = $this->flashSaleRepository->findById($flashSaleId, $tenantId);

        if (null === $flashSale) {
            $this->logger->warning('Flash sale not found for activation', [
                'flash_sale_id' => $flashSaleId->toString(),
                'tenant_id' => $tenantId->toString(),
            ]);

            return;
        }

        try {
            $flashSale->activate();
            $this->flashSaleRepository->save($flashSale);

            $this->logger->info('Flash sale activated successfully', [
                'flash_sale_id' => $flashSaleId->toString(),
                'tenant_id' => $tenantId->toString(),
            ]);
        } catch (\DomainException $e) {
            $this->logger->error('Failed to activate flash sale', [
                'flash_sale_id' => $flashSaleId->toString(),
                'tenant_id' => $tenantId->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
