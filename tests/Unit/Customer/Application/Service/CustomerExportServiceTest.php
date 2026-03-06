<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Service;

use App\Customer\Application\Service\CustomerExportService;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerExportService::class)]
final class CustomerExportServiceTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $repository;
    private CustomerExportService $service;
    private TenantId $tenantId;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->service = new CustomerExportService($this->repository);
        $this->tenantId = TenantId::fromString(self::TENANT_ID);
    }

    // -----------------------------------------------------------------------
    // CSV export
    // -----------------------------------------------------------------------

    #[Test]
    public function itExportsEmptyCustomerListAsCsv(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $csv = $this->service->exportToCsv($this->tenantId);

        self::assertStringContainsString('email', $csv);
        self::assertStringContainsString('first_name', $csv);
        self::assertStringContainsString('last_name', $csv);
    }

    #[Test]
    public function itExportsCsvWithCustomerData(): void
    {
        $customer = $this->makeCustomer('export@example.com', 'Alice', 'Wonder');
        $this->repository->method('findAll')->willReturn([$customer]);

        $csv = $this->service->exportToCsv($this->tenantId);

        self::assertStringContainsString('export@example.com', $csv);
        self::assertStringContainsString('Alice', $csv);
        self::assertStringContainsString('Wonder', $csv);
    }

    #[Test]
    public function itExportsCsvWithMultipleCustomers(): void
    {
        $c1 = $this->makeCustomer('first@example.com', 'First', 'One');
        $c2 = $this->makeCustomer('second@example.com', 'Second', 'Two');
        $this->repository->method('findAll')->willReturn([$c1, $c2]);

        $csv = $this->service->exportToCsv($this->tenantId);

        self::assertStringContainsString('first@example.com', $csv);
        self::assertStringContainsString('second@example.com', $csv);
    }

    #[Test]
    public function itFiltersBySegmentWhenSegmentFilterProvided(): void
    {
        $customer = $this->makeCustomer('vip@example.com', 'Vip', 'User');
        $this->repository
            ->expects(self::once())
            ->method('findBySegment')
            ->with('vip', $this->tenantId)
            ->willReturn([$customer]);

        $csv = $this->service->exportToCsv($this->tenantId, ['segment' => 'vip']);

        self::assertStringContainsString('vip@example.com', $csv);
    }

    #[Test]
    public function itFiltersActiveCustomersByStatusFilter(): void
    {
        $active = $this->makeCustomer('active@example.com', 'Active', 'User');
        $inactive = $this->makeInactiveCustomer('inactive@example.com', 'Inactive', 'User');

        $this->repository->method('findAll')->willReturn([$active, $inactive]);

        $csv = $this->service->exportToCsv($this->tenantId, ['status' => 'active']);

        self::assertStringContainsString('active@example.com', $csv);
        self::assertStringNotContainsString('inactive@example.com', $csv);
    }

    #[Test]
    public function itFiltersInactiveCustomersByStatusFilter(): void
    {
        $active = $this->makeCustomer('john.doe@example.com', 'Active', 'User');
        $inactive = $this->makeInactiveCustomer('inactive@example.com', 'Inactive', 'User');

        $this->repository->method('findAll')->willReturn([$active, $inactive]);

        $csv = $this->service->exportToCsv($this->tenantId, ['status' => 'inactive']);

        self::assertStringContainsString('inactive@example.com', $csv);
        self::assertStringNotContainsString('john.doe@example.com', $csv);
    }

    #[Test]
    public function itFiltersByDateFrom(): void
    {
        $old = $this->makeCustomerWithDate('old@example.com', 'Old', 'User', new \DateTimeImmutable('2020-01-01'));
        $new = $this->makeCustomer('new@example.com', 'New', 'User');

        $this->repository->method('findAll')->willReturn([$old, $new]);

        $csv = $this->service->exportToCsv(
            $this->tenantId,
            ['date_from' => new \DateTimeImmutable('2024-01-01')]
        );

        self::assertStringNotContainsString('old@example.com', $csv);
        self::assertStringContainsString('new@example.com', $csv);
    }

    #[Test]
    public function itFiltersByDateTo(): void
    {
        $old = $this->makeCustomerWithDate('old@example.com', 'Old', 'User', new \DateTimeImmutable('2020-01-01'));
        $new = $this->makeCustomer('new@example.com', 'New', 'User'); // createdAt = now

        $this->repository->method('findAll')->willReturn([$old, $new]);

        $csv = $this->service->exportToCsv(
            $this->tenantId,
            ['date_to' => new \DateTimeImmutable('2022-01-01')]
        );

        self::assertStringContainsString('old@example.com', $csv);
        self::assertStringNotContainsString('new@example.com', $csv);
    }

    // -----------------------------------------------------------------------
    // JSON export
    // -----------------------------------------------------------------------

    #[Test]
    public function itExportsEmptyListAsJson(): void
    {
        $this->repository->method('findAll')->willReturn([]);

        $json = $this->service->exportToJson($this->tenantId);
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertEmpty($data);
    }

    #[Test]
    public function itExportsCustomerAsJson(): void
    {
        $customer = $this->makeCustomer('json@example.com', 'Json', 'User');
        $this->repository->method('findAll')->willReturn([$customer]);

        $json = $this->service->exportToJson($this->tenantId);
        $data = json_decode($json, true);

        self::assertCount(1, $data);
        self::assertSame('json@example.com', $data[0]['email']);
        self::assertSame('Json', $data[0]['first_name']);
        self::assertSame('User', $data[0]['last_name']);
        self::assertArrayHasKey('id', $data[0]);
        self::assertArrayHasKey('segment', $data[0]);
        self::assertArrayHasKey('loyalty_points', $data[0]);
        self::assertArrayHasKey('status', $data[0]);
    }

    #[Test]
    public function itExportsActiveStatusCorrectlyInJson(): void
    {
        $active = $this->makeCustomer('active@example.com', 'Active', 'User');
        $inactive = $this->makeInactiveCustomer('inactive@example.com', 'Inactive', 'User');
        $this->repository->method('findAll')->willReturn([$active, $inactive]);

        $json = $this->service->exportToJson($this->tenantId);
        $data = json_decode($json, true);

        $statuses = array_column($data, 'status', 'email');
        self::assertSame('active', $statuses['active@example.com']);
        self::assertSame('inactive', $statuses['inactive@example.com']);
    }

    // -----------------------------------------------------------------------
    // CSV template
    // -----------------------------------------------------------------------

    #[Test]
    public function itGetsCsvTemplate(): void
    {
        $template = $this->service->getCsvTemplate();

        self::assertStringContainsString('email', $template);
        self::assertStringContainsString('first_name', $template);
        self::assertStringContainsString('last_name', $template);
        self::assertStringContainsString('john.doe@example.com', $template);
        self::assertStringContainsString('jane.smith@example.com', $template);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeCustomer(string $email, string $firstName, string $lastName): Customer
    {
        return Customer::register(
            id: CustomerId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            email: Email::fromString($email),
            firstName: $firstName,
            lastName: $lastName,
        );
    }

    private function makeInactiveCustomer(string $email, string $firstName, string $lastName): Customer
    {
        $customer = $this->makeCustomer($email, $firstName, $lastName);
        $customer->deactivate();

        return $customer;
    }

    private function makeCustomerWithDate(
        string $email,
        string $firstName,
        string $lastName,
        \DateTimeImmutable $createdAt,
    ): Customer {
        // We need to use reconstituteFromPersistence to set a specific createdAt
        return Customer::reconstituteFromPersistence(
            id: CustomerId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            email: Email::fromString($email),
            firstName: $firstName,
            lastName: $lastName,
            phoneNumber: null,
            segment: \App\Customer\Domain\ValueObject\CustomerSegment::regular(),
            loyaltyPoints: 0,
            isActive: true,
            preferences: \App\Customer\Domain\ValueObject\CustomerPreferences::create(),
            consents: \App\Customer\Domain\ValueObject\CustomerConsent::default(),
            notificationPreferences: \App\Customer\Domain\ValueObject\NotificationPreferences::default(),
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }
}
