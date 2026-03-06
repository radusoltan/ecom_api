<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\UpdatePreferences\UpdatePreferencesCommand;
use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQuery;
use App\Customer\Presentation\Api\Processor\UpdateNotificationPreferencesProcessor;
use App\Customer\Presentation\Api\Resource\NotificationPreferencesResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(UpdateNotificationPreferencesProcessor::class)]
final class UpdateNotificationPreferencesProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '99999999-9999-4999-8999-999999999999';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private UpdateNotificationPreferencesProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new UpdateNotificationPreferencesProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itDispatchesCommandAndReturnsMappedResource(): void
    {
        $dto = $this->buildPreferencesDTO();

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdatePreferencesCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GetCustomerPreferencesQuery::class))
            ->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            $data,
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(NotificationPreferencesResource::class, $result);
        self::assertSame(self::CUSTOMER_ID, $result->customerId);
        self::assertTrue($result->newsletterSubscribed);
        self::assertTrue($result->marketingEmailsAllowed);
        self::assertFalse($result->smsNotificationsAllowed);
        self::assertTrue($result->orderNotificationsEnabled);
        self::assertFalse($result->promotionNotificationsEnabled);
        self::assertSame('en', $result->preferredLanguage);
        self::assertSame('USD', $result->preferredCurrency);
    }

    #[Test]
    public function itIncludesOptionalLanguageAndCurrencyInPreferences(): void
    {
        $dto = $this->buildPreferencesDTO();

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        $data->preferredLanguage = 'ro';
        $data->preferredCurrency = 'RON';

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);

        self::assertInstanceOf(NotificationPreferencesResource::class, $result);
    }

    #[Test]
    public function itOmitsLanguageAndCurrencyFromPreferencesWhenNull(): void
    {
        $dto = $this->buildPreferencesDTO();

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $queryEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($queryEnvelope);

        $data = $this->buildValidResource();
        // Language and currency remain null

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);

        self::assertInstanceOf(NotificationPreferencesResource::class, $result);
    }

    // ---------------------------------------------------------------
    // Validation guards – data type
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenDataIsNotNotificationPreferencesResource(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Expected NotificationPreferencesResource');

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

        $processor = new UpdateNotificationPreferencesProcessor(
            $this->commandBus,
            $this->queryBus,
            $tenantContext,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – required fields
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenNewsletterSubscribedIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->newsletterSubscribed = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All notification preference fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenMarketingEmailsAllowedIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->marketingEmailsAllowed = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All notification preference fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenSmsNotificationsAllowedIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->smsNotificationsAllowed = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All notification preference fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenOrderNotificationsEnabledIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->orderNotificationsEnabled = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All notification preference fields are required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($data, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenPromotionNotificationsEnabledIsNull(): void
    {
        $data = $this->buildValidResource();
        $data->promotionNotificationsEnabled = null;

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('All notification preference fields are required');

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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Preferences not found after update');

        $operation = $this->createMock(Operation::class);
        $this->processor->process($this->buildValidResource(), $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildValidResource(): NotificationPreferencesResource
    {
        $resource = new NotificationPreferencesResource();
        $resource->newsletterSubscribed = true;
        $resource->marketingEmailsAllowed = true;
        $resource->smsNotificationsAllowed = false;
        $resource->orderNotificationsEnabled = true;
        $resource->promotionNotificationsEnabled = false;

        return $resource;
    }

    private function buildPreferencesDTO(): CustomerPreferencesDTO
    {
        return new CustomerPreferencesDTO(
            preferredLanguage: 'en',
            preferredCurrency: 'USD',
            newsletterSubscribed: true,
            marketingEmailsAllowed: true,
            smsNotificationsAllowed: false,
            orderNotificationsEnabled: true,
            promotionNotificationsEnabled: false,
        );
    }
}
