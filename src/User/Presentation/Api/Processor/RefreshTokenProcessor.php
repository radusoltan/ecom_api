<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Presentation\Api\Resource\AuthResource;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * API Platform processor for JWT token refresh.
 *
 * Exchanges a valid refresh token for a new JWT access token.
 *
 * @implements ProcessorInterface<AuthResource, AuthResource>
 */
final readonly class RefreshTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private RefreshTokenManagerInterface $refreshTokenManager,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private EntityManagerInterface $entityManager
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

        // Validate refresh token is provided
        if (!$data->refresh_token) {
            throw new BadRequestHttpException('Refresh token is required');
        }

        // Find the refresh token
        $refreshToken = $this->refreshTokenManager->get($data->refresh_token);

        if (!$refreshToken) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token');
        }

        // Check if token is expired
        if (!$refreshToken->isValid()) {
            throw new UnauthorizedHttpException('Bearer', 'Refresh token has expired');
        }

        // Get the user from the refresh token
        $username = $refreshToken->getUsername();
        $userEntity = $this->entityManager
            ->getRepository(UserEntity::class)
            ->findOneBy(['email' => $username]);

        if (!$userEntity) {
            throw new UnauthorizedHttpException('Bearer', 'User not found');
        }

        // Generate new JWT access token
        $newJwtToken = $this->jwtManager->create($userEntity);

        // Delete old refresh token (security: single use)
        $this->refreshTokenManager->delete($refreshToken);

        // Generate new refresh token
        $newRefreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
            $userEntity,
            2592000 // 30 days in seconds
        );

        // Save new refresh token
        $this->refreshTokenManager->save($newRefreshToken);

        // Flush to ensure old token is deleted from database immediately
        $this->entityManager->flush();

        // Prepare response
        $response = new AuthResource();
        $response->token = $newJwtToken;
        $response->refreshToken = $newRefreshToken->getRefreshToken();

        return $response;
    }
}
