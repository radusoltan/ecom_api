<?php

declare(strict_types=1);

namespace App\Order\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Application\DTO\OrderDTO;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Service\FraudCheckService;
use App\Order\Infrastructure\Security\GuestOrderTokenService;
use App\Order\Presentation\Api\Resource\OrderResource;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class PlaceOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private FraudCheckService $fraudCheckService,
        private GuestOrderTokenService $guestOrderTokenService,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderResource
    {
        if (!$data instanceof OrderResource) {
            throw new \InvalidArgumentException('Expected OrderResource');
        }

        if (!$data->customerEmail || !$data->lines || !$data->shippingAddress || !$data->billingAddress) {
            throw new \InvalidArgumentException('Customer email, lines, shipping address, and billing address are required');
        }

        $orderId = OrderId::generate()->toString();
        $tenantId = $data->tenantId ?? throw new \InvalidArgumentException('Tenant ID is required');

        // Perform fraud check
        $request = $this->requestStack->getCurrentRequest();
        $clientIp = $request?->getClientIp() ?? 'unknown';

        $fraudCheck = $this->fraudCheckService->calculateFraudScore(
            $clientIp,
            $data->customerEmail,
            $tenantId
        );

        // Log fraud score for monitoring
        $this->logger->info('Order fraud check performed', [
            'order_id' => $orderId,
            'customer_email' => $data->customerEmail,
            'fraud_score' => $fraudCheck['score'],
            'risk_level' => $fraudCheck['risk_level'],
            'ip' => $clientIp,
        ]);

        // For high-risk orders, we log but still allow (can be changed to block)
        if ('high' === $fraudCheck['risk_level']) {
            $this->logger->warning('High-risk order detected', [
                'order_id' => $orderId,
                'fraud_score' => $fraudCheck['score'],
                'reasons' => $fraudCheck['reasons'],
                'customer_email' => $data->customerEmail,
                'ip' => $clientIp,
            ]);
            // In production, you might want to:
            // throw new InvalidArgumentException('Order flagged for manual review');
        }

        $command = new PlaceOrderCommand(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: $data->customerEmail,
            lines: $data->lines,
            shippingAddress: $data->shippingAddress,
            billingAddress: $data->billingAddress,
            couponCode: $data->couponCode ?? null,
            promotionContext: $data->promotionContext ?? []
        );

        $envelope = $this->commandBus->dispatch($command);

        // Record successful order placement for fraud tracking
        $this->fraudCheckService->recordOrderPlaced($clientIp, $data->customerEmail, $tenantId);

        // Use the command handler's return value directly (avoids transaction visibility issues)
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for PlaceOrderCommand');
        }

        $order = $handledStamp->getResult();

        if (!$order instanceof Order) {
            throw new \RuntimeException('Order not found after creation');
        }

        $orderDTO = OrderDTO::fromDomain($order);

        $resource = new OrderResource();
        $resource->id = $orderDTO->id;
        $resource->tenantId = $orderDTO->tenantId;
        $resource->customerEmail = $orderDTO->customerEmail;
        $resource->status = $orderDTO->status;
        $resource->lines = $orderDTO->lines;
        $resource->shippingAddress = $orderDTO->shippingAddress;
        $resource->billingAddress = $orderDTO->billingAddress;
        $resource->totalAmount = $orderDTO->totalAmount;
        $resource->totalCurrency = $orderDTO->totalCurrency;
        $resource->appliedPromotions = $orderDTO->appliedPromotions ?? [];
        $resource->discountAmount = $orderDTO->discountAmount ?? null;
        $resource->discountCurrency = $orderDTO->discountCurrency ?? null;
        $resource->couponCode = $orderDTO->couponCode ?? null;
        $resource->createdAt = $orderDTO->createdAt;
        $resource->updatedAt = $orderDTO->updatedAt;
        $resource->guestToken = $this->guestOrderTokenService->generateToken($orderId);

        return $resource;
    }
}
