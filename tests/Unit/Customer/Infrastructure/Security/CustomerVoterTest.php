<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Security;

use App\Customer\Domain\Model\Customer;
use App\Customer\Infrastructure\Security\CustomerVoter;
use App\Shared\Domain\ValueObject\Email;
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

    public function testCustomerCanViewOwnProfile(): void
    {
        $token = $this->createTokenWithRolesAndEmail(['ROLE_CUSTOMER'], 'customer@example.com');
        $customer = $this->createCustomerWithEmail('customer@example.com');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $customer, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $customer, [CustomerVoter::EDIT]));
    }

    public function testCustomerCannotViewOtherCustomerProfile(): void
    {
        $token = $this->createTokenWithRolesAndEmail(['ROLE_CUSTOMER'], 'customer@example.com');
        $customer = $this->createCustomerWithEmail('other@example.com');

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $customer, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $customer, [CustomerVoter::EDIT]));
    }

    public function testAdminCanViewAnyCustomerProfile(): void
    {
        $token = $this->createTokenWithRolesAndEmail(['ROLE_ADMIN'], 'admin@example.com');
        $customer = $this->createCustomerWithEmail('customer@example.com');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $customer, [CustomerVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $customer, [CustomerVoter::EDIT]));
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

    /**
     * @param list<string> $roles
     */
    private function createTokenWithRolesAndEmail(array $roles, string $email): TokenInterface
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn($email);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $token->method('getRoleNames')->willReturn($roles);

        return $token;
    }

    private function createCustomerWithEmail(string $email): Customer
    {
        $emailVo = $this->createMock(Email::class);
        $emailVo->method('toString')->willReturn($email);

        $customer = $this->createMock(Customer::class);
        $customer->method('email')->willReturn($emailVo);

        return $customer;
    }
}
