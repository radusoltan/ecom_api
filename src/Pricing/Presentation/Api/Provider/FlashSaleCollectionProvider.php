<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetActiveFlashSales\GetActiveFlashSalesQuery;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProviderInterface<FlashSaleEntity>
 */
final class FlashSaleCollectionProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @return array<FlashSaleEntity>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        $query = new GetActiveFlashSalesQuery(
            tenantId: TenantId::fromString($tenantIdHeader)
        );

        $flashSales = $this->handle($query);

        return array_map(
            fn ($flashSale) => FlashSaleEntity::fromDomainModel($flashSale),
            $flashSales
        );
    }
}
