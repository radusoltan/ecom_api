<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Application\Command\UpdateCartQuantity;
use App\Cart\Application\Query\GetCart;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Presentation\Api\Resource\CartResource;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<CartResource, CartResource>
 */
final readonly class UpdateCartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartResource
    {
        if (!$data instanceof CartResource) {
            throw new InvalidArgumentException('Expected CartResource');
        }

        $itemId = $uriVariables['itemId'] ?? throw new InvalidArgumentException('Cart item ID is required');

        if (!$data->newQuantity) {
            throw new InvalidArgumentException('New quantity is required');
        }

        $tenantId = $data->tenantId ?? throw new InvalidArgumentException('Tenant ID is required');

        // Get cart ID from context or from X-Cart-ID header
        $cartId = $context['cart_id'] ?? null;
        if ($cartId === null) {
            $request = $this->requestStack->getCurrentRequest();
            $cartId = $request?->headers->get('X-Cart-ID') ?? throw new InvalidArgumentException('Cart ID is required (provide via X-Cart-ID header or context)');
        }

        $command = new UpdateCartQuantity(
            cartId: $cartId,
            cartItemId: $itemId,
            newQuantity: $data->newQuantity
        );

        $this->commandBus->dispatch($command);

        // Retrieve the updated cart
        $envelope = $this->queryBus->dispatch(new GetCart($cartId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new RuntimeException('No handler found for query');
        }

        $cartDTO = $handledStamp->getResult();

        if ($cartDTO === null) {
            throw CartNotFoundException::withId($cartId);
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
