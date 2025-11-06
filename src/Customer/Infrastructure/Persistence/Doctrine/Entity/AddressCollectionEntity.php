<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Customer\Presentation\Api\Provider\AddressesProvider;

/**
 * Address Collection Entity - wrapper for customer addresses API endpoint.
 *
 * This is a virtual entity (not mapped to database) used only for API Platform routing.
 */
#[ApiResource(
    shortName: 'ProfileAddresses',
    operations: [
        new Get(
            uriTemplate: '/profile/addresses',
            provider: AddressesProvider::class,
            normalizationContext: ['skip_null_values' => true]
        ),
    ]
)]
class AddressCollectionEntity
{
    // This is a virtual entity - no database mapping needed
    // Used only for API Platform routing
}
