<?php

declare(strict_types=1);

namespace App\Payment\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payment\Application\Command\CapturePayment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CapturePaymentProcessor implements ProcessorInterface
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
        $command = new CapturePayment(
            id: PaymentId::fromString($data->getId())
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
