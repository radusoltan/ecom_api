<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Review\Presentation\Api\State\DeleteReviewProcessor;
use App\Review\Presentation\Api\State\ProductReviewProvider;
use App\Review\Presentation\Api\State\ProductReviewsProvider;
use App\Review\Presentation\Api\State\ProductReviewStatsProvider;
use App\Review\Presentation\Api\State\RatingDistributionProvider;
use App\Review\Presentation\Api\State\SubmitReviewProcessor;
use App\Review\Presentation\Api\State\UpdateReviewProcessor;

#[ApiResource(
    shortName: 'ProductReview',
    operations: [
        new GetCollection(
            uriTemplate: '/storefront/products/{productId}/reviews',
            provider: ProductReviewsProvider::class,
            normalizationContext: ['groups' => ['review:read']],
            description: 'Get all approved reviews for a product'
        ),
        new Get(
            uriTemplate: '/storefront/reviews/{id}',
            provider: ProductReviewProvider::class,
            normalizationContext: ['groups' => ['review:read']],
            description: 'Get a specific review by ID'
        ),
        new GetCollection(
            uriTemplate: '/storefront/products/{productId}/reviews/stats',
            provider: ProductReviewStatsProvider::class,
            normalizationContext: ['groups' => ['review:stats']],
            description: 'Get review statistics for a product'
        ),
        new GetCollection(
            uriTemplate: '/storefront/products/{productId}/reviews/rating-distribution',
            provider: RatingDistributionProvider::class,
            normalizationContext: ['groups' => ['review:stats']],
            description: 'Get rating distribution for a product'
        ),
        new Post(
            uriTemplate: '/storefront/reviews',
            processor: SubmitReviewProcessor::class,
            denormalizationContext: ['groups' => ['review:write']],
            normalizationContext: ['groups' => ['review:read']],
            description: 'Submit a new product review'
        ),
        new Put(
            uriTemplate: '/storefront/reviews/{id}',
            processor: UpdateReviewProcessor::class,
            denormalizationContext: ['groups' => ['review:update']],
            normalizationContext: ['groups' => ['review:read']],
            description: 'Update a pending review'
        ),
        new Delete(
            uriTemplate: '/storefront/reviews/{id}',
            processor: DeleteReviewProcessor::class,
            description: 'Delete a review'
        ),
    ],
    normalizationContext: ['groups' => ['review:read']]
)]
class ProductReviewResource
{
    public ?string $id = null;
    public ?string $productId = null;
    public ?string $customerId = null;
    public ?string $customerName = null;
    public ?int $rating = null;
    public ?string $title = null;
    public ?string $content = null;
    public ?string $status = null;
    public ?bool $isVerifiedPurchase = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $updatedAt = null;
}
