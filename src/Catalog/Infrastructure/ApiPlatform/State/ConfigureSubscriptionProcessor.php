<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\ConfigureSubscriptionCommand;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Shared\Domain\ValueObject\Money;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConfigureSubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $command = new ConfigureSubscriptionCommand(
            productId: ProductId::fromString($data['productId']),
            interval: SubscriptionInterval::fromString($data['interval']),
            billingCycles: $data['billingCycles'],
            setupFee: Money::fromScalars($data['setupFee']['amount'], $data['setupFee']['currency']),
            trialPeriodEnd: isset($data['trialPeriodEnd']) ? new \DateTimeImmutable($data['trialPeriodEnd']) : null
        );

        $this->commandBus->dispatch($command);

        return $data;
    }
}
