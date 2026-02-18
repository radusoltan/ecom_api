<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Application\Command\AssignCartToCustomer;
use App\Cart\Application\Command\MergeCarts;
use App\Cart\Application\Query\GetCart;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Presentation\Api\Resource\CartResource;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * AssignCartToCustomerProcessor.
 *
 * Handles POST /cart/assign - Assigns a guest cart to a customer.
 *
 * Logic:
 * 1. If customer has no existing cart -> assign guest cart to customer
 * 2. If customer has existing cart -> merge guest cart items into customer cart
 *
 * @implements ProcessorInterface<CartResource, CartResource>
 */
final readonly class AssignCartToCustomerProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
        private CartRepositoryInterface $cartRepository,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartResource
    {
        if (!$data instanceof CartResource) {
            throw new BadRequestHttpException('Expected CartResource');
        }

        $customerId = $data->customerId ?? throw new BadRequestHttpException('Customer ID is required');

        // Get guest cart ID from header
        $request = $this->requestStack->getCurrentRequest();
        $guestCartId = $request?->headers->get('X-Cart-ID');
        if (null === $guestCartId || '' === $guestCartId) {
            throw new BadRequestHttpException('Guest cart ID is required (X-Cart-ID header)');
        }

        // Get tenant ID
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Verify guest cart exists and belongs to tenant
        $guestCart = $this->cartRepository->findById(CartId::fromString($guestCartId));
        if (null === $guestCart) {
            throw new NotFoundHttpException(sprintf('Cart with ID "%s" not found', $guestCartId));
        }
        if (!$guestCart->tenantId()->equals($tenantId)) {
            throw new NotFoundHttpException(sprintf('Cart with ID "%s" not found', $guestCartId));
        }

        // Check if customer already has a cart
        $existingCustomerCart = $this->cartRepository->findByCustomerId(
            CustomerId::fromString($customerId),
            $tenantId
        );

        try {
            if (null === $existingCustomerCart) {
                // No existing cart - assign guest cart to customer
                $this->commandBus->dispatch(new AssignCartToCustomer(
                    cartId: $guestCartId,
                    customerId: $customerId
                ));

                return $this->retrieveCart($guestCartId);
            }

            // Customer has existing cart - merge guest cart into it
            // Note: The MergeCartsHandler will re-query the cart to ensure data consistency.
            // This intentional double-query prevents stale state issues in concurrent scenarios.
            $this->commandBus->dispatch(new MergeCarts(
                sourceCartId: $guestCartId,
                targetCartId: $existingCustomerCart->id()->toString(),
                deleteSourceAfterMerge: true
            ));

            return $this->retrieveCart($existingCustomerCart->id()->toString());
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof CartNotFoundException) {
                    throw new NotFoundHttpException($nested->getMessage(), $nested);
                }
            }

            throw $e;
        }
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
