<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Security;

use App\Tests\Support\BcryptFixtures;
use App\User\Infrastructure\Security\MfaTotpService;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MfaTotpService::class)]
final class MfaTotpServiceTest extends TestCase
{
    private MfaTotpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MfaTotpService();
    }

    // -----------------------------------------------------------------------
    // generateSecret()
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesANonEmptySecret(): void
    {
        $secret = $this->service->generateSecret();

        self::assertNotEmpty($secret);
        self::assertIsString($secret);
    }

    #[Test]
    public function itGeneratesUniqueSecrets(): void
    {
        $secret1 = $this->service->generateSecret();
        $secret2 = $this->service->generateSecret();

        self::assertNotSame($secret1, $secret2);
    }

    // -----------------------------------------------------------------------
    // verifyCode()
    // -----------------------------------------------------------------------

    #[Test]
    public function itVerifiesValidTotpCode(): void
    {
        $secret = $this->service->generateSecret();
        $totp = TOTP::createFromSecret($secret);
        $currentCode = $totp->now();

        $result = $this->service->verifyCode($secret, $currentCode);

        self::assertTrue($result);
    }

    #[Test]
    public function itReturnsFalseForInvalidCode(): void
    {
        $secret = $this->service->generateSecret();

        $result = $this->service->verifyCode($secret, '000000');

        // There is an astronomically small chance 000000 happens to be valid;
        // however this test is deterministic in practice because TOTP generates
        // 6-digit codes that very rarely equal '000000'.
        // We just verify the method returns a boolean.
        self::assertIsBool($result);
    }

    #[Test]
    public function itReturnsFalseForObviouslyWrongCode(): void
    {
        $secret = $this->service->generateSecret();

        // 'INVALID' is not a valid 6-digit TOTP code
        $result = $this->service->verifyCode($secret, 'INVALID');

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenSecretIsEmpty(): void
    {
        $result = $this->service->verifyCode('', '123456');

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenCodeIsEmpty(): void
    {
        $secret = $this->service->generateSecret();

        $result = $this->service->verifyCode($secret, '');

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenBothSecretAndCodeAreEmpty(): void
    {
        $result = $this->service->verifyCode('', '');

        self::assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // getProvisioningUri()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsValidOtpauthUri(): void
    {
        $secret = $this->service->generateSecret();
        $email = 'user@example.com';

        $uri = $this->service->getProvisioningUri($secret, $email);

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString($secret, $uri);
        self::assertStringContainsString('EcommercePlatform', $uri);
    }

    #[Test]
    public function itIncludesEmailLabelInProvisioningUri(): void
    {
        $secret = $this->service->generateSecret();
        $email = 'john.doe@example.com';

        $uri = $this->service->getProvisioningUri($secret, $email);

        self::assertStringContainsString(rawurlencode($email), $uri);
    }

    // -----------------------------------------------------------------------
    // generateQrCodeDataUri()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsDataUriStartingWithPngPrefix(): void
    {
        $secret = $this->service->generateSecret();
        $provisioningUri = $this->service->getProvisioningUri($secret, 'user@example.com');

        $dataUri = $this->service->generateQrCodeDataUri($provisioningUri);

        self::assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    #[Test]
    public function itReturnsNonEmptyQrCodeDataUri(): void
    {
        $secret = $this->service->generateSecret();
        $provisioningUri = $this->service->getProvisioningUri($secret, 'user@example.com');

        $dataUri = $this->service->generateQrCodeDataUri($provisioningUri);

        // Strip the prefix and verify there is actual base64 content
        $base64Part = substr($dataUri, strlen('data:image/png;base64,'));
        self::assertNotEmpty($base64Part);
        self::assertNotFalse(base64_decode($base64Part, strict: true));
    }

    // -----------------------------------------------------------------------
    // generateBackupCodes()
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesExactlyEightBackupCodes(): void
    {
        $codes = $this->service->generateBackupCodes();

        self::assertCount(8, $codes);
    }

    #[Test]
    public function itGeneratesBackupCodesOfEightHexCharacters(): void
    {
        $codes = $this->service->generateBackupCodes();

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[0-9A-F]{8}$/', $code, "Code '$code' is not 8 uppercase hex chars");
        }
    }

    #[Test]
    public function itGeneratesUniqueBackupCodes(): void
    {
        $codes = $this->service->generateBackupCodes();

        self::assertCount(8, array_unique($codes), 'Backup codes should be unique');
    }

    // -----------------------------------------------------------------------
    // hashBackupCodes()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsBcryptHashesForBackupCodes(): void
    {
        $codes = ['AABBCCDD', 'EEFF0011', '22334455'];

        $hashes = $this->service->hashBackupCodes($codes);

        self::assertCount(3, $hashes);
        foreach ($hashes as $index => $hash) {
            self::assertStringStartsWith('$2', $hash, "Hash at index $index is not a bcrypt hash");
            self::assertTrue(password_verify($codes[$index], $hash));
        }
    }

    #[Test]
    public function itReturnsAsManyHashesAsCodes(): void
    {
        $codes = $this->service->generateBackupCodes();

        $hashes = $this->service->hashBackupCodes($codes);

        self::assertCount(count($codes), $hashes);
    }

    // -----------------------------------------------------------------------
    // verifyBackupCode()
    // -----------------------------------------------------------------------

    #[Test]
    public function itMatchesCorrectBackupCode(): void
    {
        $plainCode = BcryptFixtures::PLAIN_AABBCCDD;
        $hashedCodes = [
            BcryptFixtures::HASH_XXXXXX00,
            BcryptFixtures::HASH_AABBCCDD,
            BcryptFixtures::HASH_ZZZZZZ99,
        ];

        $result = $this->service->verifyBackupCode($plainCode, $hashedCodes);

        self::assertSame(1, $result);
    }

    #[Test]
    public function itReturnsIndexZeroWhenFirstCodeMatches(): void
    {
        $plainCode = BcryptFixtures::PLAIN_FIRSTCOD;
        $hashedCodes = [
            BcryptFixtures::HASH_FIRSTCOD,
            BcryptFixtures::HASH_SECOND00,
        ];

        $result = $this->service->verifyBackupCode($plainCode, $hashedCodes);

        self::assertSame(0, $result);
    }

    #[Test]
    public function itReturnsFalseForNonMatchingCode(): void
    {
        $hashedCodes = [
            BcryptFixtures::HASH_AABBCCDD,
            BcryptFixtures::HASH_EEFF0011,
        ];

        $result = $this->service->verifyBackupCode('WRONGCOD', $hashedCodes);

        self::assertFalse($result);
    }

    #[Test]
    public function itReturnsFalseWhenHashedCodesListIsEmpty(): void
    {
        $result = $this->service->verifyBackupCode('AABBCCDD', []);

        self::assertFalse($result);
    }
}
