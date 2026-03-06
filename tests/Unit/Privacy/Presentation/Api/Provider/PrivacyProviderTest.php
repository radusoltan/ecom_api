<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Privacy\Presentation\Api\Provider\ConsentCollectionProvider;
use App\Privacy\Presentation\Api\Provider\ConsentItemProvider;
use App\Privacy\Presentation\Api\Provider\DataSubjectRequestCollectionProvider;
use App\Privacy\Presentation\Api\Provider\DataSubjectRequestItemProvider;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(ConsentCollectionProvider::class)]
#[CoversClass(ConsentItemProvider::class)]
#[CoversClass(DataSubjectRequestCollectionProvider::class)]
#[CoversClass(DataSubjectRequestItemProvider::class)]
final class PrivacyProviderTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';
    private const CONSENT_TEXT = 'I consent to the processing of my personal data for the purpose of marketing communications as described in the privacy policy.';
    private const CONSENT_VERSION = 'v1.0.0';

    private ConsentRepositoryInterface $consentRepository;
    private DataSubjectRequestRepositoryInterface $requestRepository;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->consentRepository = $this->createMock(ConsentRepositoryInterface::class);
        $this->requestRepository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->operation = $this->createMock(Operation::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeConsent(): Consent
    {
        return Consent::grant(
            ConsentId::generate(),
            TenantId::fromString(self::TENANT_ID),
            CustomerId::fromString(self::CUSTOMER_ID),
            ConsentPurpose::fromString('marketing'),
            '192.168.1.1',
            'TestAgent/1.0',
            self::CONSENT_TEXT,
            self::CONSENT_VERSION,
        );
    }

    private function makeDsRequest(): DataSubjectRequest
    {
        return DataSubjectRequest::submit(
            DataSubjectRequestId::generate(),
            TenantId::fromString(self::TENANT_ID),
            CustomerId::fromString(self::CUSTOMER_ID),
            RequestType::fromString('access'),
            'I want to access all my personal data stored in the system.',
        );
    }

    private function makeRequestStack(?Request $request = null): RequestStack
    {
        $stack = new RequestStack();
        if (null !== $request) {
            $stack->push($request);
        }

        return $stack;
    }

    private function tenantContext(): array
    {
        return ['tenant_id' => self::TENANT_ID];
    }

    // -------------------------------------------------------------------------
    // ConsentCollectionProvider tests
    // -------------------------------------------------------------------------

    #[Test]
    public function testConsentCollectionProviderReturnsTenantConsents(): void
    {
        // Arrange
        $consent = $this->makeConsent();

        $this->consentRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with(self::callback(
                fn (TenantId $id) => self::TENANT_ID === $id->toString(),
            ))
            ->willReturn([$consent]);

        $requestStack = $this->makeRequestStack(new Request());
        $provider = new ConsentCollectionProvider($this->consentRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(ConsentEntity::class, $result[0]);
        self::assertSame(self::CUSTOMER_ID, $result[0]->getCustomerId());
        self::assertSame('marketing', $result[0]->getPurpose());
    }

    #[Test]
    public function testConsentCollectionProviderFiltersByCustomerId(): void
    {
        // Arrange
        $consent = $this->makeConsent();

        $this->consentRepository
            ->expects(self::once())
            ->method('findByCustomerId')
            ->with(self::callback(
                fn (CustomerId $id) => self::CUSTOMER_ID === $id->toString(),
            ))
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects(self::never())
            ->method('findByTenantId');

        $request = new Request(query: ['customerId' => self::CUSTOMER_ID]);
        $requestStack = $this->makeRequestStack($request);
        $provider = new ConsentCollectionProvider($this->consentRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(ConsentEntity::class, $result[0]);
        self::assertSame(self::CUSTOMER_ID, $result[0]->getCustomerId());
    }

    #[Test]
    public function testConsentCollectionProviderFiltersActiveOnly(): void
    {
        // Arrange
        $consent = $this->makeConsent();

        $this->consentRepository
            ->expects(self::once())
            ->method('findActiveByCustomerId')
            ->with(self::callback(
                fn (CustomerId $id) => self::CUSTOMER_ID === $id->toString(),
            ))
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects(self::never())
            ->method('findByCustomerId');

        $this->consentRepository
            ->expects(self::never())
            ->method('findByTenantId');

        $request = new Request(query: ['customerId' => self::CUSTOMER_ID, 'activeOnly' => 'true']);
        $requestStack = $this->makeRequestStack($request);
        $provider = new ConsentCollectionProvider($this->consentRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(ConsentEntity::class, $result[0]);
    }

    #[Test]
    public function testConsentCollectionProviderThrowsWhenMissingTenantId(): void
    {
        // Arrange
        $requestStack = $this->makeRequestStack(new Request());
        $provider = new ConsentCollectionProvider($this->consentRepository, $requestStack);

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        // Act
        $provider->provide($this->operation, [], []);
    }

    #[Test]
    public function testConsentCollectionProviderReturnsEmptyArrayWhenNoConsentsFound(): void
    {
        // Arrange
        $this->consentRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn([]);

        $requestStack = $this->makeRequestStack(new Request());
        $provider = new ConsentCollectionProvider($this->consentRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertSame([], $result);
    }

    #[Test]
    public function testConsentCollectionProviderIgnoresActiveOnlyWhenNoCustomerId(): void
    {
        // Arrange: activeOnly=true but no customerId - should fall through to findByTenantId
        $consent = $this->makeConsent();

        $this->consentRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn([$consent]);

        $this->consentRepository
            ->expects(self::never())
            ->method('findActiveByCustomerId');

        $request = new Request(query: ['activeOnly' => 'true']);
        $requestStack = $this->makeRequestStack($request);
        $provider = new ConsentCollectionProvider($this->consentRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // ConsentItemProvider tests
    // -------------------------------------------------------------------------

    #[Test]
    public function testConsentItemProviderReturnsConsent(): void
    {
        // Arrange
        $consent = $this->makeConsent();
        $consentIdString = $consent->id()->toString();

        $this->consentRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(
                fn (ConsentId $id) => $id->toString() === $consentIdString,
            ))
            ->willReturn($consent);

        $provider = new ConsentItemProvider($this->consentRepository);

        // Act
        $result = $provider->provide($this->operation, ['id' => $consentIdString], $this->tenantContext());

        // Assert
        self::assertInstanceOf(ConsentEntity::class, $result);
        self::assertSame($consentIdString, $result->getId());
        self::assertSame(self::CUSTOMER_ID, $result->getCustomerId());
        self::assertSame('marketing', $result->getPurpose());
    }

    #[Test]
    public function testConsentItemProviderThrowsWhenNotFound(): void
    {
        // Arrange
        $consentId = ConsentId::generate();

        $this->consentRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $provider = new ConsentItemProvider($this->consentRepository);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('not found');

        // Act
        $provider->provide($this->operation, ['id' => $consentId->toString()], []);
    }

    #[Test]
    public function testConsentItemProviderThrowsWhenIdMissing(): void
    {
        // Arrange
        $this->consentRepository
            ->expects(self::never())
            ->method('findById');

        $provider = new ConsentItemProvider($this->consentRepository);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Consent ID is required');

        // Act
        $provider->provide($this->operation, [], []);
    }

    // -------------------------------------------------------------------------
    // DataSubjectRequestCollectionProvider tests
    // -------------------------------------------------------------------------

    #[Test]
    public function testDSRCollectionProviderReturnsTenantRequests(): void
    {
        // Arrange
        $dsRequest = $this->makeDsRequest();

        $this->requestRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with(self::callback(
                fn (TenantId $id) => self::TENANT_ID === $id->toString(),
            ))
            ->willReturn([$dsRequest]);

        $requestStack = $this->makeRequestStack(new Request());
        $provider = new DataSubjectRequestCollectionProvider($this->requestRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequestEntity::class, $result[0]);
        self::assertSame(self::CUSTOMER_ID, $result[0]->getCustomerId());
        self::assertSame('access', $result[0]->getRequestType());
    }

    #[Test]
    public function testDSRCollectionProviderFiltersByCustomerId(): void
    {
        // Arrange
        $dsRequest = $this->makeDsRequest();

        $this->requestRepository
            ->expects(self::once())
            ->method('findByCustomerId')
            ->with(self::callback(
                fn (CustomerId $id) => self::CUSTOMER_ID === $id->toString(),
            ))
            ->willReturn([$dsRequest]);

        $this->requestRepository
            ->expects(self::never())
            ->method('findByTenantId');

        $request = new Request(query: ['customerId' => self::CUSTOMER_ID]);
        $requestStack = $this->makeRequestStack($request);
        $provider = new DataSubjectRequestCollectionProvider($this->requestRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequestEntity::class, $result[0]);
        self::assertSame(self::CUSTOMER_ID, $result[0]->getCustomerId());
    }

    #[Test]
    public function testDSRCollectionProviderFiltersByStatus(): void
    {
        // Arrange
        $dsRequest = $this->makeDsRequest();

        $this->requestRepository
            ->expects(self::once())
            ->method('findByStatus')
            ->with(
                self::callback(fn (RequestStatus $s) => 'pending' === $s->value()),
                self::callback(fn (TenantId $id) => self::TENANT_ID === $id->toString()),
            )
            ->willReturn([$dsRequest]);

        $this->requestRepository
            ->expects(self::never())
            ->method('findByTenantId');

        $this->requestRepository
            ->expects(self::never())
            ->method('findByCustomerId');

        $request = new Request(query: ['status' => 'pending']);
        $requestStack = $this->makeRequestStack($request);
        $provider = new DataSubjectRequestCollectionProvider($this->requestRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequestEntity::class, $result[0]);
        self::assertSame('pending', $result[0]->getStatus());
    }

    #[Test]
    public function testDSRCollectionProviderFiltersByType(): void
    {
        // Arrange
        $dsRequest = $this->makeDsRequest();

        $this->requestRepository
            ->expects(self::once())
            ->method('findByType')
            ->with(
                self::callback(fn (RequestType $t) => 'access' === $t->value()),
                self::callback(fn (TenantId $id) => self::TENANT_ID === $id->toString()),
            )
            ->willReturn([$dsRequest]);

        $this->requestRepository
            ->expects(self::never())
            ->method('findByTenantId');

        $this->requestRepository
            ->expects(self::never())
            ->method('findByCustomerId');

        $request = new Request(query: ['type' => 'access']);
        $requestStack = $this->makeRequestStack($request);
        $provider = new DataSubjectRequestCollectionProvider($this->requestRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertCount(1, $result);
        self::assertInstanceOf(DataSubjectRequestEntity::class, $result[0]);
        self::assertSame('access', $result[0]->getRequestType());
    }

    #[Test]
    public function testDSRCollectionProviderThrowsWhenMissingTenantId(): void
    {
        // Arrange
        $requestStack = $this->makeRequestStack(new Request());
        $provider = new DataSubjectRequestCollectionProvider($this->requestRepository, $requestStack);

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        // Act
        $provider->provide($this->operation, [], []);
    }

    #[Test]
    public function testDSRCollectionProviderReturnsEmptyArrayWhenNoRequestsFound(): void
    {
        // Arrange
        $this->requestRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn([]);

        $requestStack = $this->makeRequestStack(new Request());
        $provider = new DataSubjectRequestCollectionProvider($this->requestRepository, $requestStack);

        // Act
        $result = $provider->provide($this->operation, [], $this->tenantContext());

        // Assert
        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // DataSubjectRequestItemProvider tests
    // -------------------------------------------------------------------------

    #[Test]
    public function testDSRItemProviderReturnsRequest(): void
    {
        // Arrange
        $dsRequest = $this->makeDsRequest();
        $dsRequestIdString = $dsRequest->id()->toString();

        $this->requestRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(
                fn (DataSubjectRequestId $id) => $id->toString() === $dsRequestIdString,
            ))
            ->willReturn($dsRequest);

        $provider = new DataSubjectRequestItemProvider($this->requestRepository);

        // Act
        $result = $provider->provide($this->operation, ['id' => $dsRequestIdString], $this->tenantContext());

        // Assert
        self::assertInstanceOf(DataSubjectRequestEntity::class, $result);
        self::assertSame($dsRequestIdString, $result->getId());
        self::assertSame(self::CUSTOMER_ID, $result->getCustomerId());
        self::assertSame('access', $result->getRequestType());
        self::assertSame('pending', $result->getStatus());
    }

    #[Test]
    public function testDSRItemProviderThrowsWhenNotFound(): void
    {
        // Arrange
        $dsRequestId = DataSubjectRequestId::generate();

        $this->requestRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $provider = new DataSubjectRequestItemProvider($this->requestRepository);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('not found');

        // Act
        $provider->provide($this->operation, ['id' => $dsRequestId->toString()], []);
    }

    #[Test]
    public function testDSRItemProviderThrowsWhenIdMissing(): void
    {
        // Arrange
        $this->requestRepository
            ->expects(self::never())
            ->method('findById');

        $provider = new DataSubjectRequestItemProvider($this->requestRepository);

        // Assert
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Data subject request ID is required');

        // Act
        $provider->provide($this->operation, [], []);
    }
}
