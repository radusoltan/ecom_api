<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\CancelFlashSale\CancelFlashSaleCommand;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<FlashSaleEntity>
 */
final readonly class CancelFlashSaleProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlashSaleEntity
    {
        assert($data instanceof FlashSaleEntity);

        $request = $this->requestStack->getCurrentRequest();
        $tenantIdHeader = $request?->headers->get('X-Tenant-ID');

        if (null === $tenantIdHeader) {
            throw new BadRequestHttpException('X-Tenant-ID header is required');
        }

        $command = new CancelFlashSaleCommand(
            flashSaleId: FlashSaleId::fromString($data->getId()),
            tenantId: TenantId::fromString($tenantIdHeader)
        );

        $this->messageBus->dispatch($command);

        return $data;
    }
}
