<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetPromotionById\GetPromotionByIdQuery;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class PromotionItemProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PromotionEntity
    {
        $dto = $this->handle(new GetPromotionByIdQuery(
            promotionId: PromotionId::fromString($uriVariables['id']),
            tenantId: TenantId::fromString($context['tenant_id'] ?? '')
        ));

        if ($dto === null) {
            return null;
        }

        return PromotionEntity::fromDTO($dto);
    }
}
