<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Application\Command\AddItemToCart;
use App\Cart\Application\Command\CreateCart;
use App\Cart\Application\Query\GetCart;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Presentation\Api\Resource\CartResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<CartResource, CartResource>
 */
final readonly class AddItemToCartProcessor implements ProcessorInterface
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
            throw new \InvalidArgumentException('Expected CartResource');
        }

        if (!$data->productId || !$data->quantity) {
            throw new \InvalidArgumentException('Product ID and quantity are required');
        }

        $tenantId = $data->tenantId ?? throw new \InvalidArgumentException('Tenant ID is required');

        // Get or create cart
        $cartId = $this->getOrCreateCart($tenantId, $data->customerId, $context);

        // Add item to cart
        $command = new AddItemToCart(
            cartId: $cartId,
            productId: $data->productId,
            variantId: $data->variantId,
            quantity: $data->quantity,
            unitPriceAmount: $data->unitPriceAmount,
            unitPriceCurrency: $data->unitPriceCurrency
        );

        $this->commandBus->dispatch($command);

        // Retrieve the updated cart
        return $this->retrieveCart($cartId);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getOrCreateCart(string $tenantId, ?string $customerId, array $context): string
    {
        // Try to get existing cart ID from context (could be from session)
        $existingCartId = $context['cart_id'] ?? null;

        if (null !== $existingCartId) {
            // Verify cart exists
            try {
                $this->retrieveCart($existingCartId);

                return $existingCartId;
            } catch (CartNotFoundException) {
                // Cart doesn't exist, create a new one
            }
        }

        // Create new cart
        $cartId = CartId::generate()->toString();
        $sessionId = $this->getSessionId();

        $command = new CreateCart(
            cartId: $cartId,
            tenantId: $tenantId,
            customerId: $customerId,
            sessionId: $sessionId
        );

        $this->commandBus->dispatch($command);

        return $cartId;
    }

    private function getSessionId(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();

        if (null === $session) {
            // Generate new session ID
            return SessionId::generate()->toString();
        }

        if (!$session->has('cart_session_id')) {
            $session->set('cart_session_id', SessionId::generate()->toString());
        }

        return $session->get('cart_session_id');
    }

    private function retrieveCart(string $cartId): CartResource
    {
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
