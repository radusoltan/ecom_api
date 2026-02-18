<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Tenant\Application\Command\UpdateTenantCommand;
use App\Tenant\Application\Query\GetTenantByIdQuery;
use App\Tenant\Presentation\Api\TenantResource;
use App\Tenant\Presentation\Api\Transformer\TenantResourceTransformer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<TenantResource, TenantResource>
 */
final readonly class UpdateTenantProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): TenantResource {
        if (!$data instanceof TenantResource) {
            throw new \InvalidArgumentException('Expected TenantResource');
        }

        if (!isset($uriVariables['id'])) {
            throw new \InvalidArgumentException('Tenant ID is required in URI');
        }

        if (null === $data->name || null === $data->ownerEmail) {
            throw new \InvalidArgumentException('Name and ownerEmail are required');
        }

        $tenantId = (string) $uriVariables['id'];

        // Dispatch command to update tenant
        $command = new UpdateTenantCommand(
            id: $tenantId,
            name: $data->name,
            ownerEmail: $data->ownerEmail
        );

        $this->commandBus->dispatch($command);

        // Query to get the updated tenant
        $query = new GetTenantByIdQuery(tenantId: $tenantId);
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $tenantDTO = $stamp->getResult();

        if (null === $tenantDTO) {
            throw new \RuntimeException('Tenant not found after update');
        }

        return TenantResourceTransformer::fromDTO($tenantDTO);
    }
}
