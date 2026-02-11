<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\RemoveLoyaltyTier\RemoveLoyaltyTierCommand;
use App\Customer\Presentation\Api\Resource\LoyaltyTierResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Remove Loyalty Tier Processor.
 *
 * Processes the removal of a tier from a loyalty program.
 *
 * @implements ProcessorInterface<LoyaltyTierResource, void>
 */
final readonly class RemoveLoyaltyTierProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // Extract IDs from URI
        if (!isset($uriVariables['programId']) || !isset($uriVariables['id'])) {
            throw new BadRequestHttpException('Program ID and tier ID are required');
        }

        $programId = $uriVariables['programId'];
        $tierId = $uriVariables['id'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Create command
        $command = new RemoveLoyaltyTierCommand(
            programId: $programId,
            tenantId: $tenantId->toString(),
            tierId: $tierId
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
    }
}
