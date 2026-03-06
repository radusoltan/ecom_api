<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security\Voter;

use App\Shared\Infrastructure\Security\Voter\AbstractResourceVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Tests AbstractResourceVoter via a concrete implementation.
 */
#[CoversClass(AbstractResourceVoter::class)]
#[Group('shared')]
final class AbstractResourceVoterTest extends TestCase
{
    private ConcreteTestVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ConcreteTestVoter();
    }

    #[Test]
    public function itGrantsAccessToSuperAdmin(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, ['test.view']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, ['test.edit']));
    }

    #[Test]
    public function itDeniesAccessToUnauthenticatedUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $token->method('getRoleNames')->willReturn([]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, ['test.view']));
    }

    #[Test]
    public function itDeniesAccessToAuthenticatedUserWithNoSpecialRole(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, ['test.view']));
    }

    #[Test]
    public function itAbstainsOnUnsupportedAttribute(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, null, ['unsupported.attribute']));
    }

    #[Test]
    public function hasRoleReturnsTrueForMatchingRole(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_ADMIN', 'ROLE_MANAGER']);

        self::assertTrue($this->voter->testHasRole($token, 'ROLE_ADMIN'));
        self::assertTrue($this->voter->testHasRole($token, 'ROLE_MANAGER'));
        self::assertFalse($this->voter->testHasRole($token, 'ROLE_SUPER_ADMIN'));
    }

    #[Test]
    public function hasAnyRoleReturnsTrueWhenAtLeastOneMatches(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_VIEWER']);

        self::assertTrue($this->voter->testHasAnyRole($token, ['ROLE_VIEWER', 'ROLE_ADMIN']));
        self::assertFalse($this->voter->testHasAnyRole($token, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']));
    }

    #[Test]
    public function hasAllRolesReturnsTrueOnlyWhenAllMatch(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_ADMIN', 'ROLE_MANAGER']);

        self::assertTrue($this->voter->testHasAllRoles($token, ['ROLE_ADMIN', 'ROLE_MANAGER']));
        self::assertFalse($this->voter->testHasAllRoles($token, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']));
    }

    #[Test]
    public function isAdminReturnsTrueForAdminAndSuperAdmin(): void
    {
        $adminToken = $this->createTokenWithRoles(['ROLE_ADMIN']);
        $superAdminToken = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);
        $managerToken = $this->createTokenWithRoles(['ROLE_MANAGER']);

        self::assertTrue($this->voter->testIsAdmin($adminToken));
        self::assertTrue($this->voter->testIsAdmin($superAdminToken));
        self::assertFalse($this->voter->testIsAdmin($managerToken));
    }

    #[Test]
    public function hasAdminPrivilegesCoversAllAdminRoles(): void
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_TENANT_ADMIN'] as $role) {
            $token = $this->createTokenWithRoles([$role]);
            self::assertTrue($this->voter->testHasAdminPrivileges($token), "Expected {$role} to have admin privileges");
        }

        $customerToken = $this->createTokenWithRoles(['ROLE_CUSTOMER']);
        self::assertFalse($this->voter->testHasAdminPrivileges($customerToken));
    }

    // -------------------------------------------------------

    private function createTokenWithRoles(array $roles): TokenInterface
    {
        $user = $this->createMock(UserInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getRoleNames')->willReturn($roles);

        return $token;
    }
}

/**
 * Concrete subclass for testing AbstractResourceVoter.
 */
final class ConcreteTestVoter extends AbstractResourceVoter
{
    protected function getResourceName(): string
    {
        return 'test';
    }

    protected function getSupportedAttributes(): array
    {
        return ['test.view', 'test.edit'];
    }

    // Expose protected methods for testing
    public function testHasRole(TokenInterface $token, string $role): bool
    {
        return $this->hasRole($token, $role);
    }

    public function testHasAnyRole(TokenInterface $token, array $roles): bool
    {
        return $this->hasAnyRole($token, $roles);
    }

    public function testHasAllRoles(TokenInterface $token, array $roles): bool
    {
        return $this->hasAllRoles($token, $roles);
    }

    public function testIsAdmin(TokenInterface $token): bool
    {
        return $this->isAdmin($token);
    }

    public function testHasAdminPrivileges(TokenInterface $token): bool
    {
        return $this->hasAdminPrivileges($token);
    }
}
