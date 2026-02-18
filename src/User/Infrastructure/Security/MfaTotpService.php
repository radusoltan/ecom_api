<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use OTPHP\TOTP;

/**
 * TOTP service for MFA using spomky-labs/otphp.
 *
 * Generates TOTP secrets, verifies codes, and produces QR codes for authenticator apps.
 */
final class MfaTotpService
{
    private const string ISSUER = 'EcommercePlatform';

    public function generateSecret(): string
    {
        $totp = TOTP::generate();

        return $totp->getSecret();
    }

    public function verifyCode(string $secret, string $code): bool
    {
        if ('' === $secret || '' === $code) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setIssuer(self::ISSUER);

        // Allow 1 period window for clock skew (30s before/after)
        return $totp->verify($code, null, 1);
    }

    /**
     * Generate a provisioning URI for authenticator apps.
     */
    public function getProvisioningUri(string $secret, string $email): string
    {
        \assert('' !== $secret && '' !== $email);

        $totp = TOTP::createFromSecret($secret);
        $totp->setIssuer(self::ISSUER);
        $totp->setLabel($email);

        return $totp->getProvisioningUri();
    }

    /**
     * Generate a QR code PNG as base64-encoded data URI.
     */
    public function generateQrCodeDataUri(string $provisioningUri): string
    {
        $builder = new Builder(
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 10,
            data: $provisioningUri,
        );

        return $builder->build()->getDataUri();
    }

    /**
     * Generate plain-text backup codes (8 codes, 8 chars each).
     *
     * @return list<string> Plain-text backup codes (to show to user once)
     */
    public function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; ++$i) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    /**
     * Hash backup codes for storage.
     *
     * @param list<string> $codes Plain-text codes
     *
     * @return list<string> Bcrypt hashes
     */
    public function hashBackupCodes(array $codes): array
    {
        return array_map(
            fn (string $code) => password_hash($code, \PASSWORD_BCRYPT),
            $codes
        );
    }

    /**
     * Verify a backup code against stored hashes.
     *
     * @param list<string> $hashedCodes
     *
     * @return int|false The index of the matching hash, or false
     */
    public function verifyBackupCode(string $code, array $hashedCodes): int|false
    {
        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($code, $hash)) {
                return $index;
            }
        }

        return false;
    }
}
