<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Order\Application\Command\UpdateFulfillmentStatus;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to mark fulfillment as failed (PATCH /api/fulfillments/{id}/mark-failed)
 *
 * Expected JSON body:
 * {
 *   "reason": "Package damaged during transit"
 * }
 */
final readonly class MarkFailedProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $uriVariables['id'] ?? null;

        if ($id === null) {
            throw new BadRequestHttpException('Fulfillment ID is required');
        }

        $request = $this->requestStack->getCurrentRequest();
        $payload = json_decode($request?->getContent() ?? '{}', true);

        $reason = $payload['reason'] ?? null;

        if ($reason === null || trim($reason) === '') {
            throw new BadRequestHttpException('Failure reason is required');
        }

        $tenantIdString = $context['tenant_id'] ?? null;
        if ($tenantIdString === null) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $fulfillmentId = FulfillmentId::fromString($id);

        $command = new UpdateFulfillmentStatus(
            fulfillmentId: $fulfillmentId,
            newStatus: FulfillmentStatus::failed(),
            tenantId: $tenantId,
            failureReason: $reason,
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
