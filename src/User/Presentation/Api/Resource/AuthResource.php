<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\User\Presentation\Api\Processor\RefreshTokenProcessor;
use App\User\Presentation\Api\Processor\RegisterUserProcessor;

#[ApiResource(
    shortName: 'Auth',
    description: 'JWT Authentication endpoints for user registration and token refresh',
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            processor: RegisterUserProcessor::class,
            openapi: new Model\Operation(
                summary: 'Register new user account',
                description: 'Creates a new user account with ROLE_CUSTOMER. Returns user data with JWT token for immediate login. Email and username must be unique.',
                requestBody: new Model\RequestBody(
                    description: 'User registration data',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email', 'username', 'password'],
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'format' => 'email',
                                        'example' => 'user@example.com',
                                        'description' => 'User email address (must be unique)',
                                    ],
                                    'username' => [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'maxLength' => 50,
                                        'example' => 'johndoe',
                                        'description' => 'Username (must be unique)',
                                    ],
                                    'password' => [
                                        'type' => 'string',
                                        'minLength' => 8,
                                        'example' => 'SecurePass123!',
                                        'description' => 'Password (minimum 8 characters)',
                                    ],
                                    'firstName' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                        'example' => 'John',
                                        'description' => 'First name (optional)',
                                    ],
                                    'lastName' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                        'example' => 'Doe',
                                        'description' => 'Last name (optional)',
                                    ],
                                ],
                            ],
                        ],
                    ])
                ),
                parameters: [
                    new Model\Parameter(
                        name: 'X-Tenant-ID',
                        in: 'header',
                        description: 'Tenant identifier for multi-tenancy',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ],
                responses: [
                    '201' => new Model\Response(
                        description: 'User registered successfully with JWT token',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'string', 'format' => 'uuid'],
                                                'email' => ['type' => 'string', 'format' => 'email'],
                                                'username' => ['type' => 'string'],
                                                'roles' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string'],
                                                    'example' => ['ROLE_CUSTOMER'],
                                                ],
                                            ],
                                        ],
                                        'token' => [
                                            'type' => 'string',
                                            'description' => 'JWT access token',
                                            'example' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
                                        ],
                                        'refreshToken' => [
                                            'type' => 'string',
                                            'description' => 'Refresh token for obtaining new access tokens',
                                        ],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '400' => new Model\Response(
                        description: 'Invalid input data (validation errors)',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => ['type' => 'string', 'example' => 'Email already exists'],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '409' => new Model\Response(
                        description: 'Conflict - email or username already exists'
                    ),
                ]
            )
        ),
        new Post(
            uriTemplate: '/auth/token/refresh',
            processor: RefreshTokenProcessor::class,
            read: false,
            status: 200,
            openapi: new Model\Operation(
                summary: 'Refresh JWT access token',
                description: 'Exchange a refresh token for a new JWT access token. Use this endpoint when your access token expires.',
                requestBody: new Model\RequestBody(
                    description: 'Refresh token',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['refresh_token'],
                                'properties' => [
                                    'refresh_token' => [
                                        'type' => 'string',
                                        'description' => 'The refresh token received during login or registration',
                                        'example' => 'def502001234567890abcdef...',
                                    ],
                                ],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => new Model\Response(
                        description: 'New access token generated successfully',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string',
                                            'description' => 'New JWT access token',
                                            'example' => 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
                                        ],
                                        'refresh_token' => [
                                            'type' => 'string',
                                            'description' => 'Updated refresh token',
                                        ],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '401' => new Model\Response(
                        description: 'Invalid or expired refresh token'
                    ),
                ]
            )
        ),
    ]
)]
class AuthResource
{
    // Registration fields
    public ?string $email = null;
    public ?string $username = null;
    public ?string $password = null;
    public ?string $firstName = null;
    public ?string $lastName = null;

    // Token refresh fields
    public ?string $refresh_token = null;

    // Response fields
    /** @var array<string, mixed>|null */
    public ?array $user = null;
    public ?string $token = null;
    public ?string $refreshToken = null;
}
