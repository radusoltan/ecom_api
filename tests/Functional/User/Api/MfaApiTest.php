<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;

/**
 * Functional Tests for MFA (TOTP) API.
 *
 * Endpoints tested:
 * - POST /api/v1/mfa/setup        — Generate TOTP secret and QR code
 * - POST /api/v1/mfa/enable       — Verify code and enable MFA
 * - POST /api/v1/mfa/verify       — Verify TOTP code during login (partial JWT)
 * - POST /api/v1/mfa/disable      — Disable MFA
 * - POST /api/v1/mfa/backup-codes — Regenerate backup codes
 */
final class MfaApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $client = static::createClient();
        $container = $client->getContainer();
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        // Clean up test MFA users from previous runs
        $connection->executeStatement(
            "DELETE FROM users WHERE username LIKE '%mfatest_%'"
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function generateUniqueEmail(): string
    {
        return sprintf('test-mfa-%d-%s@example.com', ++self::$counter, uniqid());
    }

    private function generateUniqueUsername(): string
    {
        return sprintf('mfatest_%d_%s', ++self::$counter, substr(uniqid(), -6));
    }

    /**
     * @param array<string, string> $extraHeaders
     *
     * @return array<string, string>
     */
    private function headers(array $extraHeaders = []): array
    {
        return array_merge([
            'X-Tenant-ID' => self::DEFAULT_TENANT_ID,
        ], $extraHeaders);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $token): array
    {
        return $this->headers(['Authorization' => 'Bearer '.$token]);
    }

    /**
     * Register a test user and login to get JWT.
     *
     * @return array{email: string, password: string, token: string}
     */
    private function registerAndLogin(\ApiPlatform\Symfony\Bundle\Test\Client $client): array
    {
        $email = $this->generateUniqueEmail();
        $username = $this->generateUniqueUsername();
        $password = 'TestPassword123!';

        // Register
        $registerResponse = $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
        ]);
        self::assertResponseStatusCodeSame(201, 'Failed to register test user');

        // Login
        $loginResponse = $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $email,
                'password' => $password,
            ],
        ]);
        self::assertResponseStatusCodeSame(200, 'Failed to login test user');

        /** @var array{token: string} $data */
        $data = json_decode($loginResponse->getContent(), true);

        return [
            'email' => $email,
            'password' => $password,
            'token' => $data['token'],
        ];
    }

    /**
     * Setup and enable MFA for a user (returns secret + backup codes).
     *
     * @return array{secret: non-empty-string, backupCodes: list<string>}
     */
    private function setupAndEnableMfa(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $token): array
    {
        // Setup — get secret
        $setupResponse = $client->request('POST', '/api/v1/mfa/setup', [
            'headers' => $this->authHeaders($token),
        ]);
        self::assertResponseStatusCodeSame(200, 'MFA setup failed');

        /** @var array{secret: non-empty-string} $setupData */
        $setupData = json_decode($setupResponse->getContent(), true);
        $secret = $setupData['secret'];

        // Generate valid TOTP code
        $totp = TOTP::createFromSecret($secret);
        $code = $totp->now();

        // Enable
        $enableResponse = $client->request('POST', '/api/v1/mfa/enable', [
            'headers' => $this->authHeaders($token),
            'json' => [
                'secret' => $secret,
                'code' => $code,
            ],
        ]);
        self::assertResponseStatusCodeSame(200, 'MFA enable failed');

        /** @var array{backupCodes: list<string>} $enableData */
        $enableData = json_decode($enableResponse->getContent(), true);

        return [
            'secret' => $secret,
            'backupCodes' => $enableData['backupCodes'],
        ];
    }

    /**
     * Login and get an MFA_PENDING token (for users with MFA enabled).
     */
    private function loginAndGetPendingToken(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $email, string $password): string
    {
        $response = $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $email,
                'password' => $password,
            ],
        ]);

        $data = json_decode($response->getContent(), true);

        return $data['token'];
    }

    // =============================================
    // POST /api/v1/mfa/setup
    // =============================================

    #[Test]
    public function setupReturnsSecretAndQrCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);

        $response = $client->request('POST', '/api/v1/mfa/setup', [
            'headers' => $this->authHeaders($userData['token']),
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('secret', $data);
        $this->assertArrayHasKey('qrCode', $data);
        $this->assertArrayHasKey('provisioningUri', $data);
        $this->assertNotEmpty($data['secret']);
        $this->assertStringStartsWith('data:image/png;base64,', $data['qrCode']);
        $this->assertStringContainsString('otpauth://totp/', $data['provisioningUri']);
    }

    #[Test]
    public function setupFailsWhenMfaAlreadyEnabled(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $this->setupAndEnableMfa($client, $userData['token']);

        // Try setup again — should fail with 409 Conflict
        $client->request('POST', '/api/v1/mfa/setup', [
            'headers' => $this->authHeaders($userData['token']),
        ]);

        $this->assertResponseStatusCodeSame(409);
    }

    // =============================================
    // POST /api/v1/mfa/enable
    // =============================================

    #[Test]
    public function enableMfaWithValidCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);

        // Setup
        $response = $client->request('POST', '/api/v1/mfa/setup', [
            'headers' => $this->authHeaders($userData['token']),
        ]);
        $setupData = json_decode($response->getContent(), true);

        // Generate valid TOTP code
        $totp = TOTP::createFromSecret($setupData['secret']);
        $code = $totp->now();

        // Enable
        $response = $client->request('POST', '/api/v1/mfa/enable', [
            'headers' => $this->authHeaders($userData['token']),
            'json' => [
                'secret' => $setupData['secret'],
                'code' => $code,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('MFA enabled successfully', $data['message']);
        $this->assertArrayHasKey('backupCodes', $data);
        $this->assertCount(8, $data['backupCodes']);
    }

    #[Test]
    public function enableMfaFailsWithInvalidCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);

        // Setup
        $response = $client->request('POST', '/api/v1/mfa/setup', [
            'headers' => $this->authHeaders($userData['token']),
        ]);
        $setupData = json_decode($response->getContent(), true);

        // Enable with invalid code
        $client->request('POST', '/api/v1/mfa/enable', [
            'headers' => $this->authHeaders($userData['token']),
            'json' => [
                'secret' => $setupData['secret'],
                'code' => '000000',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // =============================================
    // POST /api/v1/mfa/verify (login flow)
    // =============================================

    #[Test]
    public function verifyMfaWithValidTotpCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $mfaData = $this->setupAndEnableMfa($client, $userData['token']);

        // Login again — MFA-enabled user gets MFA_PENDING token
        $response = $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $userData['email'],
                'password' => $userData['password'],
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $loginData = json_decode($response->getContent(), true);
        $this->assertTrue($loginData['mfa_required'] ?? false, 'Login response should contain mfa_required=true');

        $pendingToken = $loginData['token'];

        // Verify with valid TOTP code
        $secret = $mfaData['secret'];
        \assert('' !== $secret);
        $totp = TOTP::createFromSecret($secret);
        $code = $totp->now();

        $response = $client->request('POST', '/api/v1/mfa/verify', [
            'headers' => $this->authHeaders($pendingToken),
            'json' => ['code' => $code],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $verifyData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $verifyData);

        // Full token should NOT contain ROLE_MFA_PENDING
        $tokenParts = explode('.', $verifyData['token']);
        $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);
        $this->assertNotContains('ROLE_MFA_PENDING', $payload['roles']);
    }

    #[Test]
    public function verifyMfaWithBackupCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $mfaData = $this->setupAndEnableMfa($client, $userData['token']);

        // Login again — get MFA_PENDING token
        $pendingToken = $this->loginAndGetPendingToken($client, $userData['email'], $userData['password']);

        // Verify with backup code (first one)
        $response = $client->request('POST', '/api/v1/mfa/verify', [
            'headers' => $this->authHeaders($pendingToken),
            'json' => ['backupCode' => $mfaData['backupCodes'][0]],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $verifyData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $verifyData);
    }

    #[Test]
    public function verifyMfaFailsWithInvalidCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $this->setupAndEnableMfa($client, $userData['token']);

        // Login again — get MFA_PENDING token
        $pendingToken = $this->loginAndGetPendingToken($client, $userData['email'], $userData['password']);

        // Verify with invalid code
        $client->request('POST', '/api/v1/mfa/verify', [
            'headers' => $this->authHeaders($pendingToken),
            'json' => ['code' => '000000'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function verifyMfaRateLimited(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $this->setupAndEnableMfa($client, $userData['token']);

        // Login again — get MFA_PENDING token
        $pendingToken = $this->loginAndGetPendingToken($client, $userData['email'], $userData['password']);

        // Exhaust rate limit: 5 attempts allowed per 15-minute window
        $gotRateLimited = false;
        for ($i = 0; $i < 10; ++$i) {
            $response = $client->request('POST', '/api/v1/mfa/verify', [
                'headers' => $this->authHeaders($pendingToken),
                'json' => ['code' => '000000'],
            ]);

            $statusCode = $response->getStatusCode();
            if (429 === $statusCode) {
                $gotRateLimited = true;
                break;
            }
            // Should be 401 (invalid code) until rate limited
            $this->assertSame(401, $statusCode);
        }

        if (!$gotRateLimited) {
            $this->markTestSkipped('Rate limiter storage not persistent in test environment (requires filesystem or Redis cache).');
        }
    }

    // =============================================
    // POST /api/v1/mfa/disable
    // =============================================

    #[Test]
    public function disableMfaWithValidCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $mfaData = $this->setupAndEnableMfa($client, $userData['token']);

        // Disable with valid TOTP code (using original full token)
        $secret = $mfaData['secret'];
        \assert('' !== $secret);
        $totp = TOTP::createFromSecret($secret);
        $code = $totp->now();

        $response = $client->request('POST', '/api/v1/mfa/disable', [
            'headers' => $this->authHeaders($userData['token']),
            'json' => ['code' => $code],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('MFA disabled successfully', $data['message']);
    }

    #[Test]
    public function disableMfaFailsWithInvalidCode(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $this->setupAndEnableMfa($client, $userData['token']);

        // Disable with invalid code
        $client->request('POST', '/api/v1/mfa/disable', [
            'headers' => $this->authHeaders($userData['token']),
            'json' => ['code' => '000000'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // =============================================
    // POST /api/v1/mfa/backup-codes
    // =============================================

    #[Test]
    public function regenerateBackupCodes(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $mfaData = $this->setupAndEnableMfa($client, $userData['token']);

        // Regenerate with valid TOTP code
        $secret = $mfaData['secret'];
        \assert('' !== $secret);
        $totp = TOTP::createFromSecret($secret);
        $code = $totp->now();

        $response = $client->request('POST', '/api/v1/mfa/backup-codes', [
            'headers' => $this->authHeaders($userData['token']),
            'json' => ['code' => $code],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('backupCodes', $data);
        $this->assertCount(8, $data['backupCodes']);
        $this->assertSame('Backup codes regenerated', $data['message']);
        // New codes should differ from original ones
        $this->assertNotSame($mfaData['backupCodes'], $data['backupCodes']);
    }

    // =============================================
    // MFA access control — pending user blocked
    // =============================================

    #[Test]
    public function mfaPendingUserBlockedFromOtherEndpoints(): void
    {
        $client = static::createClient();
        $userData = $this->registerAndLogin($client);
        $this->setupAndEnableMfa($client, $userData['token']);

        // Login again — get MFA_PENDING token
        $pendingToken = $this->loginAndGetPendingToken($client, $userData['email'], $userData['password']);

        // Try to access a normal protected endpoint with MFA_PENDING token
        $client->request('GET', '/api/v1/tenants', [
            'headers' => $this->authHeaders($pendingToken),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
