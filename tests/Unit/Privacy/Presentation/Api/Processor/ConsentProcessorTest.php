<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\GrantConsentCommand;
use App\Privacy\Application\Command\WithdrawConsentCommand;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use App\Privacy\Presentation\Api\Processor\GrantConsentProcessor;
use App\Privacy\Presentation\Api\Processor\WithdrawConsentProcessor;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(GrantConsentProcessor::class)]
#[CoversClass(WithdrawConsentProcessor::class)]
final class ConsentProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';
    private const VALID_CONSENT_TEXT = 'I consent to the processing of my personal data for the purpose of marketing communications as described in the privacy policy.';
    private const CONSENT_VERSION = 'v1.0.0';
    private const IP_ADDRESS = '192.168.1.1';
    private const USER_AGENT = 'TestAgent/1.0';

    private MessageBusInterface $commandBus;
    private ConsentRepositoryInterface $consentRepository;
    private RequestStack $requestStack;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->consentRepository = $this->createMock(ConsentRepositoryInterface::class);
        $this->requestStack = new RequestStack();
        $this->operation = $this->createMock(Operation::class);

        $request = new Request(
            server: [
                'REMOTE_ADDR' => self::IP_ADDRESS,
                'HTTP_USER_AGENT' => self::USER_AGENT,
            ]
        );
        $this->requestStack->push($request);
    }

    // -------------------------------------------------------------------------
    // GrantConsentProcessor tests
    // -------------------------------------------------------------------------

    #[Test]
    public function testGrantConsentDispatchesCommandAndReturnsEntity(): void
    {
        $consentId = ConsentId::generate();
        $consent = $this->buildGrantedConsent($consentId);
        $data = ConsentEntity::fromDomainModel($consent);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GrantConsentCommand $cmd): bool {
                return self::TENANT_ID === $cmd->tenantId->toString()
                    && self::CUSTOMER_ID === $cmd->customerId->toString()
                    && 'marketing' === $cmd->purpose->value()
                    && self::VALID_CONSENT_TEXT === $cmd->consentText
                    && self::CONSENT_VERSION === $cmd->consentVersion;
            }))
            ->willReturn(new Envelope(new \stdClass(), [
                new HandledStamp($consentId, 'handler'),
            ]));

        $this->consentRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->callback(fn (ConsentId $id): bool => $id->equals($consentId)))
            ->willReturn($consent);

        $processor = $this->buildGrantConsentProcessor();

        $result = $processor->process(
            $data,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );

        $this->assertInstanceOf(ConsentEntity::class, $result);
        $this->assertSame(self::CUSTOMER_ID, $result->getCustomerId());
        $this->assertSame('marketing', $result->getPurpose());
        $this->assertTrue($result->isGranted());
    }

    #[Test]
    public function testGrantConsentThrowsWhenMissingTenantId(): void
    {
        $consent = $this->buildGrantedConsent(ConsentId::generate());
        $data = ConsentEntity::fromDomainModel($consent);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildGrantConsentProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        $processor->process(
            $data,
            $this->operation,
            [],
            [] // no tenant_id key
        );
    }

    #[Test]
    public function testGrantConsentThrowsWhenMissingCustomerId(): void
    {
        // Build a consent with an empty customerId is not possible via domain model,
        // so we craft a ConsentEntity via reconstituteFromPersistence using a minimal consent
        // and then test that the processor itself detects the empty string.
        // We use a consent that has valid data but test the processor's guard on empty customerId
        // by building an entity via a consent with a known customerId and then confirming the
        // guard executes - in this case we must simulate the entity returning '' for customerId.
        // Since ConsentEntity has no setters, we test the guard by using the actual Consent
        // domain model factory path that results in an entity whose getCustomerId() returns ''.
        // CustomerId cannot be empty via domain model, so we verify the processor's guard
        // against empty customerId using a separate mechanism: reflection.
        $entity = $this->buildConsentEntityWithEmptyField('customerId');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildGrantConsentProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $processor->process(
            $entity,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );
    }

    #[Test]
    public function testGrantConsentThrowsWhenMissingPurpose(): void
    {
        $entity = $this->buildConsentEntityWithEmptyField('purpose');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildGrantConsentProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Purpose is required');

        $processor->process(
            $entity,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );
    }

    #[Test]
    public function testGrantConsentThrowsWhenMissingConsentText(): void
    {
        $entity = $this->buildConsentEntityWithEmptyField('consentText');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildGrantConsentProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Consent text is required');

        $processor->process(
            $entity,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );
    }

    #[Test]
    public function testGrantConsentThrowsWhenMissingConsentVersion(): void
    {
        $entity = $this->buildConsentEntityWithEmptyField('consentVersion');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildGrantConsentProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Consent version is required');

        $processor->process(
            $entity,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );
    }

    #[Test]
    public function testGrantConsentCapturesIpAndUserAgent(): void
    {
        $consentId = ConsentId::generate();
        $consent = $this->buildGrantedConsent($consentId);
        $data = ConsentEntity::fromDomainModel($consent);

        $capturedCommand = null;

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GrantConsentCommand $cmd) use (&$capturedCommand): bool {
                $capturedCommand = $cmd;

                return true;
            }))
            ->willReturn(new Envelope(new \stdClass(), [
                new HandledStamp($consentId, 'handler'),
            ]));

        $this->consentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($consent);

        $processor = $this->buildGrantConsentProcessor();

        $processor->process(
            $data,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );

        $this->assertNotNull($capturedCommand);
        $this->assertSame(self::IP_ADDRESS, $capturedCommand->ipAddress);
        $this->assertSame(self::USER_AGENT, $capturedCommand->userAgent);
    }

    #[Test]
    public function testGrantConsentThrowsRuntimeExceptionWhenConsentNotFoundAfterCreation(): void
    {
        $consentId = ConsentId::generate();
        $consent = $this->buildGrantedConsent($consentId);
        $data = ConsentEntity::fromDomainModel($consent);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [
                new HandledStamp($consentId, 'handler'),
            ]));

        $this->consentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null); // Consent created but not loadable

        $processor = $this->buildGrantConsentProcessor();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Consent was created but could not be loaded');

        $processor->process(
            $data,
            $this->operation,
            [],
            ['tenant_id' => self::TENANT_ID]
        );
    }

    // -------------------------------------------------------------------------
    // WithdrawConsentProcessor tests
    // -------------------------------------------------------------------------

    #[Test]
    public function testWithdrawConsentDispatchesCommandAndReturnsUpdatedEntity(): void
    {
        $consentId = ConsentId::generate();
        $grantedConsent = $this->buildGrantedConsent($consentId);

        // After withdraw, consent is no longer granted
        $withdrawnConsent = $this->buildWithdrawnConsent($consentId);

        $this->consentRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($grantedConsent, $withdrawnConsent);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (WithdrawConsentCommand $cmd) use ($consentId): bool {
                return $cmd->consentId->equals($consentId);
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = $this->buildWithdrawConsentProcessor();

        $result = $processor->process(
            new \stdClass(),
            $this->operation,
            ['id' => $consentId->toString()],
            ['tenant_id' => self::TENANT_ID]
        );

        $this->assertInstanceOf(ConsentEntity::class, $result);
        $this->assertFalse($result->isGranted());
    }

    #[Test]
    public function testWithdrawConsentThrowsWhenConsentNotFound(): void
    {
        $consentId = ConsentId::generate();

        $this->consentRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildWithdrawConsentProcessor();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(sprintf('Consent "%s" not found', $consentId->toString()));

        $processor->process(
            new \stdClass(),
            $this->operation,
            ['id' => $consentId->toString()],
            ['tenant_id' => self::TENANT_ID]
        );
    }

    #[Test]
    public function testWithdrawConsentThrowsWhenMissingId(): void
    {
        $this->consentRepository->expects($this->never())->method('findById');
        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = $this->buildWithdrawConsentProcessor();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Consent ID is required');

        $processor->process(
            new \stdClass(),
            $this->operation,
            [], // no id in uriVariables
            ['tenant_id' => self::TENANT_ID]
        );
    }

    #[Test]
    public function testWithdrawConsentUsesOriginalConsentWhenReloadReturnsNull(): void
    {
        $consentId = ConsentId::generate();
        $grantedConsent = $this->buildGrantedConsent($consentId);

        $this->consentRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls(
                $grantedConsent, // initial lookup - found
                null             // reload after dispatch - not found, fallback to original
            );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $processor = $this->buildWithdrawConsentProcessor();

        $result = $processor->process(
            new \stdClass(),
            $this->operation,
            ['id' => $consentId->toString()],
            []
        );

        $this->assertInstanceOf(ConsentEntity::class, $result);
        // Falls back to original granted consent
        $this->assertTrue($result->isGranted());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildGrantConsentProcessor(): GrantConsentProcessor
    {
        return new GrantConsentProcessor(
            $this->commandBus,
            $this->consentRepository,
            $this->requestStack,
        );
    }

    private function buildWithdrawConsentProcessor(): WithdrawConsentProcessor
    {
        return new WithdrawConsentProcessor(
            $this->commandBus,
            $this->consentRepository,
        );
    }

    private function buildGrantedConsent(ConsentId $consentId): Consent
    {
        return Consent::grant(
            $consentId,
            TenantId::fromString(self::TENANT_ID),
            CustomerId::fromString(self::CUSTOMER_ID),
            ConsentPurpose::fromString('marketing'),
            self::IP_ADDRESS,
            self::USER_AGENT,
            self::VALID_CONSENT_TEXT,
            self::CONSENT_VERSION,
        );
    }

    private function buildWithdrawnConsent(ConsentId $consentId): Consent
    {
        $consent = Consent::reconstituteFromPersistence(
            $consentId,
            TenantId::fromString(self::TENANT_ID),
            CustomerId::fromString(self::CUSTOMER_ID),
            ConsentPurpose::fromString('marketing'),
            false,
            self::IP_ADDRESS,
            self::USER_AGENT,
            self::VALID_CONSENT_TEXT,
            self::CONSENT_VERSION,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        return $consent;
    }

    /**
     * Creates a ConsentEntity with a specific field blanked out using reflection,
     * since ConsentEntity has no public setters and all fields are private.
     *
     * This allows testing the processor's guard clauses for missing required fields.
     */
    private function buildConsentEntityWithEmptyField(string $fieldName): ConsentEntity
    {
        $consent = $this->buildGrantedConsent(ConsentId::generate());
        $entity = ConsentEntity::fromDomainModel($consent);

        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty($fieldName);
        $property->setAccessible(true);
        $property->setValue($entity, '');

        return $entity;
    }
}
