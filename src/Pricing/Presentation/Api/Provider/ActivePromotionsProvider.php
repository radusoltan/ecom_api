<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetActivePromotions\GetActivePromotionsQuery;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Active Promotions Collection Provider.
 *
 * Provides only currently active promotions for the storefront or admin UI
 * to display applicable discounts.
 */
final readonly class ActivePromotionsProvider implements ProviderInterface
{
    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    /**
     * @return PromotionEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $envelope = $this->messageBus->dispatch(new GetActivePromotionsQuery(
            tenantId: TenantId::fromString($context['tenant_id'] ?? '')
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        $dtos = $handledStamp?->getResult() ?? [];

        return array_map(fn ($dto) => PromotionEntity::fromDTO($dto), $dtos);
    }
}
