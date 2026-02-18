<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Query\GetProductReviews;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProductReviewsProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly RequestStack $requestStack,
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request->headers->get('X-Tenant-ID');

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        $productId = $uriVariables['productId'] ?? null;
        if (!$productId) {
            throw new \InvalidArgumentException('Product ID is required');
        }

        $query = new GetProductReviews(
            ProductId::fromString($productId),
            TenantId::fromString($tenantId),
            onlyApproved: true
        );

        $reviews = $this->handle($query);

        return array_map(function ($review) {
            $resource = new ProductReviewResource();
            $resource->id = $review->id()->toString();
            $resource->productId = $review->productId()->toString();
            $resource->customerId = $review->customerId();
            $resource->customerName = $review->customerName();
            $resource->rating = $review->rating()->value();
            $resource->title = $review->title();
            $resource->content = $review->content();
            $resource->status = $review->status()->value;
            $resource->isVerifiedPurchase = $review->isVerifiedPurchase();
            $resource->createdAt = $review->createdAt();
            $resource->updatedAt = $review->updatedAt();

            return $resource;
        }, $reviews);
    }
}
