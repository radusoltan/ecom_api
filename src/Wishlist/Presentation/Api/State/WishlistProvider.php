<?php

declare(strict_types=1);

namespace App\Wishlist\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Query\GetWishlist;
use App\Wishlist\Presentation\Api\Resource\WishlistResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class WishlistProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly RequestStack $requestStack
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?WishlistResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request->headers->get('X-Tenant-ID');
        $customerId = $request->headers->get('X-Customer-ID') ?? 'guest'; // TODO: Get from auth

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        $query = new GetWishlist($customerId, TenantId::fromString($tenantId));
        $wishlist = $this->handle($query);

        if (null === $wishlist) {
            // Return empty wishlist
            $resource = new WishlistResource();
            $resource->items = [];
            $resource->itemCount = 0;

            return $resource;
        }

        // Convert to resource
        $resource = new WishlistResource();
        $resource->id = $wishlist->id()->toString();
        $resource->customerId = $wishlist->customerId();
        $resource->items = array_map(
            fn ($item) => [
                'productId' => $item->productId()->toString(),
                'addedAt' => $item->addedAt()->format('c'),
            ],
            $wishlist->items()
        );
        $resource->itemCount = $wishlist->itemCount();
        $resource->createdAt = $wishlist->createdAt();
        $resource->updatedAt = $wishlist->updatedAt();

        return $resource;
    }
}
