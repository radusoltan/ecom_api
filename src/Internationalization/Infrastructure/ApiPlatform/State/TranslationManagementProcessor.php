<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Internationalization\Application\Command\CreateTranslation;
use App\Internationalization\Application\Command\DeleteTranslation;
use App\Internationalization\Application\Command\UpdateTranslation;
use App\Internationalization\Infrastructure\ApiPlatform\Resource\TranslationManagementResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Translation Management Processor.
 *
 * State processor for write operations (CREATE, UPDATE, DELETE).
 */
final readonly class TranslationManagementProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TranslationManagementResource
    {
        $tenantId = $context['tenant_id'] ?? throw new \RuntimeException('Tenant ID not found');

        if ($operation instanceof DeleteOperationInterface) {
            $command = new DeleteTranslation(
                id: (int) $uriVariables['id'],
                tenantId: $tenantId,
            );

            $this->commandBus->dispatch($command);

            return null;
        }

        // CREATE
        if (null === $data->id) {
            $command = new CreateTranslation(
                tenantId: $tenantId,
                locale: $data->locale,
                domain: $data->domain,
                key: $data->key,
                value: $data->value,
            );

            $envelope = $this->commandBus->dispatch($command);
            $entry = $envelope->last(HandledStamp::class)?->getResult();

            return new TranslationManagementResource(
                id: $entry->id,
                tenantId: $entry->tenantId->toString(),
                locale: $entry->locale->value(),
                domain: $entry->domain->value(),
                key: $entry->key->value(),
                value: $entry->value->value(),
            );
        }

        // UPDATE
        $command = new UpdateTranslation(
            id: $data->id,
            tenantId: $tenantId,
            value: $data->value,
        );

        $envelope = $this->commandBus->dispatch($command);
        $entry = $envelope->last(HandledStamp::class)?->getResult();

        return new TranslationManagementResource(
            id: $entry->id,
            tenantId: $entry->tenantId->toString(),
            locale: $entry->locale->value(),
            domain: $entry->domain->value(),
            key: $entry->key->value(),
            value: $entry->value->value(),
        );
    }
}
