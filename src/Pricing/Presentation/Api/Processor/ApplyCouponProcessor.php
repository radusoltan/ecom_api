<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Cart\Domain\Model\CartId;
use App\Pricing\Application\Command\ApplyCouponToCart\ApplyCouponToCartCommand;
use App\Pricing\Presentation\Api\Resource\CartPricingResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Apply Coupon Processor.
 *
 * Handles POST /cart/apply-coupon endpoint
 */
final readonly class ApplyCouponProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param CartPricingResource $data
     *
     * @throws \RuntimeException         If required headers or data are missing
     * @throws \InvalidArgumentException If coupon is invalid
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartPricingResource
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \RuntimeException('No active request found');
        }

        // Extract headers
        $cartIdString = $request->headers->get('X-Cart-ID');
        $tenantIdString = $request->headers->get('X-Tenant-ID');

        if (null === $cartIdString || null === $tenantIdString) {
            throw new BadRequestHttpException('X-Cart-ID and X-Tenant-ID headers are required');
        }

        // Extract coupon code from request body
        $requestData = json_decode($request->getContent(), true);

        if (!isset($requestData['coupon_code']) || empty($requestData['coupon_code'])) {
            throw new BadRequestHttpException('coupon_code is required in request body');
        }

        $couponCode = trim($requestData['coupon_code']);

        // Create command
        $command = new ApplyCouponToCartCommand(
            cartId: CartId::fromString($cartIdString),
            tenantId: TenantId::fromString($tenantIdString),
            couponCode: $couponCode
        );

        // Dispatch command
        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof \RuntimeException && str_contains($nested->getMessage(), 'not found')) {
                    throw new NotFoundHttpException($nested->getMessage(), $nested);
                }
                if ($nested instanceof \InvalidArgumentException) {
                    throw new BadRequestHttpException($nested->getMessage(), $nested);
                }
            }
            throw $e;
        }

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('Command was not handled');
        }

        $appliedDiscount = $handledStamp->getResult();

        // Map to resource
        $resource = new CartPricingResource();
        $resource->id = $appliedDiscount->id;

        // Return applied discount details
        $data = $appliedDiscount->toArray();

        // Create response resource with discount data
        $resource->cartId = $cartIdString;
        $resource->items = [$data]; // Temporary - return discount data in items for now

        return $resource;
    }
}
