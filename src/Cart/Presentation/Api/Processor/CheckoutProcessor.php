<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Application\Service\CartToOrderConverter;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Presentation\Api\Resource\CheckoutResource;
use App\Order\Application\Query\GetOrderByIdQuery;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * CheckoutProcessor - Processes cart checkout to create an order.
 *
 * Flow:
 * 1. Validate input (cartId, customerEmail, addresses)
 * 2. Load cart from repository
 * 3. Convert cart to PlaceOrderCommand using CartToOrderConverter
 * 4. Dispatch command to create order
 * 5. Mark cart as converted
 * 6. Return order details
 *
 * @implements ProcessorInterface<CheckoutResource, CheckoutResource>
 */
final readonly class CheckoutProcessor implements ProcessorInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartToOrderConverter $cartToOrderConverter,
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private TenantContext $tenantContext,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws ConflictHttpException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CheckoutResource
    {
        if (!$data instanceof CheckoutResource) {
            throw new BadRequestHttpException('Expected CheckoutResource');
        }

        // Validate required fields
        $this->validateInput($data);

        // Get tenant context
        $tenantId = $this->tenantContext->getCurrentTenantId();
        if (null === $tenantId) {
            throw new BadRequestHttpException('Tenant context is required');
        }

        // Load cart
        if (null === $data->cartId) {
            throw new BadRequestHttpException('Cart ID is required');
        }

        $cart = $this->cartRepository->findById(CartId::fromString($data->cartId));
        if (null === $cart) {
            throw new NotFoundHttpException(sprintf('Cart with ID "%s" not found', $data->cartId));
        }

        // Verify cart belongs to tenant
        if (!$cart->tenantId()->equals($tenantId)) {
            throw new NotFoundHttpException(sprintf('Cart with ID "%s" not found', $data->cartId));
        }

        // Verify cart is active
        if (!$cart->isActive()) {
            throw new ConflictHttpException('Cart is not active (may already be converted or expired)');
        }

        // Verify cart has items
        if ($cart->isEmpty()) {
            throw new BadRequestHttpException('Cannot checkout empty cart');
        }

        // Validate required fields for conversion
        if (null === $data->customerEmail) {
            throw new BadRequestHttpException('Customer email is required');
        }

        if (null === $data->billingAddress) {
            throw new BadRequestHttpException('Billing address is required');
        }

        // Handle useBillingAsShipping
        $shippingAddress = $data->useBillingAsShipping ? $data->billingAddress : $data->shippingAddress;

        if (null === $shippingAddress) {
            throw new BadRequestHttpException('Shipping address is required');
        }

        try {
            // Convert cart to PlaceOrderCommand
            $placeOrderCommand = $this->cartToOrderConverter->convert(
                cart: $cart,
                customerEmail: $data->customerEmail,
                shippingAddress: $shippingAddress,
                billingAddress: $data->billingAddress,
                couponCode: $data->couponCode
            );

            // Log checkout attempt
            $this->logger->info('Processing checkout', [
                'cart_id' => $data->cartId,
                'order_id' => $placeOrderCommand->orderId,
                'customer_email' => $data->customerEmail,
                'item_count' => $cart->getItemCount(),
            ]);

            // Dispatch command to create order
            $this->commandBus->dispatch($placeOrderCommand);

            // Mark cart as converted
            $cart->markAsConverted();
            $this->cartRepository->save($cart);

            // Retrieve the created order
            $orderId = $placeOrderCommand->orderId;
            $orderDTO = $this->retrieveOrder($orderId, $tenantId->toString());

            // Build response
            $response = new CheckoutResource();
            $response->orderId = $orderDTO->id;
            $response->tenantId = $orderDTO->tenantId;
            $response->customerEmail = $orderDTO->customerEmail;
            $response->status = $orderDTO->status;
            $response->totalAmount = $orderDTO->totalAmount;
            $response->totalCurrency = $orderDTO->totalCurrency;
            $response->itemCount = count($orderDTO->lines);
            $response->lines = $orderDTO->lines;
            $response->shippingAddress = $orderDTO->shippingAddress;
            $response->billingAddress = $orderDTO->billingAddress;
            $response->createdAt = $orderDTO->createdAt;
            $response->appliedPromotions = $orderDTO->appliedPromotions ?? [];
            $response->discountAmount = $orderDTO->discountAmount ?? null;
            $response->discountCurrency = $orderDTO->discountCurrency ?? null;
            $response->couponCode = $orderDTO->couponCode ?? null;

            $this->logger->info('Checkout completed successfully', [
                'order_id' => $orderId,
                'cart_id' => $data->cartId,
                'total_amount' => $orderDTO->totalAmount,
            ]);

            return $response;
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof \InvalidArgumentException) {
                    throw new BadRequestHttpException($nested->getMessage(), $nested);
                }
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }

    private function validateInput(CheckoutResource $data): void
    {
        if (null === $data->cartId || '' === $data->cartId) {
            throw new BadRequestHttpException('Cart ID is required');
        }

        if (null === $data->customerEmail || '' === trim($data->customerEmail)) {
            throw new BadRequestHttpException('Customer email is required');
        }

        if (!filter_var($data->customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Invalid customer email format');
        }

        if (null === $data->billingAddress || empty($data->billingAddress)) {
            throw new BadRequestHttpException('Billing address is required');
        }

        // If not using billing as shipping, shipping address is required
        if (!$data->useBillingAsShipping && (null === $data->shippingAddress || empty($data->shippingAddress))) {
            throw new BadRequestHttpException('Shipping address is required (or set useBillingAsShipping to true)');
        }
    }

    /**
     * @return object{id: string, tenantId: string, customerEmail: string, status: string, lines: array<int, mixed>, shippingAddress: array<string, mixed>, billingAddress: array<string, mixed>, totalAmount: int, totalCurrency: string, appliedPromotions: ?array<int, mixed>, discountAmount: ?int, discountCurrency: ?string, couponCode: ?string, createdAt: string, updatedAt: string}
     */
    private function retrieveOrder(string $orderId, string $tenantId): object
    {
        $envelope = $this->queryBus->dispatch(new GetOrderByIdQuery($orderId, $tenantId));
        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('No handler found for GetOrderByIdQuery');
        }

        $orderDTO = $handledStamp->getResult();

        if (null === $orderDTO) {
            throw new \RuntimeException('Order not found after creation');
        }

        return $orderDTO;
    }
}
