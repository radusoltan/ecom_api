<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\CancelPayment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CancelPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {
    }

    /**
     * @param PaymentEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PaymentEntity
    {
        $reason = $data->getErrorMessage();

        if ($reason === null || $reason === '') {
            throw new \RuntimeException('Cancellation reason is required');
        }

        $command = new CancelPayment(
            id: PaymentId::fromString($data->getId()),
            tenantId: TenantId::fromString($data->getTenantId()),
            reason: $reason
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
