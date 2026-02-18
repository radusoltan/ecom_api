<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Command\ResetPassword\ResetPassword;
use App\User\Presentation\Api\Resource\PasswordResetResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * API Platform processor for resetting password.
 *
 * Delegates to ResetPassword command handler.
 * Validates token and updates user password.
 *
 * @implements ProcessorInterface<PasswordResetResource, PasswordResetResource>
 */
final readonly class ResetPasswordProcessor implements ProcessorInterface
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
        // Validate token provided
        if (empty($data->token)) {
            throw new BadRequestHttpException('Reset token is required');
        }

        // Validate token format (64 characters)
        if (64 !== strlen($data->token)) {
            throw new UnauthorizedHttpException('', 'Invalid reset token format');
        }

        // Validate new password provided
        if (empty($data->newPassword)) {
            throw new BadRequestHttpException('New password is required');
        }

        // Validate password strength (minimum 8 characters)
        if (strlen($data->newPassword) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters long');
        }

        // Dispatch command
        try {
            $command = new ResetPassword(
                token: $data->token,
                newPassword: $data->newPassword
            );

            $this->messageBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Unwrap the original exception from HandlerFailedException
            $originalException = $e->getPrevious();

            if ($originalException instanceof \DomainException) {
                throw new UnauthorizedHttpException('', $originalException->getMessage());
            }

            // Re-throw if not a domain exception
            throw $e;
        } catch (\DomainException $e) {
            // Convert domain exceptions to HTTP 401 Unauthorized
            throw new UnauthorizedHttpException('', $e->getMessage());
        }

        // Return success message
        $response = new PasswordResetResource();
        $response->message = 'Password has been reset successfully. Please login with your new password.';

        return $response;
    }
}
