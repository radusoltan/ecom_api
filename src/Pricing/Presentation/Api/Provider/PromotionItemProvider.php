<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Pricing\Application\Query\GetPromotionById\GetPromotionByIdQuery;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PromotionEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        $tenantIdValue = $context['tenant_id'] ?? null;
        if ($tenantIdValue === null) {
            throw new BadRequestHttpException('Tenant ID not found in context');
        }

        try {
            $tenantId = TenantId::fromString($tenantIdValue);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        try {
            $promotionId = PromotionId::fromString($uriVariables['id']);
        } catch (\InvalidArgumentException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $dto = $this->handle(new GetPromotionByIdQuery(
            promotionId: $promotionId,
            tenantId: $tenantId
        ));

        if ($dto === null) {
            return null;
        }

        return PromotionEntity::fromDTO($dto);
    }
}
