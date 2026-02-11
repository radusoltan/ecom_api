<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notifications\Application\Command\RetryNotification\RetryNotification;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Processor to retry a failed notification (POST /api/v1/notifications/{id}/retry).
 *
 * @implements ProcessorInterface<mixed, JsonResponse>
 */
final readonly class RetryNotificationProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $id = $uriVariables['id'] ?? null;

        if (null === $id) {
            throw new BadRequestHttpException('Notification ID is required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $notificationId = NotificationId::fromString($id);

        // Find the notification
        $notification = $this->notificationRepository->findById($notificationId, $tenantId);

        if (null === $notification) {
            throw new NotFoundHttpException(sprintf('Notification with ID "%s" not found', $id));
        }

        // Verify tenant ownership
        if (!$notification->tenantId()->equals($tenantId)) {
            throw new NotFoundHttpException(sprintf('Notification with ID "%s" not found', $id));
        }

        // Check if notification can be retried (must be in failed status)
        if (!$notification->status()->canRetry()) {
            throw new BadRequestHttpException(sprintf(
                'Notification cannot be retried. Current status: %s',
                $notification->status()->value
            ));
        }

        // Dispatch retry command
        $command = new RetryNotification(
            id: $notificationId,
            tenantId: $tenantId,
        );

        $this->commandBus->dispatch($command);

        // Return 202 Accepted with status message
        return new JsonResponse(['status' => 'retry_queued'], Response::HTTP_ACCEPTED);
    }
}
