<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\UpdatePromotion\UpdatePromotionCommand;
use App\Pricing\Application\Query\GetPromotionById\GetPromotionByIdQuery;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class UpdatePromotionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?PromotionEntity
    {
        assert($data instanceof PromotionEntity);

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantId = $context['tenant_id'] ?? null;
        if (null === $tenantId) {
            throw new \RuntimeException('Tenant ID not found in context');
        }

        $promotionId = PromotionId::fromString($data->getId());
        $tenantIdVO = TenantId::fromString($tenantId);

        $command = new UpdatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $tenantIdVO,
            name: $data->getName(),
            discountType: $data->getDiscountType(),
            discountValue: $data->getDiscountValue(),
            priority: $data->getPriority(),
            conditions: $data->getConditions(),
            validFrom: $data->getValidFrom()?->format(\DateTimeInterface::ATOM),
            validTo: $data->getValidTo()?->format(\DateTimeInterface::ATOM)
        );

        $this->commandBus->dispatch($command);

        // Retrieve the updated promotion
        $envelope = $this->queryBus->dispatch(
            new GetPromotionByIdQuery($promotionId, $tenantIdVO)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $promotionDTO = $handledStamp->getResult();

        if (null === $promotionDTO) {
            throw new \RuntimeException('Promotion not found after update');
        }

        return PromotionEntity::fromDTO($promotionDTO);
    }
}
