<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Pricing\Presentation\Api\Processor\ValidateCouponProcessor;

#[ApiResource(
    shortName: 'CouponValidation',
    operations: [
        new Post(
            uriTemplate: '/coupons/validate',
            processor: ValidateCouponProcessor::class,
            description: 'Validate a coupon code - validates a coupon code and returns discount information if valid. Required: code, cart_total (amount, currency)'
        ),
    ]
)]
class CouponValidationResource
{
    public ?string $code = null;
    public ?array $cart_total = null;
    public ?bool $valid = null;
    public ?array $promotion = null;
    public ?array $discount_amount = null;
    public ?string $message = null;
}
