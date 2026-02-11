<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Functional Tests for User Login API.
 *
 * Tests the login endpoint:
 * - POST /api/login_check (JWT authentication)
 *
 * Test cases:
 * 1. Successful login with valid credentials
 * 2. Invalid credentials rejection (401 Unauthorized)
 * 3. Non-existent user rejection (401 Unauthorized)
 * 4. JWT token works for protected endpoints
 *
 * Based on Epic 2: JWT Authentication (Task 2.5 - Functional Tests)
 */
final class LoginApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up test users from previous runs
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        // Clean up test users (users table doesn't have RLS)
        $connection->executeStatement(
            "DELETE FROM users WHERE email LIKE 'test-login-%@example.com'"
        );
    }

    /**
     * Generate a unique email address for testing.
     */
    private function generateUniqueEmail(string $prefix = 'test-login'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Generate a unique username for testing.
     */
    private function generateUniqueUsername(string $prefix = 'loginuser'): string
    {
        return sprintf('%s_%d_%s', $prefix, ++self::$counter, substr(uniqid(), -6));
    }

    /**
     * Build headers array with X-Tenant-ID always included.
     *
     * @param array<string, string> $extraHeaders Additional headers to merge
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
     * Create a test user using the registration API.
     *
     * @return array{email: string, username: string, password: string}
     */
    private function createTestUserViaRegistration(\ApiPlatform\Symfony\Bundle\Test\Client $client): array
    {
        $email = $this->generateUniqueEmail();
        $username = $this->generateUniqueUsername();
        $password = 'TestPassword123!';

        // Register user via API
        $response = $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        // Ensure registration succeeded
        if ($client->getResponse()->getStatusCode() !== 201) {
            throw new \RuntimeException('Failed to create test user: ' . $client->getResponse()->getContent());
        }

        return [
            'email' => $email,
            'username' => $username,
            'password' => $password,
        ];
    }

    // =============================================
    // Test: Successful Login
    // =============================================

    public function testItLogsInWithValidCredentials(): void
    {
        $client = static::createClient();

        // Create test user via registration API
        $userData = $this->createTestUserViaRegistration($client);

        // Login with valid credentials
        $response = $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $userData['email'], // Email is used as username
                'password' => $userData['password'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($response->getContent(), true);

        // Verify JWT token in response
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);

        // Token should be a valid JWT (3 parts separated by dots)
        $tokenParts = explode('.', $data['token']);
        $this->assertCount(3, $tokenParts, 'JWT token should have 3 parts (header.payload.signature)');
    }

    // =============================================
    // Test: Invalid Credentials
    // =============================================

    public function testItRejectsInvalidCredentials(): void
    {
        $client = static::createClient();

        // Create test user via registration API
        $userData = $this->createTestUserViaRegistration($client);

        // Login with WRONG password
        $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $userData['email'],
                'password' => 'WrongPassword123!', // Wrong password
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // =============================================
    // Test: Non-existent User
    // =============================================

    public function testItRejectsNonExistentUser(): void
    {
        $client = static::createClient();

        // Try to login with non-existent email
        $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => 'nonexistent-' . uniqid() . '@example.com',
                'password' => 'SomePassword123!',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // =============================================
    // Test: JWT Token Can Access Protected Endpoints
    // =============================================

    public function testJwtTokenCanAccessProtectedEndpoints(): void
    {
        $client = static::createClient();

        // Create test user via registration API
        $userData = $this->createTestUserViaRegistration($client);

        // Login to get JWT token
        $loginResponse = $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $userData['email'],
                'password' => $userData['password'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);

        $loginData = json_decode($loginResponse->getContent(), true);
        $token = $loginData['token'];

        // Verify token is a valid JWT format
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $token);

        // Use token to access a protected endpoint (e.g., payments)
        $client->request('GET', '/api/v1/payments', [
            'headers' => array_merge(
                $this->headers(),
                ['Authorization' => 'Bearer ' . $token]
            ),
        ]);

        // Should be able to access protected endpoint (200 or 403 if no permission, but NOT 401)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(401, $statusCode, 'Token should authenticate user (not 401 Unauthorized)');
    }

    // =============================================
    // Test: Login Without Credentials
    // =============================================

    public function testItRejectsLoginWithoutCredentials(): void
    {
        $client = static::createClient();

        // Try to login without username/password
        $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Login with Empty Password
    // =============================================

    public function testItRejectsLoginWithEmptyPassword(): void
    {
        $client = static::createClient();

        // Create test user via registration API
        $userData = $this->createTestUserViaRegistration($client);

        // Try to login with empty password
        $client->request('POST', '/api/login_check', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $userData['email'],
                'password' => '', // Empty password
            ],
        ]);

        // Empty password should be rejected with 400 or 401
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [400, 401], true),
            sprintf('Expected 400 or 401 for empty password, got %d', $statusCode)
        );
    }
}
