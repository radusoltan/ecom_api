<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pricing\Application\Command\CreateFlashSale\CreateFlashSaleCommand;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\FlashSaleEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<FlashSaleEntity>
 */
final readonly class CreateFlashSaleProcessor implements ProcessorInterface
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

        $command = new CreateFlashSaleCommand(
            flashSaleId: FlashSaleId::generate(),
            tenantId: TenantId::fromString($tenantIdHeader),
            name: $data->getName(),
            productIds: $data->getProductIds(),
            discountType: $data->getDiscountType(),
            discountValue: $data->getDiscountValue(),
            startTime: $data->getStartTime()->format('c'),
            endTime: $data->getEndTime()->format('c')
        );

        $this->messageBus->dispatch($command);

        return $data;
    }
}
