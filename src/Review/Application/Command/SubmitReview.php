<?php

declare(strict_types=1);

namespace App\Review\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class SubmitReview
{
    public function __construct(
        public ReviewId $reviewId,
        public ProductId $productId,
        public TenantId $tenantId,
        public ?string $customerId,
        public ?string $customerName,
        public Rating $rating,
        public ?string $title = null,
        public ?string $content = null,
        public bool $isVerifiedPurchase = false
    ) {}
}
