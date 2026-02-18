<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Application\Command\RemoveItemFromCart;
use App\Cart\Application\Query\GetCart;
use App\Cart\Domain\Exception\CartItemNotFoundException;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Presentation\Api\Resource\CartResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<CartResource, CartResource>
 */
final readonly class RemoveItemFromCartProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartResource
    {
        $itemId = $uriVariables['itemId'] ?? throw new BadRequestHttpException('Cart item ID is required');

        // Get cart ID from context or from X-Cart-ID header
        $cartId = $context['cart_id'] ?? null;
        if (null === $cartId) {
            $request = $this->requestStack->getCurrentRequest();
            $cartId = $request?->headers->get('X-Cart-ID') ?? throw new BadRequestHttpException('Cart ID is required (provide via X-Cart-ID header or context)');
        }

        $command = new RemoveItemFromCart(
            cartId: $cartId,
            cartItemId: $itemId
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Unwrap the exception to check the actual cause
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof CartItemNotFoundException) {
                    throw new NotFoundHttpException($nested->getMessage(), $nested);
                }
                if ($nested instanceof CartNotFoundException) {
                    throw new NotFoundHttpException($nested->getMessage(), $nested);
                }
            }
            throw $e;
        }

        // Retrieve the updated cart
        $envelope = $this->queryBus->dispatch(new GetCart($cartId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for query');
        }

        $cartDTO = $handledStamp->getResult();

        if (null === $cartDTO) {
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
