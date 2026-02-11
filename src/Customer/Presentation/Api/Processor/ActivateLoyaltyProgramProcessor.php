<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\ActivateLoyaltyProgram\ActivateLoyaltyProgramCommand;
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
 * Activate Loyalty Program Processor.
 *
 * Processes the activation of a loyalty program.
 *
 * @implements ProcessorInterface<LoyaltyProgramResource, LoyaltyProgramResource>
 */
final readonly class ActivateLoyaltyProgramProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContextInterface $tenantContext
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoyaltyProgramResource
    {
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

        // Create command
        $command = new ActivateLoyaltyProgramCommand(
            programId: $programId,
            tenantId: $tenantId->toString()
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

        // Retrieve the activated program
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
            throw new \RuntimeException('Loyalty program not found after activation');
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
