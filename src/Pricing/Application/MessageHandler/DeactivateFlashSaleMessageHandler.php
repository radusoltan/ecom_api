<?php

declare(strict_types=1);

namespace App\Pricing\Application\MessageHandler;

use App\Pricing\Application\Message\DeactivateFlashSaleMessage;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeactivateFlashSaleMessageHandler
{
    public function __construct(
        private FlashSaleRepositoryInterface $flashSaleRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(DeactivateFlashSaleMessage $message): void
    {
        $flashSaleId = $message->getFlashSaleId();
        $tenantId = $message->getTenantId();

        $this->logger->info('Deactivating flash sale', [
            'flash_sale_id' => $flashSaleId->toString(),
            'tenant_id' => $tenantId->toString(),
        ]);

        $flashSale = $this->flashSaleRepository->findById($flashSaleId, $tenantId);

        if (null === $flashSale) {
            $this->logger->warning('Flash sale not found for deactivation', [
                'flash_sale_id' => $flashSaleId->toString(),
                'tenant_id' => $tenantId->toString(),
            ]);

            return;
        }

        try {
            $flashSale->end();
            $this->flashSaleRepository->save($flashSale);

            $this->logger->info('Flash sale deactivated successfully', [
                'flash_sale_id' => $flashSaleId->toString(),
                'tenant_id' => $tenantId->toString(),
            ]);
        } catch (\DomainException $e) {
            $this->logger->error('Failed to deactivate flash sale', [
                'flash_sale_id' => $flashSaleId->toString(),
                'tenant_id' => $tenantId->toString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
