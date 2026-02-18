<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Application\Command\RedeemPoints\RedeemPointsCommand;
use App\Customer\Application\DTO\RedeemPointsResult;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Presentation\Api\Resource\CustomerLoyaltyResource;
use App\Shared\Application\Service\TenantContextInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Redeem Points Processor.
 *
 * Processes loyalty point redemption requests.
 *
 * @implements ProcessorInterface<CustomerLoyaltyResource, CustomerLoyaltyResource>
 */
final readonly class RedeemPointsProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContextInterface $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerLoyaltyResource
    {
        if (!$data instanceof CustomerLoyaltyResource) {
            throw new BadRequestHttpException('Expected CustomerLoyaltyResource');
        }

        // Extract customer ID from URI
        if (!isset($uriVariables['customerId'])) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        $customerId = CustomerId::fromString($uriVariables['customerId']);

        // Get tenant from context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Validate required fields
        if (null === $data->pointsToRedeem) {
            throw new BadRequestHttpException('Points to redeem is required');
        }

        if ($data->pointsToRedeem <= 0) {
            throw new BadRequestHttpException('Points to redeem must be positive');
        }

        // Create command
        $command = new RedeemPointsCommand(
            customerId: $customerId->toString(),
            tenantId: $tenantId->toString(),
            pointsToRedeem: $data->pointsToRedeem,
            orderId: $data->orderId
        );

        try {
            $envelope = $this->commandBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('No handler found for command');
            }

            /** @var RedeemPointsResult|null $result */
            $result = $handledStamp->getResult();

            if (null === $result) {
                throw new \RuntimeException('No result returned from redemption');
            }

            // Map result to resource
            return $this->mapResultToResource($result, $customerId->toString());
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof HttpExceptionInterface) {
                throw $previous;
            }

            throw $exception;
        }
    }

    private function mapResultToResource(RedeemPointsResult $result, string $customerId): CustomerLoyaltyResource
    {
        $resource = new CustomerLoyaltyResource();
        $resource->customerId = $customerId;
        $resource->pointsRedeemed = $result->pointsRedeemed;
        $resource->discountAmount = $result->discountAmount;
        $resource->discountCurrency = $result->discountCurrency;
        $resource->remainingBalance = $result->remainingBalance;

        return $resource;
    }
}
