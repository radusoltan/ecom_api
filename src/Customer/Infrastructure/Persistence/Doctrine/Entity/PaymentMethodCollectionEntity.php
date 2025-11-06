<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Customer\Presentation\Api\Provider\PaymentMethodsProvider;

/**
 * Payment Method Collection Entity - wrapper for customer payment methods API endpoint.
 *
 * This is a virtual entity (not mapped to database) used only for API Platform routing.
 */
#[ApiResource(
    shortName: 'ProfilePaymentMethods',
    operations: [
        new Get(
            uriTemplate: '/profile/payment-methods',
            provider: PaymentMethodsProvider::class
        ),
    ]
)]
class PaymentMethodCollectionEntity
{
    // This is a virtual entity - no database mapping needed
    // Used only for API Platform routing
}
