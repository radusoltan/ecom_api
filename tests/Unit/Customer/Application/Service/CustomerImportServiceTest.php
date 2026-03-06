<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Service;

use App\Customer\Application\Service\CustomerImportService;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(CustomerImportService::class)]
final class CustomerImportServiceTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private CustomerImportService $service;
    private TenantId $tenantId;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Entity manager begins/commits transactions for each batch
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('commit');

        $this->service = new CustomerImportService(
            $this->repository,
            $this->entityManager,
            new NullLogger(),
        );

        $this->tenantId = TenantId::fromString(self::TENANT_ID);
    }

    // -----------------------------------------------------------------------
    // CSV import – happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itImportsNewCustomersFromCsv(): void
    {
        $csv = "email,first_name,last_name\njohn.doe@example.com,John,Doe\n";

        $this->repository->method('findByEmail')->willReturn(null);
        $this->repository->expects(self::once())->method('save');

        $result = $this->service->importFromCsv($this->tenantId, $csv);

        self::assertSame(1, $result->totalRows);
        self::assertSame(1, $result->imported);
        self::assertSame(0, $result->updated);
        self::assertSame(0, $result->skipped);
        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function itSkipsDuplicateWhenUpdateExistingIsFalse(): void
    {
        $csv = "email,first_name,last_name\njohn.doe@example.com,John,Doe\n";

        $existing = $this->makeCustomer('john.doe@example.com');
        $this->repository->method('findByEmail')->willReturn($existing);
        $this->repository->expects(self::never())->method('save');

        $result = $this->service->importFromCsv($this->tenantId, $csv, false);

        self::assertSame(1, $result->skipped);
        self::assertSame(0, $result->imported);
        self::assertFalse($result->isSuccessful());
    }

    #[Test]
    public function itUpdatesExistingCustomerWhenUpdateExistingIsTrue(): void
    {
        $csv = "email,first_name,last_name\njohn.doe@example.com,Jane,Smith\n";

        $existing = $this->makeCustomer('john.doe@example.com');
        $this->repository->method('findByEmail')->willReturn($existing);
        $this->repository->expects(self::once())->method('save');

        $result = $this->service->importFromCsv($this->tenantId, $csv, true);

        self::assertSame(1, $result->updated);
        self::assertSame(0, $result->imported);
        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function itThrowsForEmptyCsv(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->importFromCsv($this->tenantId, '');
    }

    #[Test]
    public function itThrowsWhenRequiredCsvColumnsAreMissing(): void
    {
        $csv = "email,first_name\njohn@example.com,John\n";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required columns');

        $this->service->importFromCsv($this->tenantId, $csv);
    }

    #[Test]
    public function itSkipsRowWithValidationErrors(): void
    {
        // empty email is invalid
        $csv = "email,first_name,last_name\n,John,Doe\n";

        $result = $this->service->importFromCsv($this->tenantId, $csv);

        self::assertSame(1, $result->skipped);
        self::assertSame(0, $result->imported);
    }

    // -----------------------------------------------------------------------
    // JSON import
    // -----------------------------------------------------------------------

    #[Test]
    public function itImportsNewCustomersFromJson(): void
    {
        $json = json_encode([
            ['email' => 'alice@example.com', 'first_name' => 'Alice', 'last_name' => 'Wonder'],
        ]);

        $this->repository->method('findByEmail')->willReturn(null);
        $this->repository->expects(self::once())->method('save');

        $result = $this->service->importFromJson($this->tenantId, $json);

        self::assertSame(1, $result->imported);
        self::assertTrue($result->isSuccessful());
    }

    #[Test]
    public function itThrowsForInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON format');

        $this->service->importFromJson($this->tenantId, '{not valid json}');
    }

    #[Test]
    public function itThrowsWhenJsonIsNotArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON content must be an array');

        $this->service->importFromJson($this->tenantId, '"just a string"');
    }

    #[Test]
    public function itImportsMultipleRowsFromJson(): void
    {
        $json = json_encode([
            ['email' => 'a@example.com', 'first_name' => 'Anna', 'last_name' => 'Apple'],
            ['email' => 'b@example.com', 'first_name' => 'Bob', 'last_name' => 'Baker'],
        ]);

        $this->repository->method('findByEmail')->willReturn(null);
        $this->repository->expects(self::exactly(2))->method('save');

        $result = $this->service->importFromJson($this->tenantId, $json);

        self::assertSame(2, $result->totalRows);
        self::assertSame(2, $result->imported);
    }

    // -----------------------------------------------------------------------
    // Row validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itValidatesRowWithAllRequiredFieldsMissing(): void
    {
        $errors = $this->service->validateRow([], 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('email', $fields);
        self::assertContains('first_name', $fields);
        self::assertContains('last_name', $fields);
    }

    #[Test]
    public function itValidatesRowWithInvalidEmail(): void
    {
        $row = ['email' => 'not-an-email', 'first_name' => 'John', 'last_name' => 'Doe'];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('email', $fields);
    }

    #[Test]
    public function itValidatesRowWithInvalidPhoneFormat(): void
    {
        $row = [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '123456789', // missing leading +
        ];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('phone_number', $fields);
    }

    #[Test]
    public function itValidatesRowWithValidE164Phone(): void
    {
        $row = [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+40723456789',
        ];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertNotContains('phone_number', $fields);
    }

    #[Test]
    public function itValidatesRowWithInvalidSegment(): void
    {
        $row = [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'segment' => 'platinum',
        ];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('segment', $fields);
    }

    #[Test]
    public function itValidatesRowWithInvalidStatus(): void
    {
        $row = [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'pending',
        ];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('status', $fields);
    }

    #[Test]
    public function itValidatesRowWithTooShortFirstName(): void
    {
        $row = ['email' => 'john@example.com', 'first_name' => 'J', 'last_name' => 'Doe'];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('first_name', $fields);
    }

    #[Test]
    public function itValidatesRowWithTooLongLastName(): void
    {
        $row = ['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => str_repeat('X', 51)];
        $errors = $this->service->validateRow($row, 1);

        $fields = array_map(fn ($e) => $e->field, $errors);
        self::assertContains('last_name', $fields);
    }

    #[Test]
    public function itReturnsNoErrorsForValidRow(): void
    {
        $row = [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+40723456789',
            'segment' => 'vip',
            'status' => 'active',
        ];

        $errors = $this->service->validateRow($row, 1);

        self::assertEmpty($errors);
    }

    // -----------------------------------------------------------------------
    // Segment & status handling during import
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsInactiveStatusOnNewCustomer(): void
    {
        $json = json_encode([
            ['email' => 'inactive@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'status' => 'inactive'],
        ]);

        $this->repository->method('findByEmail')->willReturn(null);

        $savedCustomers = [];
        $this->repository->method('save')->willReturnCallback(
            function (Customer $c) use (&$savedCustomers) { $savedCustomers[] = $c; }
        );

        $result = $this->service->importFromJson($this->tenantId, $json);

        self::assertSame(1, $result->imported);
        self::assertFalse($savedCustomers[0]->isActive());
    }

    #[Test]
    public function itHandlesPartialSuccessResult(): void
    {
        $json = json_encode([
            ['email' => 'good@example.com', 'first_name' => 'Good', 'last_name' => 'User'],
            ['email' => 'bad-email', 'first_name' => 'Bad', 'last_name' => 'User'],
        ]);

        $this->repository->method('findByEmail')->willReturn(null);

        $result = $this->service->importFromJson($this->tenantId, $json);

        self::assertSame(1, $result->imported);
        self::assertSame(1, $result->skipped);
        self::assertTrue($result->hasPartialSuccess());
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function makeCustomer(string $email): Customer
    {
        return Customer::register(
            id: CustomerId::generate(),
            tenantId: $this->tenantId,
            email: Email::fromString($email),
            firstName: 'Original',
            lastName: 'Customer',
        );
    }
}
