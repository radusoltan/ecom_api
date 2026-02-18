<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Encryption\BlindIndexService;
use App\Shared\Infrastructure\Encryption\EncryptionService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-time command to encrypt existing plaintext PII data and populate blind indexes.
 *
 * Usage:
 *   php bin/console app:encrypt-existing-data           # Execute encryption
 *   php bin/console app:encrypt-existing-data --dry-run  # Preview without changes
 */
#[AsCommand(
    name: 'app:encrypt-existing-data',
    description: 'Encrypt existing plaintext PII data and populate blind indexes'
)]
final class EncryptExistingDataCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EncryptionService $encryptionService,
        private readonly BlindIndexService $blindIndexService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without executing');
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per batch', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        \assert($batchSize >= 1);

        if ($dryRun) {
            $io->warning('DRY RUN mode — no changes will be made.');
        }

        $io->title('Encrypting Existing PII Data');

        $totalEncrypted = 0;

        // 1. Users: email, username + blind indexes
        $totalEncrypted += $this->encryptUsersTable($io, $dryRun, $batchSize);

        // 2. Customers: email, first_name, last_name, phone_number + email blind index
        $totalEncrypted += $this->encryptCustomersTable($io, $dryRun, $batchSize);

        // 3. Customer Addresses: street, street2, city, state, postal_code
        $totalEncrypted += $this->encryptCustomerAddressesTable($io, $dryRun, $batchSize);

        // 4. Orders: customer_email, shipping_address, billing_address + email blind index
        $totalEncrypted += $this->encryptOrdersTable($io, $dryRun, $batchSize);

        // 5. Invoices: billing PII fields
        $totalEncrypted += $this->encryptInvoicesTable($io, $dryRun, $batchSize);

        // 6. Payments: gateway_reference, failure_message
        $totalEncrypted += $this->encryptPaymentsTable($io, $dryRun, $batchSize);

        // 7. Consents: ip_address, user_agent
        $totalEncrypted += $this->encryptConsentsTable($io, $dryRun, $batchSize);

        // 8. Data Subject Requests: reason, review_notes, rejection_reason, export_data
        $totalEncrypted += $this->encryptDataSubjectRequestsTable($io, $dryRun, $batchSize);

        $io->success(sprintf('Total rows processed: %d', $totalEncrypted));

        return Command::SUCCESS;
    }

    /** @param positive-int $batchSize */
    private function encryptUsersTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Users');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, email, username FROM users WHERE email_blind_index IS NULL'
        );

        $io->writeln(sprintf('Found %d unprocessed rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE users SET email = ?, email_blind_index = ?, username = ?, username_blind_index = ? WHERE id = ?',
                    [
                        $this->encryptionService->encrypt($row['email']),
                        $this->blindIndexService->generate($row['email']),
                        $this->encryptionService->encrypt($row['username']),
                        $this->blindIndexService->generate($row['username']),
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d user rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptCustomersTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Customers');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, email, first_name, last_name, phone_number FROM customers WHERE email_blind_index IS NULL'
        );

        $io->writeln(sprintf('Found %d unprocessed rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE customers SET email = ?, email_blind_index = ?, first_name = ?, last_name = ?, phone_number = ? WHERE id = ?',
                    [
                        $this->encryptionService->encrypt($row['email']),
                        $this->blindIndexService->generate($row['email']),
                        $this->encryptionService->encrypt($row['first_name']),
                        $this->encryptionService->encrypt($row['last_name']),
                        null !== $row['phone_number'] ? $this->encryptionService->encrypt($row['phone_number']) : null,
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d customer rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptCustomerAddressesTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Customer Addresses');
        // Use length check as heuristic: encrypted data is base64-encoded and much longer
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, street, street2, city, state, postal_code FROM customer_addresses WHERE LENGTH(street) < 500'
        );

        $io->writeln(sprintf('Found %d potentially unencrypted rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE customer_addresses SET street = ?, street2 = ?, city = ?, state = ?, postal_code = ? WHERE id = ?',
                    [
                        $this->encryptionService->encrypt($row['street']),
                        null !== $row['street2'] ? $this->encryptionService->encrypt($row['street2']) : null,
                        $this->encryptionService->encrypt($row['city']),
                        null !== $row['state'] ? $this->encryptionService->encrypt($row['state']) : null,
                        $this->encryptionService->encrypt($row['postal_code']),
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d customer address rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptOrdersTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Orders');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, customer_email, shipping_address, billing_address FROM orders WHERE customer_email_blind_index IS NULL'
        );

        $io->writeln(sprintf('Found %d unprocessed rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE orders SET customer_email = ?, customer_email_blind_index = ?, shipping_address = ?, billing_address = ? WHERE id = ?',
                    [
                        $this->encryptionService->encrypt($row['customer_email']),
                        $this->blindIndexService->generate($row['customer_email']),
                        $this->encryptionService->encrypt($row['shipping_address']),
                        $this->encryptionService->encrypt($row['billing_address']),
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d order rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptInvoicesTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Invoices');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, billing_name, billing_address_line1, billing_address_line2, billing_city, billing_postal_code, billing_vat_number, notes FROM invoices WHERE LENGTH(billing_name) < 500'
        );

        $io->writeln(sprintf('Found %d potentially unencrypted rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE invoices SET billing_name = ?, billing_address_line1 = ?, billing_address_line2 = ?, billing_city = ?, billing_postal_code = ?, billing_vat_number = ?, notes = ? WHERE id = ?',
                    [
                        $this->encryptionService->encrypt($row['billing_name']),
                        $this->encryptionService->encrypt($row['billing_address_line1']),
                        null !== $row['billing_address_line2'] ? $this->encryptionService->encrypt($row['billing_address_line2']) : null,
                        $this->encryptionService->encrypt($row['billing_city']),
                        $this->encryptionService->encrypt($row['billing_postal_code']),
                        null !== $row['billing_vat_number'] ? $this->encryptionService->encrypt($row['billing_vat_number']) : null,
                        null !== $row['notes'] ? $this->encryptionService->encrypt($row['notes']) : null,
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d invoice rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptPaymentsTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Payments');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, gateway_reference, failure_message FROM payments WHERE gateway_reference IS NOT NULL AND LENGTH(gateway_reference) < 500'
        );

        $io->writeln(sprintf('Found %d potentially unencrypted rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE payments SET gateway_reference = ?, failure_message = ? WHERE id = ?',
                    [
                        null !== $row['gateway_reference'] ? $this->encryptionService->encrypt($row['gateway_reference']) : null,
                        null !== $row['failure_message'] ? $this->encryptionService->encrypt($row['failure_message']) : null,
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d payment rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptConsentsTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Consents');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, ip_address, user_agent FROM consents WHERE LENGTH(ip_address) < 100'
        );

        $io->writeln(sprintf('Found %d potentially unencrypted rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE consents SET ip_address = ?, user_agent = ? WHERE id = ?',
                    [
                        $this->encryptionService->encrypt($row['ip_address']),
                        $this->encryptionService->encrypt($row['user_agent']),
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d consent rows', $count));

        return $count;
    }

    /** @param positive-int $batchSize */
    private function encryptDataSubjectRequestsTable(SymfonyStyle $io, bool $dryRun, int $batchSize): int
    {
        $io->section('Data Subject Requests');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, reason, review_notes, rejection_reason, export_data FROM data_subject_requests'
        );

        $io->writeln(sprintf('Found %d rows', \count($rows)));
        if ($dryRun || 0 === \count($rows)) {
            return \count($rows);
        }

        $count = 0;
        foreach (array_chunk($rows, $batchSize) as $batch) {
            foreach ($batch as $row) {
                $this->connection->executeStatement(
                    'UPDATE data_subject_requests SET reason = ?, review_notes = ?, rejection_reason = ?, export_data = ? WHERE id = ?',
                    [
                        null !== $row['reason'] ? $this->encryptionService->encrypt($row['reason']) : null,
                        null !== $row['review_notes'] ? $this->encryptionService->encrypt($row['review_notes']) : null,
                        null !== $row['rejection_reason'] ? $this->encryptionService->encrypt($row['rejection_reason']) : null,
                        null !== $row['export_data'] ? $this->encryptionService->encrypt($row['export_data']) : null,
                        $row['id'],
                    ]
                );
                ++$count;
            }
        }

        $io->writeln(sprintf('Encrypted %d data subject request rows', $count));

        return $count;
    }
}
