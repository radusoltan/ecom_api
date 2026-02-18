<?php

declare(strict_types=1);

namespace App\Review\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\Event\DomainEvent;

final readonly class ReviewSubmitted implements DomainEvent
{
    public function __construct(
        public ReviewId $reviewId,
        public ProductId $productId,
        public ?string $customerId,
        public Rating $rating,
        public ?string $title,
        public ?string $content,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
