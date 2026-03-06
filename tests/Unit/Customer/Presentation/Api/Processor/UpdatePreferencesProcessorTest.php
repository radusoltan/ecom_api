<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\UpdatePreferences\UpdatePreferencesCommand;
use App\Customer\Presentation\Api\Processor\UpdatePreferencesProcessor;
use App\Customer\Presentation\Api\Resource\CustomerPreferencesResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(UpdatePreferencesProcessor::class)]
final class UpdatePreferencesProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '44444444-4444-4444-8444-444444444444';

    private MessageBusInterface $messageBus;
    private RequestStack $requestStack;
    private UpdatePreferencesProcessor $processor;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->processor = new UpdatePreferencesProcessor($this->messageBus, $this->requestStack);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesUpdatePreferencesCommandAndReturnsResource(): void
    {
        $this->stubCurrentRequest(tenantId: self::TENANT_ID);

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdatePreferencesCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $data = new CustomerPreferencesResource();
        $data->preferredLanguage = 'en';
        $data->preferredCurrency = 'USD';
        $data->newsletterSubscribed = true;

        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(CustomerPreferencesResource::class, $result);
        self::assertSame(self::CUSTOMER_ID, $result->customerId);
        self::assertSame('en', $result->preferredLanguage);
        self::assertTrue($result->newsletterSubscribed);
    }

    #[Test]
    public function itBuildsPreferencesArrayFromNonNullFieldsOnly(): void
    {
        $this->stubCurrentRequest(tenantId: self::TENANT_ID);

        $capturedCommand = null;
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$capturedCommand) {
                $capturedCommand = $message;

                return new Envelope($message);
            });

        $data = new CustomerPreferencesResource();
        $data->preferredLanguage = 'ro';
        // All other fields remain null

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);

        self::assertInstanceOf(UpdatePreferencesCommand::class, $capturedCommand);
        self::assertArrayHasKey('preferredLanguage', $capturedCommand->preferences);
        self::assertArrayNotHasKey('preferredCurrency', $capturedCommand->preferences);
    }

    // ---------------------------------------------------------------
    // Validation guards
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $data = new CustomerPreferencesResource();
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, [], []);
    }

    #[Test]
    public function itThrowsWhenTenantIdHeaderMissing(): void
    {
        $this->stubCurrentRequest(tenantId: null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $data = new CustomerPreferencesResource();
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenNoCurrentRequest(): void
    {
        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant ID is required');

        $data = new CustomerPreferencesResource();
        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
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
