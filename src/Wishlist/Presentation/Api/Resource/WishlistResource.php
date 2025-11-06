<?php

declare(strict_types=1);

namespace App\Wishlist\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Wishlist\Presentation\Api\State\WishlistProvider;
use App\Wishlist\Presentation\Api\State\AddItemProcessor;
use App\Wishlist\Presentation\Api\State\RemoveItemProcessor;
use App\Wishlist\Presentation\Api\State\ClearWishlistProcessor;
use App\Wishlist\Presentation\Api\State\WishlistCountProvider;

#[ApiResource(
    shortName: 'Wishlist',
    operations: [
        new Get(
            uriTemplate: '/storefront/wishlist',
            provider: WishlistProvider::class,
            normalizationContext: ['groups' => ['wishlist:read']],
            description: 'Get customer wishlist'
        ),
        new Get(
            uriTemplate: '/storefront/wishlist/count',
            provider: WishlistCountProvider::class,
            normalizationContext: ['groups' => ['wishlist:count']],
            description: 'Get wishlist item count'
        ),
        new Post(
            uriTemplate: '/storefront/wishlist/add',
            processor: AddItemProcessor::class,
            denormalizationContext: ['groups' => ['wishlist:add']],
            normalizationContext: ['groups' => ['wishlist:read']],
            description: 'Add item to wishlist'
        ),
        new Delete(
            uriTemplate: '/storefront/wishlist/remove/{productId}',
            processor: RemoveItemProcessor::class,
            description: 'Remove item from wishlist'
        ),
        new Delete(
            uriTemplate: '/storefront/wishlist/clear',
            processor: ClearWishlistProcessor::class,
            description: 'Clear wishlist'
        ),
    ],
    normalizationContext: ['groups' => ['wishlist:read']]
)]
class WishlistResource
{
    public ?string $id = null;
    public ?string $customerId = null;
    public ?array $items = null;
    public ?int $itemCount = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $updatedAt = null;
}
