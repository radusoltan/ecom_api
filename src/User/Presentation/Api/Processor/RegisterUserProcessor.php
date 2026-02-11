<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Infrastructure\Tenant\TenantContext;
use App\User\Application\Command\RegisterUser\RegisterUser;
use App\User\Domain\Exception\EmailAlreadyExistsException;
use App\User\Domain\Exception\UsernameAlreadyExistsException;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Presentation\Api\Resource\AuthResource;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * API Platform processor for user registration.
 *
 * Handles user registration with automatic JWT token generation for immediate login.
 *
 * @implements ProcessorInterface<AuthResource, AuthResource>
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantContext $tenantContext,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private RequestStack $requestStack
    ) {
    }

    /**
     * @param AuthResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AuthResource
    {
        if (!$data instanceof AuthResource) {
            throw new BadRequestHttpException('Expected AuthResource');
        }

        // Validate required fields
        if (!$data->email || !$data->username || !$data->password) {
            throw new BadRequestHttpException('Email, username, and password are required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantId = $this->tenantContext->getTenantId();
        if (!$tenantId) {
            // Fallback: try to get from X-Tenant-ID header
            $request = $this->requestStack->getCurrentRequest();
            $tenantId = $request?->headers->get('X-Tenant-ID');
        }

        if (!$tenantId) {
            throw new BadRequestHttpException('Tenant ID is required (X-Tenant-ID header)');
        }

        // Create registration command
        $command = new RegisterUser(
            tenantId: $tenantId,
            email: $data->email,
            username: $data->username,
            plainPassword: $data->password
        );

        try {
            // Dispatch command and get user ID
            $envelope = $this->commandBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if (!$handledStamp instanceof HandledStamp) {
                throw new \RuntimeException('No handler found for RegisterUser command');
            }

            $userId = $handledStamp->getResult();

            // Create a temporary UserEntity for JWT token generation
            // Note: This is a workaround since JWTTokenManagerInterface requires UserInterface
            $userEntity = new UserEntity();
            $userEntity->setId($userId->toString());
            $userEntity->setEmail($data->email);
            $userEntity->setUsername($data->username);
            $userEntity->setRoles(['ROLE_CUSTOMER']);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            // Generate JWT token
            $jwtToken = $this->jwtManager->create($userEntity);

            // Generate refresh token
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
                $userEntity,
                2592000 // 30 days in seconds
            );
            $this->refreshTokenManager->save($refreshToken);

            // Prepare response
            $response = new AuthResource();
            $response->user = [
                'id' => $userId->toString(),
                'email' => $data->email,
                'username' => $data->username,
                'roles' => ['ROLE_CUSTOMER'],
            ];
            $response->token = $jwtToken;
            $response->refreshToken = $refreshToken->getRefreshToken();

            return $response;
        } catch (HandlerFailedException $e) {
            // Unwrap the nested exception from Messenger
            $previous = $e->getPrevious();
            while ($previous instanceof HandlerFailedException) {
                $previous = $previous->getPrevious();
            }

            if ($previous instanceof EmailAlreadyExistsException) {
                throw new ConflictHttpException($previous->getMessage());
            }
            if ($previous instanceof UsernameAlreadyExistsException) {
                throw new ConflictHttpException($previous->getMessage());
            }
            if ($previous instanceof \InvalidArgumentException) {
                throw new BadRequestHttpException($previous->getMessage());
            }
            if ($previous instanceof \DomainException) {
                throw new BadRequestHttpException($previous->getMessage());
            }

            // Re-throw if we can't handle it
            throw new BadRequestHttpException($previous ? $previous->getMessage() : $e->getMessage());
        } catch (EmailAlreadyExistsException $e) {
            throw new ConflictHttpException($e->getMessage());
        } catch (UsernameAlreadyExistsException $e) {
            throw new ConflictHttpException($e->getMessage());
        } catch (\DomainException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
