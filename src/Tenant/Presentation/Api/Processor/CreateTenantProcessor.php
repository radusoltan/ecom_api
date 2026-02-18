<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Tenant\Application\Command\CreateTenantCommand;
use App\Tenant\Application\Query\GetTenantByOwnerEmailQuery;
use App\Tenant\Presentation\Api\TenantResource;
use App\Tenant\Presentation\Api\Transformer\TenantResourceTransformer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @implements ProcessorInterface<TenantResource, TenantResource>
 */
final readonly class CreateTenantProcessor implements ProcessorInterface
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

        if (null === $data->name || null === $data->ownerEmail) {
            throw new \InvalidArgumentException('Name and ownerEmail are required');
        }

        // Dispatch command to create tenant
        $command = new CreateTenantCommand(
            name: $data->name,
            ownerEmail: $data->ownerEmail
        );

        $this->commandBus->dispatch($command);

        // Query to get the created tenant
        $query = new GetTenantByOwnerEmailQuery(ownerEmail: $data->ownerEmail);
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof StampInterface) {
            throw new \RuntimeException('No handler found for query');
        }

        $tenantDTO = $stamp->getResult();

        if (null === $tenantDTO) {
            throw new \RuntimeException('Tenant not found after creation');
        }

        return TenantResourceTransformer::fromDTO($tenantDTO);
    }
}
