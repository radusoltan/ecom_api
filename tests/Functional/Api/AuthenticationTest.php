<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * Functional Tests for Authentication Flow
 *
 * Tests JWT authentication with protected endpoints:
 * - Token generation and usage
 * - Protected endpoint access with JWT tokens
 * - Token validation
 * - Role-based access
 */
final class AuthenticationTest extends ApiTestCase
{
    /**
     * Create an authenticated client with JWT token
     *
     * @param string $email User email
     * @param array $roles User roles
     * @return \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    protected function createAuthenticatedClient(string $email = 'admin@admin.com', array $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'])
    {
        // First, create a temporary client to access services
        $tempClient = static::createClient();
        $container = $tempClient->getContainer();

        // Create user in database for JWT authentication (if not exists)
        $entityManager = $container->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(\App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity::class);

        $existingUser = $userRepository->findOneBy(['email' => $email]);

        if (!$existingUser) {
            $userEntity = new \App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity();
            $userEntity->setId(\Symfony\Component\Uid\Uuid::v4()->toString());
            $userEntity->setEmail($email);
            $userEntity->setUsername(explode('@', $email)[0] . '-' . bin2hex(random_bytes(4)));
            $userEntity->setPassword('$2y$13$dummy.password.hash'); // Dummy password (not used for JWT)
            $userEntity->setRoles($roles);
            $userEntity->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($userEntity);
            $entityManager->flush();
        }

        // Generate JWT token using Lexik encoder for testing
        $encoder = $container->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        // Create authenticated client with token in headers (API Platform recommended way)
        return static::createClient([], ['headers' => ['authorization' => 'Bearer ' . $token]]);
    }

    // ========================================================================
    // Protected Endpoint Access Tests
    // ========================================================================

    public function testAccessProtectedEndpointWithValidTokenSucceeds(): void
    {
        // Arrange
        $client = $this->createAuthenticatedClient();

        // Act
        $client->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAccessProtectedEndpointWithoutTokenReturns401(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/tenants', [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(401);
        $data = json_decode($client->getResponse()->getContent(false), true);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertSame(401, $data['code']);
    }

    public function testAccessProtectedEndpointWithInvalidTokenReturns401(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/tenants', [
            'headers' => [
                'Authorization' => 'Bearer invalid.token.here',
                'Accept' => 'application/json',
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedEndpointWithMalformedTokenReturns401(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('GET', '/api/tenants', [
            'headers' => [
                'Authorization' => 'Bearer notavalidtoken',
                'Accept' => 'application/json',
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedEndpointWithoutBearerPrefixReturns401(): void
    {
        // Arrange - Generate token but send without Bearer prefix
        $client = static::createClient();
        $encoder = $client->getContainer()->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => 'admin@admin.com',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        // Act - Send token without Bearer prefix
        $client->request('GET', '/api/tenants', [
            'headers' => [
                'Authorization' => $token, // No Bearer prefix
                'Accept' => 'application/json',
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedEndpointWithExpiredTokenReturns401(): void
    {
        // Arrange - Generate expired token
        $client = static::createClient();
        $encoder = $client->getContainer()->get('lexik_jwt_authentication.encoder');

        $token = $encoder->encode([
            'email' => 'admin@admin.com',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
            'iat' => time() - 7200, // 2 hours ago
            'exp' => time() - 3600, // Expired 1 hour ago
        ]);

        // Act
        $client->request('GET', '/api/tenants', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    // ========================================================================
    // Token Claims and Roles Tests
    // ========================================================================

    public function testTokenWithSuperAdminRoleCanAccessEndpoints(): void
    {
        // Arrange
        $client = $this->createAuthenticatedClient('admin@admin.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER']);

        // Act
        $client->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testTokenWithUserRoleCanAccessEndpoints(): void
    {
        // Arrange
        $client = $this->createAuthenticatedClient('user@example.com', ['ROLE_USER']);

        // Act
        $client->request('GET', '/api/tenants');

        // Assert
        $this->assertResponseIsSuccessful();
    }

    public function testGeneratedTokenContainsCorrectClaims(): void
    {
        // Arrange
        $client = static::createClient();
        $encoder = $client->getContainer()->get('lexik_jwt_authentication.encoder');

        $email = 'test@example.com';
        $roles = ['ROLE_ADMIN', 'ROLE_USER'];

        // Act
        $token = $encoder->encode([
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        // Assert - Decode and verify claims
        $tokenParts = explode('.', $token);
        $this->assertCount(3, $tokenParts);

        $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertArrayHasKey('email', $payload);

        $this->assertSame($email, $payload['email']);
        $this->assertSame($roles, $payload['roles']);
    }

    public function testTokenIsValidJWTFormat(): void
    {
        // Arrange
        $client = static::createClient();
        $encoder = $client->getContainer()->get('lexik_jwt_authentication.encoder');

        // Act
        $token = $encoder->encode([
            'email' => 'admin@admin.com',
            'roles' => ['ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        // Assert - Verify JWT format (3 parts separated by dots)
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $token);
    }

    // ========================================================================
    // Integration Tests - Multiple Operations
    // ========================================================================

    public function testMultipleRequestsWithSameTokenSucceed(): void
    {
        // Arrange
        $client = $this->createAuthenticatedClient();

        // Act & Assert - Make multiple requests with same token
        for ($i = 0; $i < 3; $i++) {
            $client->request('GET', '/api/tenants');
            $this->assertResponseIsSuccessful();
        }
    }

    public function testAuthenticatedUserCanCreateTenant(): void
    {
        // Arrange
        $client = $this->createAuthenticatedClient();
        $uniqueEmail = 'authtest-' . bin2hex(random_bytes(4)) . '@example.com';

        // Act
        $client->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'Auth Test Company',
                'ownerEmail' => $uniqueEmail,
            ],
        ]);

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Auth Test Company', $data['name']);
    }

    public function testAuthenticatedUserCanActivateTenant(): void
    {
        // Arrange - Create a tenant first
        $client = $this->createAuthenticatedClient();
        $uniqueEmail = 'activate-' . bin2hex(random_bytes(4)) . '@example.com';

        $createResponse = $client->request('POST', '/api/tenants', [
            'json' => [
                'name' => 'Activate Test Company',
                'ownerEmail' => $uniqueEmail,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $createData = json_decode($createResponse->getContent(), true);
        $tenantId = $createData['id'];

        // Deactivate first
        $client->request('PATCH', "/api/tenants/$tenantId/deactivate", [
            'json' => [],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Act - Activate with authenticated client
        $client->request('PATCH', "/api/tenants/$tenantId/activate", [
            'json' => [],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('active', $data['status']);
    }

    public function testDifferentUsersCanAccessSameEndpointWithValidTokens(): void
    {
        // Arrange & Act & Assert - User 1
        $client1 = $this->createAuthenticatedClient('user1@example.com', ['ROLE_USER']);
        $client1->request('GET', '/api/tenants');
        $this->assertResponseIsSuccessful();

        // Arrange & Act & Assert - User 2
        $client2 = $this->createAuthenticatedClient('user2@example.com', ['ROLE_USER']);
        $client2->request('GET', '/api/tenants');
        $this->assertResponseIsSuccessful();

        // Arrange & Act & Assert - Admin
        $client3 = $this->createAuthenticatedClient('admin@example.com', ['ROLE_SUPER_ADMIN', 'ROLE_USER']);
        $client3->request('GET', '/api/tenants');
        $this->assertResponseIsSuccessful();
    }
}
