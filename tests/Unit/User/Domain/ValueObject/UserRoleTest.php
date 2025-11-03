<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\ValueObject;

use App\User\Domain\ValueObject\UserRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserRoleTest extends TestCase
{
    // ==================== Admin Panel Roles Tests ====================

    public function testUserRoleCanBeCreatedWithRoleUser(): void
    {
        $role = UserRole::user();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_USER', $role->toString());
        $this->assertSame('ROLE_USER', (string) $role);
    }

    public function testUserRoleCanBeCreatedWithRoleAdmin(): void
    {
        $role = UserRole::admin();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_ADMIN', $role->toString());
        $this->assertTrue($role->isAdmin());
    }

    public function testUserRoleCanBeCreatedWithRoleSuperAdmin(): void
    {
        $role = UserRole::superAdmin();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_SUPER_ADMIN', $role->toString());
        $this->assertTrue($role->isSuperAdmin());
        $this->assertTrue($role->isAdmin());
    }

    public function testUserRoleCanBeCreatedWithRoleManager(): void
    {
        $role = UserRole::manager();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_MANAGER', $role->toString());
        $this->assertTrue($role->isManager());
        $this->assertFalse($role->isAdmin());
        $this->assertTrue($role->hasAdminPrivileges());
    }

    public function testUserRoleCanBeCreatedWithRoleViewer(): void
    {
        $role = UserRole::viewer();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_VIEWER', $role->toString());
        $this->assertTrue($role->isViewer());
        $this->assertTrue($role->isReadOnly());
        $this->assertFalse($role->hasAdminPrivileges());
    }

    // ==================== Storefront & Business Roles Tests ====================

    public function testUserRoleCanBeCreatedWithRoleCustomer(): void
    {
        $role = UserRole::customer();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_CUSTOMER', $role->toString());
        $this->assertTrue($role->isCustomer());
        $this->assertFalse($role->hasAdminPrivileges());
    }

    public function testUserRoleCanBeCreatedWithRoleTenantAdmin(): void
    {
        $role = UserRole::tenantAdmin();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_TENANT_ADMIN', $role->toString());
        $this->assertTrue($role->isTenantAdmin());
        $this->assertTrue($role->hasAdminPrivileges());
    }

    public function testUserRoleCanBeCreatedWithRoleTenantUser(): void
    {
        $role = UserRole::tenantUser();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_TENANT_USER', $role->toString());
        $this->assertTrue($role->isTenantUser());
        $this->assertFalse($role->hasAdminPrivileges());
    }

    public function testUserRoleCanBeCreatedWithRoleVendor(): void
    {
        $role = UserRole::vendor();

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_VENDOR', $role->toString());
        $this->assertTrue($role->isVendor());
        $this->assertFalse($role->hasAdminPrivileges());
    }

    // ==================== fromString Tests ====================

    public function testUserRoleCanBeCreatedFromString(): void
    {
        $role = UserRole::fromString('ROLE_ADMIN');

        $this->assertInstanceOf(UserRole::class, $role);
        $this->assertSame('ROLE_ADMIN', $role->toString());
    }

    public function testFromStringAcceptsAllValidRoles(): void
    {
        $validRoles = [
            'ROLE_USER',
            'ROLE_ADMIN',
            'ROLE_SUPER_ADMIN',
            'ROLE_MANAGER',
            'ROLE_VIEWER',
            'ROLE_CUSTOMER',
            'ROLE_TENANT_ADMIN',
            'ROLE_TENANT_USER',
            'ROLE_VENDOR',
        ];

        foreach ($validRoles as $roleString) {
            $role = UserRole::fromString($roleString);
            $this->assertSame($roleString, $role->toString());
        }
    }

    public function testFromStringThrowsExceptionForInvalidRole(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user role: "ROLE_INVALID"');

        UserRole::fromString('ROLE_INVALID');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user role');

        UserRole::fromString('');
    }

    // ==================== Equality Tests ====================

    public function testTwoUserRolesWithSameValueAreEqual(): void
    {
        $role1 = UserRole::admin();
        $role2 = UserRole::admin();

        $this->assertTrue($role1->equals($role2));
    }

    public function testTwoUserRolesWithDifferentValuesAreNotEqual(): void
    {
        $role1 = UserRole::admin();
        $role2 = UserRole::manager();

        $this->assertFalse($role1->equals($role2));
    }

    public function testUserRoleFromStringEqualsFactoryMethod(): void
    {
        $role1 = UserRole::fromString('ROLE_MANAGER');
        $role2 = UserRole::manager();

        $this->assertTrue($role1->equals($role2));
    }

    // ==================== Helper Methods Tests ====================

    public function testIsSuperAdminReturnsTrueOnlyForSuperAdmin(): void
    {
        $this->assertTrue(UserRole::superAdmin()->isSuperAdmin());
        $this->assertFalse(UserRole::admin()->isSuperAdmin());
        $this->assertFalse(UserRole::manager()->isSuperAdmin());
        $this->assertFalse(UserRole::viewer()->isSuperAdmin());
        $this->assertFalse(UserRole::user()->isSuperAdmin());
    }

    public function testIsAdminReturnsTrueForAdminAndSuperAdmin(): void
    {
        $this->assertTrue(UserRole::superAdmin()->isAdmin());
        $this->assertTrue(UserRole::admin()->isAdmin());
        $this->assertFalse(UserRole::manager()->isAdmin());
        $this->assertFalse(UserRole::viewer()->isAdmin());
        $this->assertFalse(UserRole::user()->isAdmin());
    }

    public function testIsManagerReturnsTrueOnlyForManager(): void
    {
        $this->assertTrue(UserRole::manager()->isManager());
        $this->assertFalse(UserRole::admin()->isManager());
        $this->assertFalse(UserRole::superAdmin()->isManager());
        $this->assertFalse(UserRole::viewer()->isManager());
    }

    public function testIsViewerReturnsTrueOnlyForViewer(): void
    {
        $this->assertTrue(UserRole::viewer()->isViewer());
        $this->assertFalse(UserRole::manager()->isViewer());
        $this->assertFalse(UserRole::admin()->isViewer());
        $this->assertFalse(UserRole::user()->isViewer());
    }

    public function testIsCustomerReturnsTrueOnlyForCustomer(): void
    {
        $this->assertTrue(UserRole::customer()->isCustomer());
        $this->assertFalse(UserRole::user()->isCustomer());
        $this->assertFalse(UserRole::vendor()->isCustomer());
    }

    public function testIsTenantAdminReturnsTrueOnlyForTenantAdmin(): void
    {
        $this->assertTrue(UserRole::tenantAdmin()->isTenantAdmin());
        $this->assertFalse(UserRole::admin()->isTenantAdmin());
        $this->assertFalse(UserRole::tenantUser()->isTenantAdmin());
    }

    public function testIsTenantUserReturnsTrueOnlyForTenantUser(): void
    {
        $this->assertTrue(UserRole::tenantUser()->isTenantUser());
        $this->assertFalse(UserRole::user()->isTenantUser());
        $this->assertFalse(UserRole::tenantAdmin()->isTenantUser());
    }

    public function testIsVendorReturnsTrueOnlyForVendor(): void
    {
        $this->assertTrue(UserRole::vendor()->isVendor());
        $this->assertFalse(UserRole::user()->isVendor());
        $this->assertFalse(UserRole::customer()->isVendor());
    }

    public function testHasAdminPrivilegesReturnsTrueForAdminRoles(): void
    {
        // Admin Panel Roles with admin privileges
        $this->assertTrue(UserRole::superAdmin()->hasAdminPrivileges());
        $this->assertTrue(UserRole::admin()->hasAdminPrivileges());
        $this->assertTrue(UserRole::manager()->hasAdminPrivileges());

        // Tenant role with admin privileges
        $this->assertTrue(UserRole::tenantAdmin()->hasAdminPrivileges());

        // Roles without admin privileges
        $this->assertFalse(UserRole::viewer()->hasAdminPrivileges());
        $this->assertFalse(UserRole::user()->hasAdminPrivileges());
        $this->assertFalse(UserRole::customer()->hasAdminPrivileges());
        $this->assertFalse(UserRole::tenantUser()->hasAdminPrivileges());
        $this->assertFalse(UserRole::vendor()->hasAdminPrivileges());
    }

    public function testIsReadOnlyReturnsTrueOnlyForViewer(): void
    {
        $this->assertTrue(UserRole::viewer()->isReadOnly());
        $this->assertFalse(UserRole::manager()->isReadOnly());
        $this->assertFalse(UserRole::admin()->isReadOnly());
        $this->assertFalse(UserRole::superAdmin()->isReadOnly());
        $this->assertFalse(UserRole::user()->isReadOnly());
    }

    // ==================== Stringable Interface Tests ====================

    public function testUserRoleImplementsStringable(): void
    {
        $role = UserRole::admin();

        $this->assertSame('ROLE_ADMIN', (string) $role);
    }

    public function testToStringReturnsRoleValue(): void
    {
        $roles = [
            [UserRole::user(), 'ROLE_USER'],
            [UserRole::admin(), 'ROLE_ADMIN'],
            [UserRole::superAdmin(), 'ROLE_SUPER_ADMIN'],
            [UserRole::manager(), 'ROLE_MANAGER'],
            [UserRole::viewer(), 'ROLE_VIEWER'],
            [UserRole::customer(), 'ROLE_CUSTOMER'],
            [UserRole::tenantAdmin(), 'ROLE_TENANT_ADMIN'],
            [UserRole::tenantUser(), 'ROLE_TENANT_USER'],
            [UserRole::vendor(), 'ROLE_VENDOR'],
        ];

        foreach ($roles as [$role, $expected]) {
            $this->assertSame($expected, $role->toString());
            $this->assertSame($expected, (string) $role);
        }
    }

    // ==================== Edge Cases Tests ====================

    public function testUserRoleIsImmutable(): void
    {
        $role1 = UserRole::admin();
        $role2 = UserRole::admin();

        // Since it's readonly, we can only verify they are different instances
        $this->assertNotSame($role1, $role2);
        // But they should be equal in value
        $this->assertTrue($role1->equals($role2));
    }

    public function testCanCreateMultipleRolesSimultaneously(): void
    {
        $admin = UserRole::admin();
        $manager = UserRole::manager();
        $viewer = UserRole::viewer();

        $this->assertSame('ROLE_ADMIN', $admin->toString());
        $this->assertSame('ROLE_MANAGER', $manager->toString());
        $this->assertSame('ROLE_VIEWER', $viewer->toString());
    }
}
