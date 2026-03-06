<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\UpdateAllConsents\UpdateAllConsentsCommand;
use App\Customer\Application\DTO\CustomerConsentsDTO;
use App\Customer\Application\Query\GetCustomerConsents\GetCustomerConsentsQuery;
use App\Customer\Presentation\Api\Processor\UpdateAllConsentsProcessor;
use App\Customer\Presentation\Api\Resource\CustomerConsentResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(UpdateAllConsentsProcessor::class)]
final class UpdateAllConsentsProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = 'aaaabbbb-aaaa-4aaa-8aaa-aaaaaaaabbbb';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private RequestStack $requestStack;
    private UpdateAllConsentsProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new UpdateAllConsentsProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
            $this->requestStack,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildConsentsDTO();

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateAllConsentsCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetCustomerConsentsQuery::class))
            ->willReturn($queryEnvelope);

        // Simulate a current request with IP and User-Agent
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn('192.168.1.1');

        $headerBag = $this->getMockBuilder(\Symfony\Component\HttpFoundation\HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headerBag->method('get')->with('User-Agent')->willReturn('TestBrowser/1.0');
        $request->headers = $headerBag;

        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(CustomerConsentResource::class, $result);
        self::assertSame($dto->customerId, $result->customerId);
        self::assertTrue($result->marketingEmail);
        self::assertFalse($result->marketingSms);
        self::assertFalse($result->thirdPartySharing);
        self::assertTrue($result->analyticsTracking);
        self::assertNotNull($result->lastUpdatedAt);
    }

    #[Test]
    public function itWorksWhenNoCurrentRequestInStack(): void
    {
        $dto = $this->buildConsentsDTO();

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);

        self::assertInstanceOf(CustomerConsentResource::class, $result);
    }

    // ---------------------------------------------------------------
    // Validation guards – data type
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotCustomerConsentResource(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Expected CustomerConsentResource');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(new \stdClass(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – URI variables
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new UpdateAllConsentsProcessor(
            $this->commandBus,
            $this->queryBus,
            $tenantContext,
            $this->requestStack,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – consent fields
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenMarketingEmailIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->marketingEmail = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All consent fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenMarketingSmsIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->marketingSms = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All consent fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenThirdPartySharingIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->thirdPartySharing = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All consent fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenAnalyticsTrackingIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->analyticsTracking = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All consent fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Handler failure paths
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNoStamp(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenQueryHandlerReturnsNull(): void
    {
        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Consents not found after update');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildValidResource(): CustomerConsentResource
    {
        $resource = new CustomerConsentResource();
        $resource->marketingEmail = true;
        $resource->marketingSms = false;
        $resource->thirdPartySharing = false;
        $resource->analyticsTracking = true;

        return $resource;
    }

    private function buildConsentsDTO(): CustomerConsentsDTO
    {
        return new CustomerConsentsDTO(
            customerId: self::CUSTOMER_ID,
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: true,
            lastUpdatedAt: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        );
    }
}
