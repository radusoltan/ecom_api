<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetFlashSaleById\GetFlashSaleByIdQuery;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProviderInterface<FlashSaleEntity>
 */
final class FlashSaleItemProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
        $this->messageBus = $messageBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?FlashSaleEntity
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        $query = new GetFlashSaleByIdQuery(
            flashSaleId: FlashSaleId::fromString((string) $uriVariables['id']),
            tenantId: TenantId::fromString($tenantIdHeader)
        );

        $flashSale = $this->handle($query);

        return FlashSaleEntity::fromDomainModel($flashSale);
    }
}
