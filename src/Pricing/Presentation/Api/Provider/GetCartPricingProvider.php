<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Cart\Domain\Model\CartId;
use App\Pricing\Application\Query\GetCartPricing\GetCartPricingQuery;
use App\Pricing\Presentation\Api\Resource\CartPricingResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Get Cart Pricing Provider.
 *
 * Handles GET /cart/pricing endpoint
 */
final readonly class GetCartPricingProvider implements ProviderInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @throws \RuntimeException If required headers are missing
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CartPricingResource
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \RuntimeException('No active request found');
        }

        // Extract headers
        $cartIdString = $request->headers->get('X-Cart-ID');
        $tenantIdString = $request->headers->get('X-Tenant-ID');
        $couponsParam = $request->query->get('coupons', '');

        if (null === $cartIdString || null === $tenantIdString) {
            throw new BadRequestHttpException('X-Cart-ID and X-Tenant-ID headers are required');
        }

        // Parse coupon codes
        $couponCodes = array_filter(
            array_map('trim', explode(',', $couponsParam)),
            fn ($code) => !empty($code)
        );

        // Create query
        $query = new GetCartPricingQuery(
            cartId: CartId::fromString($cartIdString),
            tenantId: TenantId::fromString($tenantIdString),
            appliedCouponCodes: $couponCodes
        );

        // Dispatch query
        try {
            $envelope = $this->queryBus->dispatch($query);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof \RuntimeException && str_contains($nested->getMessage(), 'not found')) {
                    throw new NotFoundHttpException($nested->getMessage(), $nested);
                }
            }
            throw $e;
        }

        $handledStamp = $envelope->last(HandledStamp::class);

        if (!$handledStamp instanceof HandledStamp) {
            throw new \RuntimeException('Query was not handled');
        }

        $result = $handledStamp->getResult();

        // Map to resource
        $resource = new CartPricingResource();
        $resource->id = $result->cartId;
        $resource->cartId = $result->cartId;
        $resource->items = array_map(fn ($item) => $item->toArray(), $result->items);
        $resource->subtotal = [
            'value' => $result->subtotal->getAmount(),
            'currency' => $result->subtotal->getCurrency()->getCurrencyCode(),
        ];
        $resource->totalDiscounts = [
            'value' => $result->totalDiscounts->getAmount(),
            'currency' => $result->totalDiscounts->getCurrency()->getCurrencyCode(),
        ];
        $resource->grandTotal = [
            'value' => $result->grandTotal->getAmount(),
            'currency' => $result->grandTotal->getCurrency()->getCurrencyCode(),
        ];
        $resource->cartLevelDiscounts = array_map(
            fn ($discount) => $discount->toArray(),
            $result->cartLevelDiscounts
        );
        $resource->totalItemsCount = $result->totalItemsCount;

        return $resource;
    }
}
