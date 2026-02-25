<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\RefundPayment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RefundPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param PaymentEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentEntity
    {
        $refundAmount = $data->getRefundedAmountInCents();
        $errorMessage = $data->getErrorMessage();

        if ($refundAmount <= 0) {
            throw new \RuntimeException('Refund amount must be greater than 0');
        }

        if (null === $errorMessage || '' === $errorMessage) {
            throw new \RuntimeException('Refund reason is required');
        }

        // Restore entity to DB state before dispatching the command.
        // The deserializer sets refundedAmountInCents from the request body on the
        // managed entity. Without refresh, the handler would load this same entity
        // from the identity map with the deserialized value and double-count.
        $this->entityManager->refresh($data);

        $command = new RefundPayment(
            id: PaymentId::fromString($data->getId()),
            refundAmountInCents: $refundAmount,
            reason: $errorMessage
        );

        $this->commandBus->dispatch($command);

        // Refresh to reflect handler's changes in the response
        $this->entityManager->refresh($data);

        return $data;
    }
}
