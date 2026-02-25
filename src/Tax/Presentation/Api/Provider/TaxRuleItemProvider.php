<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetTaxRuleById;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Provider for retrieving a single tax rule.
 */
final class TaxRuleItemProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer,
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?TaxRuleResource
    {
        // Get ID from URI variables
        $id = $uriVariables['id'] ?? null;
        if (!$id) {
            throw new BadRequestHttpException('Tax rule ID is required');
        }

        // Extract tenant ID from context (injected by TenantContextProvider)
        $tenantId = TenantId::fromString(
            $context['tenant_id'] ?? throw new \InvalidArgumentException('Tenant ID is required')
        );

        try {
            $taxRuleId = TaxRuleId::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Tax rule not found');
        }

        // Create query
        $query = new GetTaxRuleById(
            id: $taxRuleId,
            tenantId: $tenantId
        );

        // Execute query
        $dto = $this->handle($query);

        if (null === $dto) {
            throw new NotFoundHttpException('Tax rule not found');
        }

        return $this->transformer->fromDTO($dto);
    }
}
