<?php

declare(strict_types=1);

namespace App\Tests\Functional\User\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;

/**
 * Functional Tests for User Registration API.
 *
 * Tests the registration endpoint:
 * - POST /api/v1/auth/register (User registration)
 *
 * Test cases:
 * 1. Successful registration with valid data
 * 2. Duplicate email rejection (409 Conflict)
 * 3. Duplicate username rejection (409 Conflict)
 * 4. Password validation (min 8 chars)
 * 5. Email validation
 * 6. Missing required fields
 * 7. JWT token authentication after registration
 * 8. Missing tenant ID header
 *
 * Based on Epic 2: JWT Authentication (Task 2.5 - Functional Tests)
 */
final class RegistrationApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up users from previous tests
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();

        // Clean up test users (users table doesn't have RLS)
        $connection->executeStatement(
            "DELETE FROM users WHERE email LIKE 'test-register-%@example.com'"
        );
    }

    /**
     * Generate a unique email address for testing.
     */
    private function generateUniqueEmail(string $prefix = 'test-register'): string
    {
        return sprintf('%s-%d-%s@example.com', $prefix, ++self::$counter, uniqid());
    }

    /**
     * Generate a unique username for testing.
     */
    private function generateUniqueUsername(string $prefix = 'testuser'): string
    {
        return sprintf('%s_%d_%s', $prefix, ++self::$counter, substr(uniqid(), -6));
    }

    /**
     * Build headers array with X-Tenant-ID always included.
     *
     * For API Platform's test client, headers use kebab-case (X-Tenant-ID).
     * The HTTP_ prefix is NOT used with API Platform test client.
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

    // =============================================
    // Test: Successful Registration
    // =============================================

    public function testItRegistersNewUser(): void
    {
        $client = static::createClient();

        $email = $this->generateUniqueEmail();
        $username = $this->generateUniqueUsername();
        $password = 'SecurePassword123!';

        $response = $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($response->getContent(), true);

        // Verify response structure - user object contains id, email, username, roles
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('token', $data); // JWT token should be returned
        $this->assertArrayHasKey('refreshToken', $data); // Refresh token should be returned

        // Verify user data nested in 'user' key
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertArrayHasKey('email', $data['user']);
        $this->assertArrayHasKey('username', $data['user']);
        $this->assertArrayHasKey('roles', $data['user']);

        $this->assertEquals($email, $data['user']['email']);
        $this->assertEquals($username, $data['user']['username']);
        $this->assertNotEmpty($data['token']);
        $this->assertNotEmpty($data['refreshToken']);

        // Password should NOT be in response
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('password', $data['user']);
    }

    // =============================================
    // Test: Duplicate Email Rejection
    // =============================================

    public function testItRejectsRegistrationWithExistingEmail(): void
    {
        $client = static::createClient();

        $email = $this->generateUniqueEmail('duplicate-email');
        $username1 = $this->generateUniqueUsername('user1');
        $username2 = $this->generateUniqueUsername('user2');
        $password = 'SecurePassword123!';

        // First registration - should succeed
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email,
                'username' => $username1,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // Second registration with same email - should fail with 409 Conflict
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email, // Same email
                'username' => $username2, // Different username
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(409);
    }

    // =============================================
    // Test: Duplicate Username Rejection
    // =============================================

    public function testItRejectsRegistrationWithExistingUsername(): void
    {
        $client = static::createClient();

        $email1 = $this->generateUniqueEmail('user1');
        $email2 = $this->generateUniqueEmail('user2');
        $username = $this->generateUniqueUsername('duplicate-username');
        $password = 'SecurePassword123!';

        // First registration - should succeed
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email1,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // Second registration with same username - should fail with 409 Conflict
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email2, // Different email
                'username' => $username, // Same username
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(409);
    }

    // =============================================
    // Test: Password Validation (Min 8 chars)
    // =============================================

    public function testItRejectsRegistrationWithShortPassword(): void
    {
        $client = static::createClient();

        $email = $this->generateUniqueEmail('short-password');
        $username = $this->generateUniqueUsername('shortpass');
        $password = 'Short1!'; // Only 7 characters (< 8 minimum)

        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Email Validation
    // =============================================

    public function testItRejectsRegistrationWithInvalidEmail(): void
    {
        $client = static::createClient();

        $invalidEmail = 'not-a-valid-email'; // Missing @domain
        $username = $this->generateUniqueUsername('invalid-email');
        $password = 'SecurePassword123!';

        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $invalidEmail,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: Missing Required Fields
    // =============================================

    public function testItRejectsRegistrationWithMissingFields(): void
    {
        $client = static::createClient();

        // Missing email
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'username' => $this->generateUniqueUsername(),
                'password' => 'SecurePassword123!',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Missing username
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $this->generateUniqueEmail(),
                'password' => 'SecurePassword123!',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        // Missing password
        $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $this->generateUniqueEmail(),
                'username' => $this->generateUniqueUsername(),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }

    // =============================================
    // Test: JWT Token Works After Registration
    // =============================================

    public function testRegisteredUserCanAuthenticateWithReturnedToken(): void
    {
        $client = static::createClient();

        $email = $this->generateUniqueEmail('token-test');
        $username = $this->generateUniqueUsername('tokentest');
        $password = 'SecurePassword123!';

        // Register user
        $registerResponse = $client->request('POST', '/api/v1/auth/register', [
            'headers' => $this->headers(),
            'json' => [
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $registerData = json_decode($registerResponse->getContent(), true);
        $token = $registerData['token'];

        // Verify the token is a valid JWT format (header.payload.signature)
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $token);

        // The token should be usable for authentication
        // We just verify the token format is valid here as we don't have a profile endpoint yet
        $this->assertNotEmpty($token);
    }

    // =============================================
    // Test: Missing Tenant ID Header
    // =============================================

    public function testRegistrationRequiresTenantId(): void
    {
        $client = static::createClient();

        $email = $this->generateUniqueEmail('no-tenant');
        $username = $this->generateUniqueUsername('notenant');
        $password = 'SecurePassword123!';

        // Request WITHOUT X-Tenant-ID header
        $client->request('POST', '/api/v1/auth/register', [
            'json' => [
                'email' => $email,
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
