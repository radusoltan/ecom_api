<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\RefundPayment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RefundPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
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

        $command = new RefundPayment(
            id: PaymentId::fromString($data->getId()),
            tenantId: TenantId::fromString($data->getTenantId()),
            refundAmountInCents: $refundAmount,
            reason: $errorMessage
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
