<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Security;

use App\Catalog\Infrastructure\Security\ProductVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ProductVoterTest extends TestCase
{
    private ProductVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ProductVoter();
    }

    public function testSuperAdminHasFullAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testAdminHasFullCrudAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testManagerHasFullCrudAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_MANAGER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testViewerHasOnlyViewAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_VIEWER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testTenantAdminHasFullCrudAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_TENANT_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testVendorCanCreateAndViewOnly(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_VENDOR']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        // EDIT/DELETE denied until vendor_id ownership field is added to Product
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testCustomerHasNoAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_CUSTOMER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::DELETE]));
    }

    public function testUnauthenticatedUserHasNoAccess(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $token->method('getRoleNames')->willReturn([]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [ProductVoter::CREATE]));
    }

    /**
     * @param list<string> $roles
     */
    private function createTokenWithRoles(array $roles): TokenInterface
    {
        $user = $this->createMock(UserInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getRoleNames')->willReturn($roles);

        return $token;
    }
}
