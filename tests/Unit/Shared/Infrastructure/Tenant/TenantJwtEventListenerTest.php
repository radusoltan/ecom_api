<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Tenant;

use App\Shared\Infrastructure\Tenant\TenantContext;
use App\Shared\Infrastructure\Tenant\TenantJwtEventListener;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(TenantJwtEventListener::class)]
final class TenantJwtEventListenerTest extends TestCase
{
    private RequestStack $requestStack;
    private TenantContext $tenantContext;
    private TenantJwtEventListener $listener;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement');
        $this->tenantContext = new TenantContext($connection);
        $this->listener = new TenantJwtEventListener($this->requestStack, $this->tenantContext);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeEvent(array $data): JWTCreatedEvent
    {
        $user = new \stdClass();

        /** @var JWTCreatedEvent $event */
        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn($data);

        return $event;
    }

    // ---------------------------------------------------------------
    // Tenant from TenantContext
    // ---------------------------------------------------------------

    #[Test]
    public function itAddsTenantIdFromContextToPayload(): void
    {
        $tenantIdString = '00000000-0000-4000-8000-000000000001';
        $tenantId = \App\Shared\Domain\ValueObject\TenantId::fromString($tenantIdString);

        $this->tenantContext->setCurrentTenant($tenantId);

        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn(['sub' => 'user1']);
        $event->expects(self::once())
            ->method('setData')
            ->with(self::callback(function (array $payload) use ($tenantIdString) {
                return isset($payload['tenant_id'])
                    && $payload['tenant_id'] === $tenantIdString
                    && 'user1' === $payload['sub'];
            }));

        $this->listener->onJWTCreated($event);
    }

    // ---------------------------------------------------------------
    // Tenant from X-Tenant-ID header (fallback)
    // ---------------------------------------------------------------

    #[Test]
    public function itFallsBackToHeaderWhenContextHasNoTenant(): void
    {
        $tenantIdString = '00000000-0000-4000-8000-000000000002';

        $request = Request::create('/api/login');
        $request->headers->set('X-Tenant-ID', $tenantIdString);
        $this->requestStack->push($request);

        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn([]);
        $event->expects(self::once())
            ->method('setData')
            ->with(self::callback(function (array $payload) use ($tenantIdString) {
                return ($payload['tenant_id'] ?? null) === $tenantIdString;
            }));

        $this->listener->onJWTCreated($event);
    }

    // ---------------------------------------------------------------
    // No tenant available at all
    // ---------------------------------------------------------------

    #[Test]
    public function itDoesNotModifyPayloadWhenNoTenantAvailable(): void
    {
        // No tenant in context, no X-Tenant-ID header, no request
        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn(['sub' => 'user1']);
        $event->expects(self::never())->method('setData');

        $this->listener->onJWTCreated($event);
    }

    #[Test]
    public function itDoesNotModifyPayloadWhenRequestExistsButHasNoHeader(): void
    {
        $request = Request::create('/api/login');
        // No X-Tenant-ID header
        $this->requestStack->push($request);

        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn([]);
        $event->expects(self::never())->method('setData');

        $this->listener->onJWTCreated($event);
    }

    // ---------------------------------------------------------------
    // Context takes priority over header
    // ---------------------------------------------------------------

    #[Test]
    public function itPrioritisesContextOverHeader(): void
    {
        $contextTenantId = '00000000-0000-4000-8000-000000000001';
        $headerTenantId = '00000000-0000-4000-8000-000000000002';

        $tenantId = \App\Shared\Domain\ValueObject\TenantId::fromString($contextTenantId);
        $this->tenantContext->setCurrentTenant($tenantId);

        $request = Request::create('/api/login');
        $request->headers->set('X-Tenant-ID', $headerTenantId);
        $this->requestStack->push($request);

        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn([]);
        $event->expects(self::once())
            ->method('setData')
            ->with(self::callback(function (array $payload) use ($contextTenantId) {
                return ($payload['tenant_id'] ?? null) === $contextTenantId;
            }));

        $this->listener->onJWTCreated($event);
    }

    // ---------------------------------------------------------------
    // Preserves existing payload keys
    // ---------------------------------------------------------------

    #[Test]
    public function itPreservesExistingPayloadKeys(): void
    {
        $tenantIdString = '00000000-0000-4000-8000-000000000001';
        $tenantId = \App\Shared\Domain\ValueObject\TenantId::fromString($tenantIdString);
        $this->tenantContext->setCurrentTenant($tenantId);

        $existingPayload = [
            'sub' => 'user@example.com',
            'iat' => 1700000000,
            'exp' => 1700003600,
            'roles' => ['ROLE_USER'],
        ];

        $event = $this->getMockBuilder(JWTCreatedEvent::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'setData'])
            ->getMock();

        $event->method('getData')->willReturn($existingPayload);
        $event->expects(self::once())
            ->method('setData')
            ->with(self::callback(function (array $payload) use ($existingPayload, $tenantIdString) {
                return $payload['sub'] === $existingPayload['sub']
                    && $payload['iat'] === $existingPayload['iat']
                    && $payload['exp'] === $existingPayload['exp']
                    && $payload['roles'] === $existingPayload['roles']
                    && $payload['tenant_id'] === $tenantIdString;
            }));

        $this->listener->onJWTCreated($event);
    }
}
