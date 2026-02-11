<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\UpdateNotificationPreferencesProcessor;
use App\Customer\Presentation\Api\Provider\NotificationPreferencesProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Notification Preferences API Resource.
 *
 * Customer notification preferences for emails, SMS, and push notifications.
 * Controls which types of notifications the customer wants to receive.
 */
#[ApiResource(
    shortName: 'NotificationPreferences',
    description: 'Customer notification preferences',
    operations: [
        new Get(
            uriTemplate: '/customers/{customerId}/notification-preferences',
            uriVariables: [
                'customerId' => [
                    'from_class' => NotificationPreferencesResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: NotificationPreferencesProvider::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Get notification preferences',
                description: 'Retrieve customer notification preferences for all channels.',
                responses: [
                    '200' => [
                        'description' => 'Notification preferences',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'newsletterSubscribed' => true,
                                    'marketingEmailsAllowed' => true,
                                    'smsNotificationsAllowed' => false,
                                    'orderNotificationsEnabled' => true,
                                    'promotionNotificationsEnabled' => true,
                                    'preferredLanguage' => 'en',
                                    'preferredCurrency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only view own preferences'],
                    '404' => ['description' => 'Customer not found'],
                ],
            )
        ),
        new Put(
            uriTemplate: '/customers/{customerId}/notification-preferences',
            uriVariables: [
                'customerId' => [
                    'from_class' => NotificationPreferencesResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: UpdateNotificationPreferencesProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Update notification preferences',
                description: 'Update customer notification preferences. All fields are required.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => [
                                    'newsletterSubscribed',
                                    'marketingEmailsAllowed',
                                    'smsNotificationsAllowed',
                                    'orderNotificationsEnabled',
                                    'promotionNotificationsEnabled',
                                ],
                                'properties' => [
                                    'newsletterSubscribed' => [
                                        'type' => 'boolean',
                                        'example' => true,
                                        'description' => 'Subscribe to newsletter',
                                    ],
                                    'marketingEmailsAllowed' => [
                                        'type' => 'boolean',
                                        'example' => true,
                                        'description' => 'Allow marketing emails',
                                    ],
                                    'smsNotificationsAllowed' => [
                                        'type' => 'boolean',
                                        'example' => false,
                                        'description' => 'Allow SMS notifications',
                                    ],
                                    'orderNotificationsEnabled' => [
                                        'type' => 'boolean',
                                        'example' => true,
                                        'description' => 'Enable order status notifications',
                                    ],
                                    'promotionNotificationsEnabled' => [
                                        'type' => 'boolean',
                                        'example' => true,
                                        'description' => 'Enable promotion notifications',
                                    ],
                                    'preferredLanguage' => [
                                        'type' => 'string',
                                        'minLength' => 2,
                                        'maxLength' => 5,
                                        'example' => 'en',
                                        'description' => 'Preferred language code (ISO 639-1)',
                                    ],
                                    'preferredCurrency' => [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'maxLength' => 3,
                                        'example' => 'USD',
                                        'description' => 'Preferred currency code (ISO 4217)',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => [
                        'description' => 'Preferences updated successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'newsletterSubscribed' => true,
                                    'marketingEmailsAllowed' => true,
                                    'smsNotificationsAllowed' => false,
                                    'orderNotificationsEnabled' => true,
                                    'promotionNotificationsEnabled' => true,
                                    'preferredLanguage' => 'en',
                                    'preferredCurrency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only update own preferences'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['notification_preferences:read']],
    denormalizationContext: ['groups' => ['notification_preferences:write']],
)]
class NotificationPreferencesResource
{
    #[ApiProperty(identifier: false, readable: true, writable: false)]
    public ?string $customerId = null;

    #[Assert\NotNull(message: 'Newsletter subscription preference is required')]
    #[Assert\Type(type: 'bool', message: 'Newsletter subscription must be a boolean')]
    public ?bool $newsletterSubscribed = null;

    #[Assert\NotNull(message: 'Marketing emails preference is required')]
    #[Assert\Type(type: 'bool', message: 'Marketing emails preference must be a boolean')]
    public ?bool $marketingEmailsAllowed = null;

    #[Assert\NotNull(message: 'SMS notifications preference is required')]
    #[Assert\Type(type: 'bool', message: 'SMS notifications preference must be a boolean')]
    public ?bool $smsNotificationsAllowed = null;

    #[Assert\NotNull(message: 'Order notifications preference is required')]
    #[Assert\Type(type: 'bool', message: 'Order notifications preference must be a boolean')]
    public ?bool $orderNotificationsEnabled = null;

    #[Assert\NotNull(message: 'Promotion notifications preference is required')]
    #[Assert\Type(type: 'bool', message: 'Promotion notifications preference must be a boolean')]
    public ?bool $promotionNotificationsEnabled = null;

    #[Assert\Length(min: 2, max: 5, minMessage: 'Language code must be at least {{ limit }} characters', maxMessage: 'Language code cannot exceed {{ limit }} characters')]
    public ?string $preferredLanguage = null;

    #[Assert\Length(min: 3, max: 3, exactMessage: 'Currency code must be exactly {{ limit }} characters (ISO 4217)')]
    public ?string $preferredCurrency = null;
}
