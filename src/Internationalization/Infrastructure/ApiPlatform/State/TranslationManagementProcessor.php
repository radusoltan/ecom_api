<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Internationalization\Application\Command\CreateTranslation;
use App\Internationalization\Application\Command\DeleteTranslation;
use App\Internationalization\Application\Command\UpdateTranslation;
use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Infrastructure\ApiPlatform\Resource\TranslationManagementResource;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Translation Management Processor.
 *
 * State processor for write operations (CREATE, UPDATE, DELETE).
 * Works with Translation aggregate root (UUID identity).
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
                id: (string) $uriVariables['id'],
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
                parameters: $data->parameters ?? [],
            );

            $envelope = $this->commandBus->dispatch($command);
            /** @var Translation $translation */
            $translation = $envelope->last(HandledStamp::class)?->getResult();

            return $this->toResource($translation);
        }

        // UPDATE
        $command = new UpdateTranslation(
            id: (string) $data->id,
            tenantId: $tenantId,
            value: $data->value,
            parameters: $data->parameters ?? [],
        );

        $envelope = $this->commandBus->dispatch($command);
        /** @var Translation $translation */
        $translation = $envelope->last(HandledStamp::class)?->getResult();

        return $this->toResource($translation);
    }

    private function toResource(Translation $translation): TranslationManagementResource
    {
        return new TranslationManagementResource(
            id: $translation->id()->toString(),
            tenantId: $translation->tenantId()->toString(),
            locale: $translation->locale()->value(),
            domain: $translation->domain()->value(),
            key: $translation->key()->value(),
            value: $translation->value()->value(),
            createdAt: $translation->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $translation->updatedAt()->format(\DateTimeInterface::ATOM),
            parameters: $translation->parameters(),
        );
    }
}
