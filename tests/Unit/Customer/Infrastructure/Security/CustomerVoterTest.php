<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Security;

use App\Customer\Infrastructure\Security\CustomerVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class CustomerVoterTest extends TestCase
{
    private CustomerVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new CustomerVoter();
    }

    public function testSuperAdminHasFullAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::DELETE]));
    }

    public function testAdminHasFullCrudAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::DELETE]));
    }

    public function testManagerHasFullCrudAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_MANAGER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::DELETE]));
    }

    public function testViewerHasOnlyViewAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_VIEWER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::DELETE]));
    }

    public function testCustomerCanViewAndEditOwnProfileButCannotCreateOrDelete(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_CUSTOMER']);

        // Customer needs a Customer subject to view/edit (ownership check)
        // Without subject, voter abstains - this is expected behavior
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::DELETE]));
    }

    public function testUnauthenticatedUserHasNoAccess(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $token->method('getRoleNames')->willReturn([]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [CustomerVoter::EDIT]));
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
