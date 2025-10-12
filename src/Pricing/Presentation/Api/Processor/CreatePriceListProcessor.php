<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\CreatePriceList\CreatePriceListCommand;
use App\Pricing\Application\Query\GetPriceListById\GetPriceListByIdQuery;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Presentation\Api\Resource\PriceListResource;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class CreatePriceListProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PriceListResource
    {
        if (!$data instanceof PriceListResource) {
            throw new InvalidArgumentException('Expected PriceListResource');
        }

        if (!$data->name || !$data->tenantId) {
            throw new InvalidArgumentException('Name and tenant ID are required');
        }

        $priceListId = PriceListId::generate();
        $tenantId = TenantId::fromString($data->tenantId);
        $name = PriceListName::fromString($data->name);
        $priority = $data->priority ?? 100;

        $validFrom = $data->validFrom ? new DateTimeImmutable($data->validFrom) : null;
        $validTo = $data->validTo ? new DateTimeImmutable($data->validTo) : null;

        $command = new CreatePriceListCommand(
            priceListId: $priceListId,
            tenantId: $tenantId,
            name: $name,
            priority: $priority,
            validFrom: $validFrom,
            validTo: $validTo
        );

        $this->commandBus->dispatch($command);

        // Retrieve the created price list
        $envelope = $this->queryBus->dispatch(
            new GetPriceListByIdQuery($priceListId)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new RuntimeException('No handler found for query');
        }

        $priceListDTO = $handledStamp->getResult();

        if ($priceListDTO === null) {
            throw new RuntimeException('PriceList not found after creation');
        }

        $resource = new PriceListResource();
        $resource->id = $priceListDTO->id;
        $resource->tenantId = $priceListDTO->tenantId;
        $resource->name = $priceListDTO->name;
        $resource->priority = $priceListDTO->priority;
        $resource->rules = $priceListDTO->rules;
        $resource->validFrom = $priceListDTO->validFrom;
        $resource->validTo = $priceListDTO->validTo;
        $resource->isActive = $priceListDTO->isActive;
        $resource->createdAt = $priceListDTO->createdAt;
        $resource->updatedAt = $priceListDTO->updatedAt;

        return $resource;
    }
}
