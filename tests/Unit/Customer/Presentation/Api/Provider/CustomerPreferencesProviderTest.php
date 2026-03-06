<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQuery;
use App\Customer\Presentation\Api\Provider\CustomerPreferencesProvider;
use App\Customer\Presentation\Api\Resource\CustomerPreferencesResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(CustomerPreferencesProvider::class)]
final class CustomerPreferencesProviderTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '77777777-7777-4777-8777-777777777777';

    private MessageBusInterface $messageBus;
    private RequestStack $requestStack;
    private CustomerPreferencesProvider $provider;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->provider = new CustomerPreferencesProvider($this->messageBus, $this->requestStack);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsPreferencesResourceMappedFromDto(): void
    {
        $this->stubCurrentRequest(tenantId: self::TENANT_ID);

        $dto = new CustomerPreferencesDTO(
            preferredLanguage: 'en',
            preferredCurrency: 'USD',
            newsletterSubscribed: true,
            marketingEmailsAllowed: false,
            smsNotificationsAllowed: false,
            orderNotificationsEnabled: true,
            promotionNotificationsEnabled: false,
        );

        $envelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetCustomerPreferencesQuery::class))
            ->willReturn($envelope);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(CustomerPreferencesResource::class, $result);
        self::assertSame(self::CUSTOMER_ID, $result->customerId);
        self::assertSame('en', $result->preferredLanguage);
        self::assertSame('USD', $result->preferredCurrency);
        self::assertTrue($result->newsletterSubscribed);
        self::assertFalse($result->marketingEmailsAllowed);
        self::assertTrue($result->orderNotificationsEnabled);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, [], []);
    }

    #[Test]
    public function itThrowsWhenTenantIdHeaderMissing(): void
    {
        $this->stubCurrentRequest(tenantId: null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $operation = $this->createMock(Operation::class);
        $this->provider->provide($operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function stubCurrentRequest(?string $tenantId): void
    {
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);
        $headers->method('get')
            ->with('X-Tenant-ID')
            ->willReturn($tenantId);
        $request->headers = $headers;

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn($request);
    }
}
