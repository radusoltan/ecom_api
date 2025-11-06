<?php

declare(strict_types=1);

namespace App\Review\Presentation\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Command\SubmitReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Presentation\Api\Resource\ProductReviewResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SubmitReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private RequestStack $requestStack
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductReviewResource
    {
        assert($data instanceof ProductReviewResource);

        $request = $this->requestStack->getCurrentRequest();
        $tenantId = $request->headers->get('X-Tenant-ID');

        if (!$tenantId) {
            throw new \RuntimeException('X-Tenant-ID header is required');
        }

        if (!$data->productId) {
            throw new \InvalidArgumentException('Product ID is required');
        }

        if (!$data->rating) {
            throw new \InvalidArgumentException('Rating is required');
        }

        // Generate a new review ID
        $reviewId = ReviewId::generate();

        $command = new SubmitReview(
            reviewId: $reviewId,
            productId: ProductId::fromString($data->productId),
            tenantId: TenantId::fromString($tenantId),
            customerId: $data->customerId,
            customerName: $data->customerName,
            rating: Rating::fromInt($data->rating),
            title: $data->title,
            content: $data->content,
            isVerifiedPurchase: $data->isVerifiedPurchase ?? false
        );

        $this->commandBus->dispatch($command);

        // Return the created resource
        $data->id = $reviewId->toString();
        $data->status = 'pending';
        $data->createdAt = new \DateTimeImmutable();
        $data->updatedAt = new \DateTimeImmutable();

        return $data;
    }
}
