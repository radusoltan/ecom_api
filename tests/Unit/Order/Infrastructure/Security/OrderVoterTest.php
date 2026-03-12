<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\Security;

use App\Order\Domain\Model\Order;
use App\Order\Infrastructure\Security\OrderVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class OrderVoterTest extends TestCase
{
    private OrderVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new OrderVoter();
    }

    public function testSuperAdminHasFullAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CANCEL]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::REFUND]));
    }

    public function testAdminHasFullAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CANCEL]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::REFUND]));
    }

    public function testManagerHasFullAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_MANAGER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CANCEL]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::REFUND]));
    }

    public function testViewerHasOnlyViewAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_VIEWER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::CANCEL]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::REFUND]));
    }

    public function testCustomerCanCreateAndViewOrders(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_CUSTOMER']);

        // Customer can always create orders
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [OrderVoter::CREATE]));

        // Customer needs Order subject to view/cancel specific orders (ownership check)
        // Without subject, voter denies access
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::CANCEL]));

        // Customer cannot edit or refund
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::REFUND]));
    }

    public function testUnauthenticatedUserHasNoAccess(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $token->method('getRoleNames')->willReturn([]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::CREATE]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [OrderVoter::REFUND]));
    }

    public function testCustomerCanViewOwnOrder(): void
    {
        $token = $this->createTokenWithRolesAndEmail(['ROLE_CUSTOMER'], 'customer@example.com');
        $order = $this->createOrderWithEmail('customer@example.com');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $order, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $order, [OrderVoter::CANCEL]));
    }

    public function testCustomerCannotViewOtherCustomerOrder(): void
    {
        $token = $this->createTokenWithRolesAndEmail(['ROLE_CUSTOMER'], 'customer@example.com');
        $order = $this->createOrderWithEmail('other@example.com');

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $order, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $order, [OrderVoter::CANCEL]));
    }

    public function testAdminCanViewAnyOrder(): void
    {
        $token = $this->createTokenWithRolesAndEmail(['ROLE_ADMIN'], 'admin@example.com');
        $order = $this->createOrderWithEmail('customer@example.com');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $order, [OrderVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $order, [OrderVoter::CANCEL]));
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

    private function createOrderWithEmail(string $customerEmail): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('customerEmail')->willReturn($customerEmail);

        return $order;
    }
}
