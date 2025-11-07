<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Review\Domain\Model\ReviewId;

final readonly class GetReviewById
{
    public function __construct(
        public ReviewId $reviewId
    ) {
    }
}
