<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security\Voter;

use App\Shared\Infrastructure\Security\Voter\SettingsVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SettingsVoterTest extends TestCase
{
    private SettingsVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new SettingsVoter();
    }

    public function testSuperAdminHasFullAccess(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_SUPER_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [SettingsVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [SettingsVoter::EDIT]));
    }

    public function testAdminCanOnlyViewSettings(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [SettingsVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [SettingsVoter::EDIT]));
    }

    public function testViewerCanOnlyViewSettings(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_VIEWER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [SettingsVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [SettingsVoter::EDIT]));
    }

    public function testTenantAdminCanViewAndEditSettings(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_TENANT_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [SettingsVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, null, [SettingsVoter::EDIT]));
    }

    public function testManagerHasNoAccessToSettings(): void
    {
        $token = $this->createTokenWithRoles(['ROLE_MANAGER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [SettingsVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [SettingsVoter::EDIT]));
    }

    public function testUnauthenticatedUserHasNoAccess(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $token->method('getRoleNames')->willReturn([]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [SettingsVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, null, [SettingsVoter::EDIT]));
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
