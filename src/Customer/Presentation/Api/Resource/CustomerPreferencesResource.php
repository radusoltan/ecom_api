<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\Customer\Presentation\Api\Processor\UpdatePreferencesProcessor;
use App\Customer\Presentation\Api\Provider\CustomerPreferencesProvider;

/**
 * Customer Preferences API Resource.
 *
 * Exposes customer preferences via REST API.
 * GET /api/customers/{customerId}/preferences - Retrieve preferences
 * PUT /api/customers/{customerId}/preferences - Update preferences
 */
#[ApiResource(
    shortName: 'CustomerPreferences',
    security: "is_granted('ROLE_USER')",
    operations: [
        new Get(
            uriTemplate: '/customers/{customerId}/preferences',
            provider: CustomerPreferencesProvider::class
        ),
        new Put(
            uriTemplate: '/customers/{customerId}/preferences',
            processor: UpdatePreferencesProcessor::class
        ),
    ]
)]
class CustomerPreferencesResource
{
    public ?string $customerId = null;
    public ?string $preferredLanguage = null;
    public ?string $preferredCurrency = null;
    public ?bool $newsletterSubscribed = null;
    public ?bool $marketingEmailsAllowed = null;
    public ?bool $smsNotificationsAllowed = null;
    public ?bool $orderNotificationsEnabled = null;
    public ?bool $promotionNotificationsEnabled = null;
}
