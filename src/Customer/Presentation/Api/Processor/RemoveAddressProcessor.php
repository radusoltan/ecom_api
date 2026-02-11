<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\RemoveAddress\RemoveAddressCommand;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Remove Address Processor.
 *
 * Processes the soft-deletion of a customer address.
 */
/**
 * @implements ProcessorInterface<null, void>
 */
final readonly class RemoveAddressProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // Extract IDs from URI
        if (!isset($uriVariables['customerId']) || !isset($uriVariables['id'])) {
            throw new BadRequestHttpException('Customer ID and address ID are required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);
        $addressId = $uriVariables['id'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Create command
        $command = new RemoveAddressCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            addressId: $addressId
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

        // No return value for DELETE (will return 204 No Content)
    }
}
