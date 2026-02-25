<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Application\Service\CartToOrderConverter;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Presentation\Api\Resource\CheckoutResource;
use App\Order\Application\DTO\OrderDTO;
use App\Order\Domain\Exception\CheckoutValidationException;
use App\Order\Domain\Model\Order;
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

            // Dispatch command to create order and get result from handler
            $envelope = $this->commandBus->dispatch($placeOrderCommand);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp || !$handledStamp->getResult() instanceof Order) {
                throw new \RuntimeException('Order creation failed');
            }

            /** @var Order $order */
            $order = $handledStamp->getResult();
            $orderDTO = OrderDTO::fromDomain($order);

            // Re-set tenant context (may have been cleared by event handlers during order dispatch)
            $this->tenantContext->setCurrentTenant($tenantId);

            // Mark cart as converted
            $this->cartRepository->markAsConverted($cart->id());

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
                'order_id' => $orderDTO->id,
                'cart_id' => $data->cartId,
                'total_amount' => $orderDTO->totalAmount,
            ]);

            return $response;
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof CheckoutValidationException) {
                    throw new ConflictHttpException(
                        json_encode($nested->toArray(), JSON_THROW_ON_ERROR),
                        $nested,
                    );
                }
                if ($nested instanceof \InvalidArgumentException) {
                    throw new BadRequestHttpException($nested->getMessage(), $nested);
                }
            }
            throw $e;
        } catch (CheckoutValidationException $e) {
            throw new ConflictHttpException(
                json_encode($e->toArray(), JSON_THROW_ON_ERROR),
                $e,
            );
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

}
