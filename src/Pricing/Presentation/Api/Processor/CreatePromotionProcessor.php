<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Application\Query\GetPromotionById\GetPromotionByIdQuery;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class CreatePromotionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus
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

        $promotionId = PromotionId::generate();
        $tenantIdVO = TenantId::fromString($tenantId);

        $command = new CreatePromotionCommand(
            promotionId: $promotionId,
            tenantId: $tenantIdVO,
            name: $data->getName(),
            type: $data->getType(),
            discountType: $data->getDiscountType(),
            discountValue: $data->getDiscountValue(),
            priority: $data->getPriority(),
            couponCode: $data->getCouponCode(),
            conditions: $data->getConditions(),
            validFrom: $data->getValidFrom()?->format(\DateTimeInterface::ATOM),
            validTo: $data->getValidTo()?->format(\DateTimeInterface::ATOM)
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof HttpExceptionInterface) {
                throw $previous;
            }

            throw $exception;
        }

        // Retrieve the created promotion
        $envelope = $this->queryBus->dispatch(
            new GetPromotionByIdQuery($promotionId, $tenantIdVO)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $promotionDTO = $handledStamp->getResult();

        if (null === $promotionDTO) {
            throw new \RuntimeException('Promotion not found after creation');
        }

        return PromotionEntity::fromDTO($promotionDTO);
    }
}
