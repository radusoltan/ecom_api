<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\User\Presentation\Api\Processor\RequestPasswordResetProcessor;
use App\User\Presentation\Api\Processor\ResetPasswordProcessor;

/**
 * Password Reset API Resource.
 *
 * Provides endpoints for password reset flow:
 * 1. Request reset: POST /api/v1/auth/password/reset-request
 * 2. Reset password: POST /api/v1/auth/password/reset
 *
 * Note: Routes use /auth/... prefix and API Platform adds /api/v1 prefix.
 */
#[ApiResource(
    shortName: 'PasswordReset',
    description: 'Password reset functionality with email verification. Supports secure token-based password reset flow.',
    operations: [
        new Post(
            uriTemplate: '/auth/password/reset-request',
            status: 200,
            processor: RequestPasswordResetProcessor::class,
            openapi: new Model\Operation(
                summary: 'Request password reset',
                description: 'Initiates password reset flow by sending a reset email with a secure token. Always returns success to prevent email enumeration attacks.',
                requestBody: new Model\RequestBody(
                    description: 'User email address',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email'],
                                'properties' => [
                                    'email' => [
                                        'type' => 'string',
                                        'format' => 'email',
                                        'example' => 'user@example.com',
                                        'description' => 'Email address of the user requesting password reset',
                                    ],
                                ],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => new Model\Response(
                        description: 'Reset email sent (if email exists)',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'If an account with that email exists, a password reset link has been sent.',
                                        ],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '400' => new Model\Response(description: 'Invalid request (missing or invalid email)'),
                ]
            )
        ),
        new Post(
            uriTemplate: '/auth/password/reset',
            status: 200,
            processor: ResetPasswordProcessor::class,
            openapi: new Model\Operation(
                summary: 'Reset password',
                description: 'Resets user password using the token received via email. Token expires after 1 hour and can only be used once. All existing sessions will be invalidated.',
                requestBody: new Model\RequestBody(
                    description: 'Reset token and new password',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['token', 'newPassword'],
                                'properties' => [
                                    'token' => [
                                        'type' => 'string',
                                        'minLength' => 64,
                                        'maxLength' => 64,
                                        'example' => 'a1b2c3d4e5f6...64_character_secure_token',
                                        'description' => '64-character secure token from reset email',
                                    ],
                                    'newPassword' => [
                                        'type' => 'string',
                                        'format' => 'password',
                                        'minLength' => 8,
                                        'example' => 'NewSecureP@ssw0rd',
                                        'description' => 'New password (min 8 characters)',
                                    ],
                                ],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => new Model\Response(
                        description: 'Password reset successful',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => [
                                            'type' => 'string',
                                            'example' => 'Password has been reset successfully. Please login with your new password.',
                                        ],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '400' => new Model\Response(description: 'Invalid request (missing required fields, weak password)'),
                    '401' => new Model\Response(description: 'Invalid, expired, or already used token'),
                ]
            )
        ),
    ]
)]
class PasswordResetResource
{
    // Request password reset
    public ?string $email = null;

    // Reset password
    public ?string $token = null;
    public ?string $newPassword = null;

    // Response message
    public ?string $message = null;
}
