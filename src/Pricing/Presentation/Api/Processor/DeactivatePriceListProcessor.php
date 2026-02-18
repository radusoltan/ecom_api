<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\DeactivatePriceList\DeactivatePriceListCommand;
use App\Pricing\Application\Query\GetPriceListById\GetPriceListByIdQuery;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Presentation\Api\Resource\PriceListResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class DeactivatePriceListProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PriceListResource
    {
        if (!$data instanceof PriceListResource) {
            throw new \InvalidArgumentException('Expected PriceListResource');
        }

        $priceListId = PriceListId::fromString($uriVariables['id'] ?? throw new \InvalidArgumentException('Price list ID is required'));
        $tenantId = TenantId::fromString($data->tenantId ?? throw new \InvalidArgumentException('Tenant ID is required'));

        $command = new DeactivatePriceListCommand($priceListId);
        $this->commandBus->dispatch($command);

        // Retrieve updated price list
        $envelope = $this->queryBus->dispatch(
            new GetPriceListByIdQuery($priceListId)
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $priceListDTO = $handledStamp->getResult();

        if (null === $priceListDTO) {
            throw new \RuntimeException('PriceList not found');
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
