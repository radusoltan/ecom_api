<?php

declare(strict_types=1);

namespace App\Cart\Domain\Event;

use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CartCreated
{
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId,
        public ?CustomerId $customerId,
        public ?SessionId $sessionId
    ) {
    }
}