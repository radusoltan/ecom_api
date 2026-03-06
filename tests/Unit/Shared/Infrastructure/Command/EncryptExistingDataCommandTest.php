<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Command\EncryptExistingDataCommand;
use App\Shared\Infrastructure\Encryption\BlindIndexService;
use App\Shared\Infrastructure\Encryption\EncryptionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(EncryptExistingDataCommand::class)]
#[Group('unit')]
#[Group('infrastructure')]
final class EncryptExistingDataCommandTest extends TestCase
{
    private Connection&MockObject $connection;
    private EncryptionService&MockObject $encryptionService;
    private BlindIndexService&MockObject $blindIndexService;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->encryptionService = $this->createMock(EncryptionService::class);
        $this->blindIndexService = $this->createMock(BlindIndexService::class);

        $command = new EncryptExistingDataCommand(
            $this->connection,
            $this->encryptionService,
            $this->blindIndexService,
        );

        $this->tester = new CommandTester($command);
    }

    // -----------------------------------------------------------------------
    // Dry-run mode — no writes happen, counts returned
    // -----------------------------------------------------------------------

    #[Test]
    public function itRunsInDryRunModeWithNoRows(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->encryptionService->expects(self::never())->method('encrypt');
        $this->connection->expects(self::never())->method('executeStatement');

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('DRY RUN', $this->tester->getDisplay());
        self::assertStringContainsString('Total rows processed: 0', $this->tester->getDisplay());
    }

    #[Test]
    public function itRunsInDryRunModeAndReportsCounts(): void
    {
        // Each table returns some rows in dry-run
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['id' => '1', 'email' => 'a@b.com', 'username' => 'user1']], // users (1 row)
                [], // customers
                [], // customer_addresses
                [], // orders
                [], // invoices
                [], // payments
                [], // consents
                [], // data_subject_requests
                [], // notifications
                [], // orders vat
            );

        $this->encryptionService->expects(self::never())->method('encrypt');

        $exit = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Total rows processed: 1', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Normal mode — empty tables (nothing to encrypt)
    // -----------------------------------------------------------------------

    #[Test]
    public function itSucceedsWhenAllTablesAreEmpty(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->encryptionService->expects(self::never())->method('encrypt');
        $this->connection->expects(self::never())->method('executeStatement');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Total rows processed: 0', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Users table encryption
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsUsersTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM users')) {
                    return [
                        ['id' => 'uuid-1', 'email' => 'alice@example.com', 'username' => 'alice'],
                        ['id' => 'uuid-2', 'email' => 'bob@example.com', 'username' => 'bob'],
                    ];
                }

                return [];
            });

        $this->encryptionService
            ->method('encrypt')
            ->willReturnCallback(fn (string $v) => 'ENC:'.$v);

        $this->blindIndexService
            ->method('generate')
            ->willReturnCallback(fn (string $v) => 'IDX:'.$v);

        $this->connection
            ->expects(self::exactly(2))
            ->method('executeStatement')
            ->with(
                self::stringContains('UPDATE users'),
                self::anything(),
            );

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 2 user rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsUsersTableWithBatchSize(): void
    {
        $rows = array_map(
            fn (int $i) => ['id' => "uuid-$i", 'email' => "user$i@test.com", 'username' => "user$i"],
            range(1, 5),
        );

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) use ($rows): array {
                if (str_contains($sql, 'FROM users')) {
                    return $rows;
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        $this->blindIndexService->method('generate')->willReturn('blind');

        $this->connection
            ->expects(self::exactly(5))
            ->method('executeStatement');

        $exit = $this->tester->execute(['--batch-size' => '2']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 5 user rows', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Customers table encryption
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsCustomersTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM customers')) {
                    return [
                        [
                            'id' => 'cust-1',
                            'email' => 'cust@example.com',
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'phone_number' => '+1234567890',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        $this->blindIndexService->method('generate')->willReturn('blind');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE customers'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 customer rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsCustomersTableWithNullPhoneNumber(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM customers')) {
                    return [
                        [
                            'id' => 'cust-1',
                            'email' => 'cust@example.com',
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'phone_number' => null,
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        $this->blindIndexService->method('generate')->willReturn('blind');

        // phone_number null — encrypt called 3 times (email, first_name, last_name), NOT phone
        $this->encryptionService->expects(self::exactly(3))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Customer addresses table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsCustomerAddressesTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM customer_addresses')) {
                    return [
                        [
                            'id' => 'addr-1',
                            'street' => '123 Main St',
                            'street2' => 'Apt 4',
                            'city' => 'Springfield',
                            'state' => 'IL',
                            'postal_code' => '62701',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE customer_addresses'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 customer address rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsCustomerAddressesWithNullOptionalFields(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM customer_addresses')) {
                    return [
                        [
                            'id' => 'addr-1',
                            'street' => '123 Main St',
                            'street2' => null,
                            'city' => 'Springfield',
                            'state' => null,
                            'postal_code' => '62701',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        // street, city, postal_code = 3 calls (street2 and state are null)
        $this->encryptionService->expects(self::exactly(3))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Orders table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsOrdersTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM orders WHERE customer_email_blind_index')) {
                    return [
                        [
                            'id' => 'order-1',
                            'customer_email' => 'order@example.com',
                            'shipping_address' => '{}',
                            'billing_address' => '{}',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        $this->blindIndexService->method('generate')->willReturn('blind');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE orders SET customer_email'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 order rows', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Invoices table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsInvoicesTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM invoices')) {
                    return [
                        [
                            'id' => 'inv-1',
                            'billing_name' => 'Acme Corp',
                            'billing_address_line1' => '1 Street',
                            'billing_address_line2' => null,
                            'billing_city' => 'NY',
                            'billing_postal_code' => '10001',
                            'billing_vat_number' => null,
                            'notes' => null,
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        // billing_name, billing_address_line1, billing_city, billing_postal_code = 4 calls
        $this->encryptionService->expects(self::exactly(4))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 invoice rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsInvoicesWithAllOptionalFields(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM invoices')) {
                    return [
                        [
                            'id' => 'inv-1',
                            'billing_name' => 'Acme Corp',
                            'billing_address_line1' => '1 Street',
                            'billing_address_line2' => 'Suite 100',
                            'billing_city' => 'NY',
                            'billing_postal_code' => '10001',
                            'billing_vat_number' => 'VAT123',
                            'notes' => 'Some notes',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        // All 7 fields get encrypted
        $this->encryptionService->expects(self::exactly(7))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Payments table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsPaymentsTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM payments')) {
                    return [
                        [
                            'id' => 'pay-1',
                            'gateway_reference' => 'pi_abc123',
                            'failure_message' => null,
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        // gateway_reference only (failure_message is null)
        $this->encryptionService->expects(self::exactly(1))->method('encrypt');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE payments'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 payment rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsPaymentsWithFailureMessage(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM payments')) {
                    return [
                        [
                            'id' => 'pay-1',
                            'gateway_reference' => 'pi_abc123',
                            'failure_message' => 'Card declined',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        $this->encryptionService->expects(self::exactly(2))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Consents table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsConsentsTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM consents')) {
                    return [
                        ['id' => 'con-1', 'ip_address' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE consents'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 consent rows', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Data Subject Requests table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsDataSubjectRequestsTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM data_subject_requests')) {
                    return [
                        [
                            'id' => 'dsr-1',
                            'reason' => 'I want my data deleted',
                            'review_notes' => null,
                            'rejection_reason' => null,
                            'export_data' => null,
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        // only reason is non-null
        $this->encryptionService->expects(self::exactly(1))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 data subject request rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsDataSubjectRequestsWithAllFields(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM data_subject_requests')) {
                    return [
                        [
                            'id' => 'dsr-1',
                            'reason' => 'Delete my data',
                            'review_notes' => 'Reviewed by admin',
                            'rejection_reason' => null,
                            'export_data' => '{"key":"value"}',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        $this->encryptionService->expects(self::exactly(3))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Notifications table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsNotificationsTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM notifications')) {
                    return [
                        [
                            'id' => 'notif-1',
                            'recipient_email' => 'notif@example.com',
                            'recipient_phone' => '+1555000000',
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE notifications'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 notification rows', $this->tester->getDisplay());
    }

    #[Test]
    public function itEncryptsNotificationsWithNullPhone(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM notifications')) {
                    return [
                        [
                            'id' => 'notif-1',
                            'recipient_email' => 'notif@example.com',
                            'recipient_phone' => null,
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');
        // Only email encrypted (phone is null)
        $this->encryptionService->expects(self::exactly(1))->method('encrypt');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Orders VAT number table
    // -----------------------------------------------------------------------

    #[Test]
    public function itEncryptsOrdersVatNumberTable(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM orders WHERE vat_number')) {
                    return [
                        ['id' => 'order-1', 'vat_number' => 'VAT123456'],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('encrypted');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('UPDATE orders SET vat_number'), self::anything());

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Encrypted 1 order VAT number rows', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Multiple tables with data
    // -----------------------------------------------------------------------

    #[Test]
    public function itAggregatesTotalAcrossAllTables(): void
    {
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'FROM users')) {
                    return [['id' => 'u1', 'email' => 'a@b.com', 'username' => 'a']];
                }
                if (str_contains($sql, 'FROM customers')) {
                    return [
                        [
                            'id' => 'c1',
                            'email' => 'c@b.com',
                            'first_name' => 'C',
                            'last_name' => 'D',
                            'phone_number' => null,
                        ],
                    ];
                }

                return [];
            });

        $this->encryptionService->method('encrypt')->willReturn('enc');
        $this->blindIndexService->method('generate')->willReturn('idx');

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        // 1 user + 1 customer = 2
        self::assertStringContainsString('Total rows processed: 2', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // batch-size min clamping
    // -----------------------------------------------------------------------

    #[Test]
    public function itClampsNegativeBatchSizeToOne(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $exit = $this->tester->execute(['--batch-size' => '-5']);

        self::assertSame(Command::SUCCESS, $exit);
    }

    #[Test]
    public function itClampsZeroBatchSizeToOne(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $exit = $this->tester->execute(['--batch-size' => '0']);

        self::assertSame(Command::SUCCESS, $exit);
    }
}
