<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\CreateTaxRule;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use App\Tax\Presentation\Api\Transformer\TaxRuleResourceTransformer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for creating new tax rules
 */
final readonly class CreateTaxRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TaxRuleResourceTransformer $transformer
    ) {
    }

    /**
     * @param TaxRuleResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaxRuleResource
    {
        if (!$data instanceof TaxRuleResource) {
            throw new \InvalidArgumentException('Expected TaxRuleResource');
        }

        // Validate required fields
        if (empty($data->tenantId)) {
            throw new BadRequestHttpException('tenantId is required');
        }

        if (empty($data->name)) {
            throw new BadRequestHttpException('name is required');
        }

        if (empty($data->countryCode)) {
            throw new BadRequestHttpException('countryCode is required');
        }

        if ($data->ratePercentage === null) {
            throw new BadRequestHttpException('ratePercentage is required');
        }

        // Create command
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::fromString($data->tenantId),
            name: $data->name,
            countryCode: $data->countryCode,
            regionCode: $data->regionCode,
            ratePercentage: $data->ratePercentage
        );

        // Dispatch command
        $this->commandBus->dispatch($command);

        // Return resource with generated ID
        $data->id = $command->id->toString();

        return $data;
    }
}
