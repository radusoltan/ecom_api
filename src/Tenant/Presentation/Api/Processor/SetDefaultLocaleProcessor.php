<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Tenant\Application\Command\SetDefaultLocaleCommand;
use App\Tenant\Application\Query\GetTenantByIdQuery;
use App\Tenant\Presentation\Api\TenantResource;
use App\Tenant\Presentation\Api\Transformer\TenantResourceTransformer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @implements ProcessorInterface<TenantResource, TenantResource>
 */
final readonly class SetDefaultLocaleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    /**
     * @param TenantResource $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): TenantResource {
        $tenantId = $uriVariables['id'] ?? null;

        if (null === $tenantId) {
            throw new \InvalidArgumentException('Tenant ID is required');
        }

        if (null === $data->defaultLocale) {
            throw new \InvalidArgumentException('Default locale is required');
        }

        // Dispatch command to set default locale
        $command = new SetDefaultLocaleCommand(
            tenantId: $tenantId,
            localeCode: $data->defaultLocale
        );
        $this->commandBus->dispatch($command);

        // Query to get the updated tenant
        $query = new GetTenantByIdQuery(tenantId: $tenantId);
        $envelope = $this->queryBus->dispatch($query);
        $stamp = $envelope->last(HandledStamp::class);

        if (!$stamp instanceof StampInterface) {
            throw new \RuntimeException('No handler found for query');
        }

        $tenantDTO = $stamp->getResult();

        if (null === $tenantDTO) {
            throw new \RuntimeException('Tenant not found after locale update');
        }

        return TenantResourceTransformer::fromDTO($tenantDTO);
    }
}
