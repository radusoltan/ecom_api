<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Security;

use App\Shared\Domain\ValueObject\Email;
use App\Tests\Support\BcryptFixtures;
use App\User\Domain\Model\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\User\Domain\ValueObject\HashedPassword;
use App\User\Domain\ValueObject\UserId;
use App\User\Domain\ValueObject\Username;
use App\User\Domain\ValueObject\UserRole;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\User\Infrastructure\Security\MfaJwtEventListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(MfaJwtEventListener::class)]
final class MfaJwtEventListenerTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUserEntity(string $id = 'a0b1c2d3-e4f5-4000-8000-000000000001'): UserEntity
    {
        $entity = new UserEntity();
        $entity->setId($id);
        $entity->setEmail('user@example.com');
        $entity->setUsername('testuser');
        $entity->setPassword(BcryptFixtures::HASH_SECRET);
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setRoles(['ROLE_USER']);

        return $entity;
    }

    private function makeDomainUser(string $id, bool $mfaEnabled = false): User
    {
        if ($mfaEnabled) {
            return User::reconstitute(
                id: UserId::fromString($id),
                email: Email::fromString('user@example.com'),
                username: Username::fromString('testuser'),
                password: HashedPassword::fromPlainPassword('SecurePass123!'),
                roles: [UserRole::user()],
                createdAt: new \DateTimeImmutable(),
                mfaEnabled: true,
                totpSecret: 'JBSWY3DPEHPK3PXP',
                backupCodes: [BcryptFixtures::HASH_AABBCCDD],
                mfaEnabledAt: new \DateTimeImmutable(),
            );
        }

        return User::create(
            email: Email::fromString('user@example.com'),
            username: Username::fromString('testuser'),
            password: HashedPassword::fromPlainPassword('SecurePass123!'),
        );
    }

    private function makeRequestWithAttributes(array $attributes = []): Request
    {
        $request = Request::create('/api/login');
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        return $request;
    }

    private function makeListener(
        ?UserRepositoryInterface $userRepository = null,
        ?RequestStack $requestStack = null,
    ): MfaJwtEventListener {
        return new MfaJwtEventListener(
            $userRepository ?? $this->createStub(UserRepositoryInterface::class),
            $requestStack ?? $this->createStub(RequestStack::class),
        );
    }

    // -----------------------------------------------------------------------
    // onJWTCreated()
    // -----------------------------------------------------------------------

    #[Test]
    public function itDowngradesRolesToMfaPendingWhenMfaIsEnabled(): void
    {
        $userId = 'a0b1c2d3-e4f5-4000-8000-000000000001';
        $entity = $this->makeUserEntity($userId);
        $domainUser = $this->makeDomainUser($userId, mfaEnabled: true);

        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($domainUser);

        $listener = $this->makeListener($userRepository, $requestStack);

        $initialPayload = ['roles' => ['ROLE_USER'], 'username' => 'testuser'];
        $event = new JWTCreatedEvent($initialPayload, $entity);

        $listener->onJWTCreated($event);

        $payload = $event->getData();
        self::assertSame(['ROLE_MFA_PENDING'], $payload['roles']);
        self::assertFalse($payload['mfa_verified']);
        self::assertArrayHasKey('exp', $payload);
        self::assertGreaterThan(time(), $payload['exp']);
        self::assertLessThanOrEqual(time() + 300, $payload['exp']);
    }

    #[Test]
    public function itDoesNotDowngradeRolesWhenMfaIsNotEnabled(): void
    {
        $userId = 'a0b1c2d3-e4f5-4000-8000-000000000002';
        $entity = $this->makeUserEntity($userId);
        $domainUser = $this->makeDomainUser($userId, mfaEnabled: false);

        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($domainUser);

        $listener = $this->makeListener($userRepository, $requestStack);

        $initialPayload = ['roles' => ['ROLE_USER'], 'username' => 'testuser'];
        $event = new JWTCreatedEvent($initialPayload, $entity);

        $listener->onJWTCreated($event);

        $payload = $event->getData();
        self::assertSame(['ROLE_USER'], $payload['roles']);
        self::assertArrayNotHasKey('mfa_verified', $payload);
    }

    #[Test]
    public function itSkipsDowngradeWhenMfaVerifiedAttributeIsSet(): void
    {
        $userId = 'a0b1c2d3-e4f5-4000-8000-000000000003';
        $entity = $this->makeUserEntity($userId);

        $request = $this->makeRequestWithAttributes(['_mfa_verified' => true]);
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('findById');

        $listener = $this->makeListener($userRepository, $requestStack);

        $initialPayload = ['roles' => ['ROLE_USER'], 'username' => 'testuser'];
        $event = new JWTCreatedEvent($initialPayload, $entity);

        $listener->onJWTCreated($event);

        $payload = $event->getData();
        self::assertSame(['ROLE_USER'], $payload['roles']);
    }

    #[Test]
    public function itSkipsWhenUserIsNotUserEntityInstance(): void
    {
        $nonUserEntity = $this->createStub(UserInterface::class);

        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('findById');

        $listener = $this->makeListener($userRepository, $requestStack);

        $initialPayload = ['roles' => ['ROLE_USER']];
        $event = new JWTCreatedEvent($initialPayload, $nonUserEntity);

        $listener->onJWTCreated($event);

        $payload = $event->getData();
        self::assertSame(['ROLE_USER'], $payload['roles']);
    }

    #[Test]
    public function itSkipsWhenDomainUserNotFoundInRepository(): void
    {
        $userId = 'a0b1c2d3-e4f5-4000-8000-000000000004';
        $entity = $this->makeUserEntity($userId);

        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $listener = $this->makeListener($userRepository, $requestStack);

        $initialPayload = ['roles' => ['ROLE_USER']];
        $event = new JWTCreatedEvent($initialPayload, $entity);

        $listener->onJWTCreated($event);

        $payload = $event->getData();
        self::assertSame(['ROLE_USER'], $payload['roles']);
    }

    // -----------------------------------------------------------------------
    // onAuthenticationSuccess()
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsMfaRequiredFlagWhenMfaIsEnabled(): void
    {
        $userId = 'a0b1c2d3-e4f5-4000-8000-000000000005';
        $entity = $this->makeUserEntity($userId);
        $domainUser = $this->makeDomainUser($userId, mfaEnabled: true);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($domainUser);

        $listener = $this->makeListener($userRepository);

        $initialData = ['token' => 'some.jwt.token'];
        $event = new AuthenticationSuccessEvent($initialData, $entity, new Response());

        $listener->onAuthenticationSuccess($event);

        $data = $event->getData();
        self::assertArrayHasKey('mfa_required', $data);
        self::assertTrue($data['mfa_required']);
    }

    #[Test]
    public function itDoesNotAddMfaRequiredFlagWhenMfaIsNotEnabled(): void
    {
        $userId = 'a0b1c2d3-e4f5-4000-8000-000000000006';
        $entity = $this->makeUserEntity($userId);
        $domainUser = $this->makeDomainUser($userId, mfaEnabled: false);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($domainUser);

        $listener = $this->makeListener($userRepository);

        $initialData = ['token' => 'some.jwt.token'];
        $event = new AuthenticationSuccessEvent($initialData, $entity, new Response());

        $listener->onAuthenticationSuccess($event);

        $data = $event->getData();
        self::assertArrayNotHasKey('mfa_required', $data);
    }

    #[Test]
    public function itDoesNotAddMfaFlagWhenUserIsNotUserEntityInstance(): void
    {
        $nonUserEntity = $this->createStub(UserInterface::class);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('findById');

        $listener = $this->makeListener($userRepository);

        $initialData = ['token' => 'some.jwt.token'];
        $event = new AuthenticationSuccessEvent($initialData, $nonUserEntity, new Response());

        $listener->onAuthenticationSuccess($event);

        $data = $event->getData();
        self::assertArrayNotHasKey('mfa_required', $data);
    }

    // -----------------------------------------------------------------------
    // onJWTDecoded()
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsMfaPendingAttributeWhenMfaVerifiedIsFalse(): void
    {
        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $listener = $this->makeListener(requestStack: $requestStack);

        $payload = ['mfa_verified' => false, 'roles' => ['ROLE_MFA_PENDING']];
        $event = new JWTDecodedEvent($payload);

        $listener->onJWTDecoded($event);

        self::assertTrue($request->attributes->get('_mfa_pending'));
    }

    #[Test]
    public function itDoesNotSetMfaPendingWhenMfaVerifiedIsTrue(): void
    {
        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $listener = $this->makeListener(requestStack: $requestStack);

        $payload = ['mfa_verified' => true, 'roles' => ['ROLE_USER']];
        $event = new JWTDecodedEvent($payload);

        $listener->onJWTDecoded($event);

        self::assertFalse($request->attributes->get('_mfa_pending', false));
    }

    #[Test]
    public function itDoesNotSetMfaPendingWhenPayloadHasNoMfaVerifiedKey(): void
    {
        $request = $this->makeRequestWithAttributes();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $listener = $this->makeListener(requestStack: $requestStack);

        $payload = ['roles' => ['ROLE_USER']];
        $event = new JWTDecodedEvent($payload);

        $listener->onJWTDecoded($event);

        self::assertFalse($request->attributes->get('_mfa_pending', false));
    }

    #[Test]
    public function itHandlesNullRequestGracefullyInOnJWTDecoded(): void
    {
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);

        $listener = $this->makeListener(requestStack: $requestStack);

        $payload = ['mfa_verified' => false];
        $event = new JWTDecodedEvent($payload);

        // Should not throw
        $listener->onJWTDecoded($event);

        self::assertTrue(true);
    }
}
