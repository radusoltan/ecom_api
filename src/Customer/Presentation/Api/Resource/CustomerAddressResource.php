<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\AddAddressProcessor;
use App\Customer\Presentation\Api\Processor\RemoveAddressProcessor;
use App\Customer\Presentation\Api\Processor\SetDefaultAddressProcessor;
use App\Customer\Presentation\Api\Processor\UpdateAddressProcessor;
use App\Customer\Presentation\Api\Provider\CustomerAddressCollectionProvider;
use App\Customer\Presentation\Api\Provider\CustomerAddressItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Customer Address API Resource.
 *
 * Represents customer addresses in the API.
 * Supports CRUD operations and setting default addresses.
 */
#[ApiResource(
    shortName: 'CustomerAddress',
    description: 'Customer shipping and billing addresses',
    operations: [
        new GetCollection(
            uriTemplate: '/customers/{customerId}/addresses',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: CustomerAddressCollectionProvider::class,
            security: "is_granted('customer.view')",
            openapi: new Model\Operation(
                summary: 'Retrieve customer addresses',
                description: 'Get all addresses for a specific customer. Can be filtered by type (shipping, billing, other).',
                parameters: [
                    new Model\Parameter(
                        name: 'customerId',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                        description: 'Customer UUID'
                    ),
                    new Model\Parameter(
                        name: 'type',
                        in: 'query',
                        required: false,
                        schema: ['type' => 'string', 'enum' => ['shipping', 'billing', 'other']],
                        description: 'Filter addresses by type'
                    ),
                ],
                responses: [
                    '200' => [
                        'description' => 'List of customer addresses',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    [
                                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                                        'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                        'tenantId' => '00000000-0000-4000-8000-000000000001',
                                        'street' => '123 Main St',
                                        'street2' => 'Apt 4B',
                                        'city' => 'New York',
                                        'state' => 'NY',
                                        'postalCode' => '10001',
                                        'country' => 'US',
                                        'type' => 'shipping',
                                        'isDefaultShipping' => true,
                                        'isDefaultBilling' => false,
                                        'createdAt' => '2024-01-15T10:30:00+00:00',
                                        'updatedAt' => '2024-01-15T10:30:00+00:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized - Missing or invalid authentication'],
                    '403' => ['description' => 'Forbidden - Insufficient permissions'],
                    '404' => ['description' => 'Customer not found'],
                ],
            )
        ),
        new Get(
            uriTemplate: '/customers/{customerId}/addresses/{id}',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'customerId',
                ],
                'id' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'id',
                ],
            ],
            provider: CustomerAddressItemProvider::class,
            security: "is_granted('customer.view')",
            openapi: new Model\Operation(
                summary: 'Retrieve a single address',
                description: 'Get details of a specific customer address by ID.',
                responses: [
                    '200' => ['description' => 'Address details'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'Address not found'],
                ],
            )
        ),
        new Post(
            uriTemplate: '/customers/{customerId}/addresses',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: AddAddressProcessor::class,
            security: "is_granted('customer.edit')",
            openapi: new Model\Operation(
                summary: 'Add a new address',
                description: 'Create a new shipping or billing address for a customer.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['street', 'city', 'postalCode', 'country', 'type'],
                                'properties' => [
                                    'street' => ['type' => 'string', 'maxLength' => 255, 'example' => '123 Main St'],
                                    'street2' => ['type' => 'string', 'maxLength' => 255, 'nullable' => true, 'example' => 'Apt 4B'],
                                    'city' => ['type' => 'string', 'maxLength' => 100, 'example' => 'New York'],
                                    'state' => ['type' => 'string', 'maxLength' => 100, 'nullable' => true, 'example' => 'NY'],
                                    'postalCode' => ['type' => 'string', 'maxLength' => 20, 'example' => '10001'],
                                    'country' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 2, 'example' => 'US'],
                                    'type' => ['type' => 'string', 'enum' => ['shipping', 'billing', 'other'], 'example' => 'shipping'],
                                    'isDefaultShipping' => ['type' => 'boolean', 'example' => false],
                                    'isDefaultBilling' => ['type' => 'boolean', 'example' => false],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '201' => ['description' => 'Address created successfully'],
                    '400' => ['description' => 'Bad Request - Invalid input'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Put(
            uriTemplate: '/customers/{customerId}/addresses/{id}',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'customerId',
                ],
                'id' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: UpdateAddressProcessor::class,
            security: "is_granted('customer.edit')",
            openapi: new Model\Operation(
                summary: 'Update an address',
                description: 'Update all fields of an existing customer address.',
                responses: [
                    '200' => ['description' => 'Address updated successfully'],
                    '400' => ['description' => 'Bad Request'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'Address not found'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Delete(
            uriTemplate: '/customers/{customerId}/addresses/{id}',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'customerId',
                ],
                'id' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: RemoveAddressProcessor::class,
            security: "is_granted('customer.edit')",
            openapi: new Model\Operation(
                summary: 'Delete an address',
                description: 'Soft-delete a customer address (marks as deleted, not permanently removed).',
                responses: [
                    '204' => ['description' => 'Address deleted successfully'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'Address not found'],
                ],
            )
        ),
        new Patch(
            uriTemplate: '/customers/{customerId}/addresses/{id}/default',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'customerId',
                ],
                'id' => [
                    'from_class' => CustomerAddressResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: SetDefaultAddressProcessor::class,
            security: "is_granted('customer.edit')",
            openapi: new Model\Operation(
                summary: 'Set address as default',
                description: 'Set an address as default for shipping or billing. Unsets previous defaults of the same type.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['type'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => ['shipping', 'billing'],
                                        'example' => 'shipping',
                                        'description' => 'Type of default to set',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => ['description' => 'Default address set successfully'],
                    '400' => ['description' => 'Bad Request - Invalid type'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'Address not found'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['customer_address:read']],
    denormalizationContext: ['groups' => ['customer_address:write']],
)]
class CustomerAddressResource
{
    #[ApiProperty(identifier: true, readable: true, writable: false)]
    public ?string $id = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $customerId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $tenantId = null;

    #[Assert\NotBlank(message: 'Street is required')]
    #[Assert\Length(max: 255, maxMessage: 'Street cannot be longer than {{ limit }} characters')]
    public ?string $street = null;

    #[Assert\Length(max: 255, maxMessage: 'Street 2 cannot be longer than {{ limit }} characters')]
    public ?string $street2 = null;

    #[Assert\NotBlank(message: 'City is required')]
    #[Assert\Length(max: 100, maxMessage: 'City cannot be longer than {{ limit }} characters')]
    public ?string $city = null;

    #[Assert\Length(max: 100, maxMessage: 'State cannot be longer than {{ limit }} characters')]
    public ?string $state = null;

    #[Assert\NotBlank(message: 'Postal code is required')]
    #[Assert\Length(max: 20, maxMessage: 'Postal code cannot be longer than {{ limit }} characters')]
    public ?string $postalCode = null;

    #[Assert\NotBlank(message: 'Country is required')]
    #[Assert\Length(min: 2, max: 2, exactMessage: 'Country must be exactly {{ limit }} characters (ISO 3166-1 alpha-2)')]
    public ?string $country = null;

    #[Assert\NotBlank(message: 'Address type is required')]
    #[Assert\Choice(choices: ['shipping', 'billing', 'other'], message: 'Type must be one of: {{ choices }}')]
    public ?string $type = null;

    public bool $isDefaultShipping = false;

    public bool $isDefaultBilling = false;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $updatedAt = null;
}
