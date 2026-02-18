<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Review\Application\Query\GetReviewById;
use App\Review\Domain\Model\ReviewId;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class ProductReviewProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $queryBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ProductReviewResource
    {
        $reviewId = $uriVariables['id'] ?? null;
        if (!$reviewId) {
            throw new \InvalidArgumentException('Review ID is required');
        }

        $query = new GetReviewById(ReviewId::fromString($reviewId));
        $review = $this->handle($query);

        if (null === $review) {
            return null;
        }

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
    }
}
