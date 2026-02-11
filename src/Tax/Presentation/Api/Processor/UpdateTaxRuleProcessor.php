<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\UpdateTaxRule;
use App\Tax\Domain\ValueObject\TaxRuleId;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor for updating tax rules.
 */
final readonly class UpdateTaxRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
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

        // Get ID from URI variables
        $id = $uriVariables['id'] ?? null;
        if (!$id) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('Tax rule ID is required');
        }

        // Validate required fields - use 422 for validation errors
        if (empty($data->tenantId)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('tenantId is required');
        }

        if (empty($data->name)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('name is required');
        }

        if (null === $data->ratePercentage) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('ratePercentage is required');
        }

        // Create command
        $command = new UpdateTaxRule(
            id: TaxRuleId::fromString($id),
            tenantId: TenantId::fromString($data->tenantId),
            name: $data->name,
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

        // Set ID in resource
        $data->id = $id;

        return $data;
    }
}
