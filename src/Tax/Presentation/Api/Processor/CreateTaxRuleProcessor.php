<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\CreateTaxRule;
use App\Tax\Application\Query\GetTaxRuleById;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for creating new tax rules.
 */
final class CreateTaxRuleProcessor implements ProcessorInterface
{
    use HandleTrait;

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        MessageBusInterface $queryBus,
        private readonly TaxRuleResourceTransformer $transformer,
    ) {
        $this->messageBus = $queryBus;
    }

    /**
     * @param TaxRuleResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxRuleResource
    {
        if (!$data instanceof TaxRuleResource) {
            throw new \InvalidArgumentException('Expected TaxRuleResource');
        }

        // Validate required fields - use 422 for validation errors
        if (empty($data->tenantId)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('tenantId is required');
        }

        if (empty($data->name)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('name is required');
        }

        if (empty($data->countryCode)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('countryCode is required');
        }

        if (null === $data->ratePercentage) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('ratePercentage is required');
        }

        // Generate ID
        $taxRuleId = TaxRuleId::generate();
        $tenantId = TenantId::fromString($data->tenantId);

        // Create command
        $command = new CreateTaxRule(
            id: $taxRuleId,
            tenantId: $tenantId,
            name: $data->name,
            countryCode: $data->countryCode,
            regionCode: $data->regionCode,
            ratePercentage: $data->ratePercentage
        );

        // Dispatch command - catch HandlerFailedException and extract domain validation errors
        try {
            $this->commandBus->dispatch($command);
        } catch (\Symfony\Component\Messenger\Exception\HandlerFailedException $e) {
            // Extract the original exception from HandlerFailedException
            $originalException = $e->getPrevious();
            if ($originalException instanceof \InvalidArgumentException) {
                throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException($originalException->getMessage(), $originalException);
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        // Fetch the created tax rule to get complete data including isActive
        $query = new GetTaxRuleById(
            id: $taxRuleId,
            tenantId: $tenantId
        );

        $dto = $this->handle($query);

        if (null === $dto) {
            throw new \RuntimeException('Failed to retrieve created tax rule');
        }

        return $this->transformer->fromDTO($dto);
    }
}
