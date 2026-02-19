<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\DTO\ConsentDTO;
use App\Privacy\Application\Query\GetCustomerConsentsQuery;
use App\Privacy\Application\Query\GetCustomerConsentsQueryHandler;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerConsentsQueryHandler::class)]
final class GetCustomerConsentsQueryHandlerTest extends TestCase
{
    private ConsentRepositoryInterface $repository;
    private GetCustomerConsentsQueryHandler $handler;
    private string $validConsentText;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConsentRepositoryInterface::class);
        $this->handler = new GetCustomerConsentsQueryHandler($this->repository);
        $this->validConsentText = 'I consent to the processing of my personal data for the purpose of marketing communications as described in the privacy policy.';
    }

    #[Test]
    public function handleReturnsAllConsentsForCustomer(): void
    {
        $customerId = CustomerId::generate();
        $consent = Consent::grant(
            ConsentId::generate(),
            TenantId::generate(),
            $customerId,
            ConsentPurpose::marketing(),
            '192.168.1.1',
            'Mozilla/5.0',
            $this->validConsentText,
            'v1.0.0'
        );

        $query = new GetCustomerConsentsQuery($customerId);

        $this->repository->expects(self::once())
            ->method('findByCustomerId')
            ->with($customerId)
            ->willReturn([$consent]);

        $this->repository->expects(self::never())
            ->method('findActiveByCustomerId');

        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(ConsentDTO::class, $result[0]);
        self::assertSame('marketing', $result[0]->purpose);
        self::assertTrue($result[0]->isGranted);
    }

    #[Test]
    public function handleReturnsActiveOnlyWhenFiltered(): void
    {
        $customerId = CustomerId::generate();
        $query = new GetCustomerConsentsQuery($customerId, activeOnly: true);

        $this->repository->expects(self::once())
            ->method('findActiveByCustomerId')
            ->with($customerId)
            ->willReturn([]);

        $this->repository->expects(self::never())
            ->method('findByCustomerId');

        $result = ($this->handler)($query);

        self::assertEmpty($result);
    }

    #[Test]
    public function handleReturnsEmptyArrayWhenNoConsents(): void
    {
        $query = new GetCustomerConsentsQuery(CustomerId::generate());

        $this->repository->expects(self::once())
            ->method('findByCustomerId')
            ->willReturn([]);

        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }
}
