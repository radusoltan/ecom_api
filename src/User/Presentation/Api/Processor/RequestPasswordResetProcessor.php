<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Command\RequestPasswordReset\RequestPasswordReset;
use App\User\Presentation\Api\Resource\PasswordResetResource;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform processor for requesting password reset.
 *
 * Delegates to RequestPasswordReset command handler.
 * Always returns success message to prevent email enumeration attacks.
 *
 * @implements ProcessorInterface<PasswordResetResource, PasswordResetResource>
 */
final readonly class RequestPasswordResetProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param PasswordResetResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PasswordResetResource
    {
        // Validate email provided
        if (empty($data->email)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Email is required');
        }

        // Validate email format
        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid email format');
        }

        // Dispatch command (silently handles non-existent emails)
        $command = new RequestPasswordReset(
            email: $data->email
        );

        $this->messageBus->dispatch($command);

        // Always return success message (security best practice)
        $response = new PasswordResetResource();
        $response->message = 'If an account with that email exists, a password reset link has been sent.';

        return $response;
    }
}
