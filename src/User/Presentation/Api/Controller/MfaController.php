<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Controller;

use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\UserId;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Security\MfaTotpService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * MFA Controller — handles TOTP setup, verification, and backup code management.
 *
 * Endpoints:
 * - POST /api/v1/mfa/setup       — Generate TOTP secret and QR code
 * - POST /api/v1/mfa/enable      — Verify code and enable MFA
 * - POST /api/v1/mfa/verify      — Verify TOTP code during login (partial JWT)
 * - POST /api/v1/mfa/disable     — Disable MFA
 * - POST /api/v1/mfa/backup-codes — Regenerate backup codes
 */
#[Route('/api/v1/mfa')]
final class MfaController extends AbstractController
{
    public function __construct(
        private readonly MfaTotpService $totpService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire(service: 'limiter.mfa_verify')]
        private readonly RateLimiterFactory $mfaVerifyLimiter,
    ) {
    }

    /**
     * Generate a TOTP secret and QR code for setup.
     * Requires fully authenticated user (MFA not yet enabled).
     */
    #[Route('/setup', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function setup(): JsonResponse
    {
        $userEntity = $this->getUser();
        if (!$userEntity instanceof UserEntity) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->findById(UserId::fromString($userEntity->getId()));
        if (null === $user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if ($user->mfaEnabled()) {
            return new JsonResponse(['error' => 'MFA is already enabled'], Response::HTTP_CONFLICT);
        }

        $secret = $this->totpService->generateSecret();
        $provisioningUri = $this->totpService->getProvisioningUri($secret, $user->email()->toString());
        $qrCodeDataUri = $this->totpService->generateQrCodeDataUri($provisioningUri);

        return new JsonResponse([
            'secret' => $secret,
            'qrCode' => $qrCodeDataUri,
            'provisioningUri' => $provisioningUri,
        ]);
    }

    /**
     * Verify a TOTP code and enable MFA.
     * Requires the secret from /setup and a valid code from the authenticator app.
     */
    #[Route('/enable', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function enable(Request $request): JsonResponse
    {
        $userEntity = $this->getUser();
        if (!$userEntity instanceof UserEntity) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $secret = $data['secret'] ?? null;
        $code = $data['code'] ?? null;

        if (null === $secret || null === $code) {
            return new JsonResponse(
                ['error' => 'Both "secret" and "code" are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Verify the code matches the secret
        if (!$this->totpService->verifyCode($secret, $code)) {
            return new JsonResponse(
                ['error' => 'Invalid TOTP code'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user = $this->userRepository->findById(UserId::fromString($userEntity->getId()));
        if (null === $user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Generate backup codes
        $plainBackupCodes = $this->totpService->generateBackupCodes();
        $hashedBackupCodes = $this->totpService->hashBackupCodes($plainBackupCodes);

        // Enable MFA on domain model
        $user->enableMfa($secret, $hashedBackupCodes);
        $this->userRepository->save($user);

        return new JsonResponse([
            'message' => 'MFA enabled successfully',
            'backupCodes' => $plainBackupCodes,
        ]);
    }

    /**
     * Verify TOTP code during login (for users with MFA enabled).
     * Accepts partial JWT (ROLE_MFA_PENDING) and returns full JWT.
     * Rate limited to 5 attempts per 15 minutes per user.
     */
    #[Route('/verify', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function verify(Request $request): JsonResponse
    {
        $userEntity = $this->getUser();
        if (!$userEntity instanceof UserEntity) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        // Rate limit: 5 attempts per 15 minutes per user
        $limiter = $this->mfaVerifyLimiter->create($userEntity->getId());
        if (false === $limiter->consume()->isAccepted()) {
            return new JsonResponse(
                ['error' => 'Too many MFA attempts. Please try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? null;
        $backupCode = $data['backupCode'] ?? null;

        if (null === $code && null === $backupCode) {
            return new JsonResponse(
                ['error' => 'Either "code" or "backupCode" is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findById(UserId::fromString($userEntity->getId()));
        if (null === $user || !$user->mfaEnabled()) {
            return new JsonResponse(['error' => 'MFA not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $verified = false;

        // Try TOTP code first
        if (null !== $code && null !== $user->totpSecret()) {
            $verified = $this->totpService->verifyCode($user->totpSecret(), $code);
        }

        // Try backup code
        if (!$verified && null !== $backupCode) {
            $hashedCodes = $user->backupCodes();
            $matchIndex = $this->totpService->verifyBackupCode($backupCode, $hashedCodes);

            if (false !== $matchIndex) {
                // Consume the backup code (single use)
                $user->consumeBackupCode($hashedCodes[$matchIndex]);
                $this->userRepository->save($user);
                $verified = true;
            }
        }

        if (!$verified) {
            return new JsonResponse(
                ['error' => 'Invalid MFA code'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Signal to MfaJwtEventListener that this is a post-MFA token (don't downgrade)
        $request->attributes->set('_mfa_verified', true);

        // Issue full JWT with actual roles (not MFA_PENDING)
        $token = $this->jwtManager->create($userEntity);

        return new JsonResponse([
            'token' => $token,
        ]);
    }

    /**
     * Disable MFA for the authenticated user.
     */
    #[Route('/disable', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function disable(Request $request): JsonResponse
    {
        $userEntity = $this->getUser();
        if (!$userEntity instanceof UserEntity) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? null;

        if (null === $code) {
            return new JsonResponse(
                ['error' => '"code" is required to disable MFA'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findById(UserId::fromString($userEntity->getId()));
        if (null === $user || !$user->mfaEnabled()) {
            return new JsonResponse(['error' => 'MFA not enabled'], Response::HTTP_BAD_REQUEST);
        }

        // Verify current code before disabling
        if (null === $user->totpSecret() || !$this->totpService->verifyCode($user->totpSecret(), $code)) {
            return new JsonResponse(
                ['error' => 'Invalid TOTP code'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $user->disableMfa();
        $this->userRepository->save($user);

        return new JsonResponse(['message' => 'MFA disabled successfully']);
    }

    /**
     * Regenerate backup codes.
     */
    #[Route('/backup-codes', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        $userEntity = $this->getUser();
        if (!$userEntity instanceof UserEntity) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? null;

        if (null === $code) {
            return new JsonResponse(
                ['error' => '"code" is required to regenerate backup codes'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findById(UserId::fromString($userEntity->getId()));
        if (null === $user || !$user->mfaEnabled()) {
            return new JsonResponse(['error' => 'MFA not enabled'], Response::HTTP_BAD_REQUEST);
        }

        // Verify current code
        if (null === $user->totpSecret() || !$this->totpService->verifyCode($user->totpSecret(), $code)) {
            return new JsonResponse(
                ['error' => 'Invalid TOTP code'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $plainBackupCodes = $this->totpService->generateBackupCodes();
        $hashedBackupCodes = $this->totpService->hashBackupCodes($plainBackupCodes);

        $user->regenerateBackupCodes($hashedBackupCodes);
        $this->userRepository->save($user);

        return new JsonResponse([
            'message' => 'Backup codes regenerated',
            'backupCodes' => $plainBackupCodes,
        ]);
    }
}
