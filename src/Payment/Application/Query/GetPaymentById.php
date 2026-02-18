<?php

declare(strict_types=1);

namespace App\Payment\Application\Query;

use App\Payment\Domain\Model\PaymentId;

final readonly class GetPaymentById
{
    public function __construct(
        public PaymentId $id,
    ) {
    }
}
