<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Cart\Application\Query\GetCart;
use App\Cart\Presentation\Api\Resource\CartResource;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProviderInterface<CartResource>
 */
final readonly class GetCartProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CartResource
    {
        // Get cart ID from context or from X-Cart-ID header
        $cartId = $context['cart_id'] ?? null;
        if ($cartId === null) {
            $request = $this->requestStack->getCurrentRequest();
            $cartId = $request?->headers->get('X-Cart-ID') ?? throw new InvalidArgumentException('Cart ID is required (provide via X-Cart-ID header or context)');
        }

        $envelope = $this->queryBus->dispatch(new GetCart($cartId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            return null;
        }

        $cartDTO = $handledStamp->getResult();

        if ($cartDTO === null) {
            return null;
        }

        $resource = new CartResource();
        $resource->id = $cartDTO->id;
        $resource->tenantId = $cartDTO->tenantId;
        $resource->customerId = $cartDTO->customerId;
        $resource->sessionId = $cartDTO->sessionId;
        $resource->status = $cartDTO->status;
        $resource->items = $cartDTO->items;
        $resource->totalAmount = $cartDTO->totalAmount;
        $resource->totalCurrency = $cartDTO->totalCurrency;
        $resource->itemCount = $cartDTO->itemCount;
        $resource->createdAt = $cartDTO->createdAt;
        $resource->updatedAt = $cartDTO->updatedAt;

        return $resource;
    }
}
