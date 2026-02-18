<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Tenant\Application\Command\DeleteTenantCommand;
use App\Tenant\Presentation\Api\TenantResource;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<TenantResource, void>
 */
final readonly class DeleteTenantProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): void {
        if (!isset($uriVariables['id'])) {
            throw new \InvalidArgumentException('Tenant ID is required in URI');
        }

        $tenantId = (string) $uriVariables['id'];

        // Dispatch command to delete tenant
        $command = new DeleteTenantCommand(id: $tenantId);
        $this->commandBus->dispatch($command);
    }
}
