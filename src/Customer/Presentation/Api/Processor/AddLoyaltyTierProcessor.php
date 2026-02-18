<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\AddLoyaltyTier\AddLoyaltyTierCommand;
use App\Customer\Presentation\Api\Resource\LoyaltyTierResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Add Loyalty Tier Processor.
 *
 * Processes the addition of a new tier to a loyalty program.
 *
 * @implements ProcessorInterface<LoyaltyTierResource, LoyaltyTierResource>
 */
final readonly class AddLoyaltyTierProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoyaltyTierResource
    {
        if (!$data instanceof LoyaltyTierResource) {
            throw new BadRequestHttpException('Expected LoyaltyTierResource');
        }

        // Extract program ID from URI
        if (!isset($uriVariables['programId'])) {
            throw new BadRequestHttpException('Program ID is required');
        }

        $programId = $uriVariables['programId'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Validate required fields
        if (null === $data->name || null === $data->threshold || null === $data->discountPercentage || null === $data->sortOrder) {
            throw new BadRequestHttpException('Name, threshold, discount percentage, and sort order are required');
        }

        // Parse free shipping minimum order
        $freeShippingMinOrder = null;
        $freeShippingCurrency = null;
        if (null !== $data->freeShippingMinOrder && isset($data->freeShippingMinOrder['amount'], $data->freeShippingMinOrder['currency'])) {
            $freeShippingMinOrder = (int) $data->freeShippingMinOrder['amount'];
            $freeShippingCurrency = (string) $data->freeShippingMinOrder['currency'];
        }

        // Create command
        $command = new AddLoyaltyTierCommand(
            programId: $programId,
            tenantId: $tenantId->toString(),
            name: $data->name,
            threshold: $data->threshold,
            discountPercentage: $data->discountPercentage,
            freeShippingMinOrder: $freeShippingMinOrder,
            freeShippingCurrency: $freeShippingCurrency,
            sortOrder: $data->sortOrder
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

        // Return the input data as resource (tier added successfully)
        $data->programId = $programId;

        return $data;
    }
}
