<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\UpdateLoyaltyProgram\UpdateLoyaltyProgramCommand;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\Query\GetLoyaltyProgramById\GetLoyaltyProgramByIdQuery;
use App\Customer\Presentation\Api\Resource\LoyaltyProgramResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Update Loyalty Program Processor.
 *
 * Processes updates to an existing loyalty program.
 *
 * @implements ProcessorInterface<LoyaltyProgramResource, LoyaltyProgramResource>
 */
final readonly class UpdateLoyaltyProgramProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoyaltyProgramResource
    {
        if (!$data instanceof LoyaltyProgramResource) {
            throw new BadRequestHttpException('Expected LoyaltyProgramResource');
        }

        // Extract program ID from URI
        if (!isset($uriVariables['id'])) {
            throw new BadRequestHttpException('Program ID is required');
        }

        $programId = $uriVariables['id'];

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Validate required fields
        if (null === $data->name || '' === $data->name) {
            throw new BadRequestHttpException('Name is required');
        }
        if (null === $data->earningRate) {
            throw new BadRequestHttpException('Earning rate is required');
        }
        if (null === $data->minOrderValue || !isset($data->minOrderValue['amount'], $data->minOrderValue['currency'])) {
            throw new BadRequestHttpException('Min order value is required with amount and currency');
        }

        // Create command
        $command = new UpdateLoyaltyProgramCommand(
            programId: $programId,
            tenantId: $tenantId->toString(),
            name: $data->name,
            description: $data->description,
            earningRate: $data->earningRate,
            minOrderValue: (int) $data->minOrderValue['amount'],
            minOrderCurrency: (string) $data->minOrderValue['currency'],
            validityDays: $data->validityDays
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

        // Retrieve the updated program
        $envelope = $this->queryBus->dispatch(
            new GetLoyaltyProgramByIdQuery($programId, $tenantId->toString())
        );

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        /** @var LoyaltyProgramDTO|null $programDTO */
        $programDTO = $handledStamp->getResult();

        if (null === $programDTO) {
            throw new \RuntimeException('Loyalty program not found after update');
        }

        // Map DTO to resource
        return $this->mapDtoToResource($programDTO);
    }

    private function mapDtoToResource(LoyaltyProgramDTO $dto): LoyaltyProgramResource
    {
        $resource = new LoyaltyProgramResource();
        $resource->id = $dto->id;
        $resource->tenantId = $dto->tenantId;
        $resource->name = $dto->name;
        $resource->description = $dto->description;
        $resource->earningRate = $dto->earningRate;
        $resource->minOrderValue = $dto->minOrderValue;
        $resource->redemptionRule = $dto->redemptionRule;
        $resource->validityDays = $dto->validityDays;
        $resource->isActive = $dto->isActive;
        $resource->tiers = array_map(fn ($tier) => [
            'id' => $tier->id,
            'name' => $tier->name,
            'threshold' => $tier->threshold,
            'discountPercentage' => $tier->discountPercentage,
            'freeShippingMinOrder' => $tier->freeShippingMinOrder,
            'sortOrder' => $tier->sortOrder,
        ], $dto->tiers);
        $resource->createdAt = $dto->createdAt;
        $resource->updatedAt = $dto->updatedAt;

        return $resource;
    }
}
