<?php

declare(strict_types=1);

namespace App\Review\Application\Command;

use App\Review\Domain\Model\ReviewId;

final readonly class UpdateReview
{
    public function __construct(
        public ReviewId $reviewId,
        public string $title,
        public string $content,
    ) {
    }
}
