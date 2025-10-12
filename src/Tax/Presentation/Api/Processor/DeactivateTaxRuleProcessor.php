<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\DeactivateTaxRule;
use App\Tax\Application\Query\GetTaxRuleById;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for deactivating tax rules
 */
final class DeactivateTaxRuleProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer
    ) {
        $this->messageBus = $queryBus;
    }

    /**
     * @param TaxRuleResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxRuleResource
    {
        // Get ID from URI variables
        $id = $uriVariables['id'] ?? null;
        if (!$id) {
            throw new BadRequestHttpException('Tax rule ID is required');
        }

        // Validate tenant ID
        if (empty($data->tenantId)) {
            throw new BadRequestHttpException('tenantId is required');
        }

        // Create command
        $command = new DeactivateTaxRule(
            id: TaxRuleId::fromString($id),
            tenantId: TenantId::fromString($data->tenantId)
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Fetch updated tax rule
        $query = new GetTaxRuleById(
            id: TaxRuleId::fromString($id),
            tenantId: TenantId::fromString($data->tenantId)
        );

        $dto = $this->handle($query);

        if ($dto === null) {
            throw new NotFoundHttpException('Tax rule not found');
        }

        return $this->transformer->fromDTO($dto);
    }
}
