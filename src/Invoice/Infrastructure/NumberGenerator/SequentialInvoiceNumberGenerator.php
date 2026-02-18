<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\NumberGenerator;

use App\Invoice\Domain\Exception\InvoiceNumberGenerationException;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Sequential Invoice Number Generator using PostgreSQL atomic function.
 *
 * Uses the database function `get_next_invoice_number(tenant_id, year)`
 * for atomic, gap-free sequence generation per tenant per year.
 *
 * Business Rules:
 * - Invoice numbers must be sequential per tenant per year
 * - Format: INV-{YEAR}-{SEQUENCE} e.g., INV-2025-000001
 * - Sequence resets to 1 at the start of each year
 * - No gaps allowed in sequence (legal requirement in EU)
 * - Must be atomic to prevent duplicate numbers under concurrency
 */
final readonly class SequentialInvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function generate(TenantId $tenantId, ?int $year = null): InvoiceNumber
    {
        $year = $year ?? (int) date('Y');

        try {
            // Call PostgreSQL function for atomic increment
            $sql = 'SELECT get_next_invoice_number(:tenant_id, :year) as sequence';

            $result = $this->connection->executeQuery($sql, [
                'tenant_id' => $tenantId->toString(),
                'year' => $year,
            ])->fetchAssociative();

            if (false === $result || !isset($result['sequence'])) {
                throw InvoiceNumberGenerationException::failedToGenerate($tenantId, 'Database function returned no result');
            }

            $sequence = (int) $result['sequence'];

            $invoiceNumber = InvoiceNumber::create($year, $sequence);

            $this->logger->info('Generated invoice number', [
                'tenant_id' => $tenantId->toString(),
                'invoice_number' => $invoiceNumber->toString(),
                'year' => $year,
                'sequence' => $sequence,
            ]);

            return $invoiceNumber;
        } catch (InvoiceNumberGenerationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate invoice number', [
                'tenant_id' => $tenantId->toString(),
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            throw InvoiceNumberGenerationException::failedToGenerate($tenantId, $e->getMessage());
        }
    }

    public function getCurrentSequence(TenantId $tenantId, ?int $year = null): int
    {
        $year = $year ?? (int) date('Y');

        $sql = 'SELECT current_sequence FROM invoice_sequences
                WHERE tenant_id = :tenant_id AND year = :year';

        $result = $this->connection->executeQuery($sql, [
            'tenant_id' => $tenantId->toString(),
            'year' => $year,
        ])->fetchAssociative();

        return $result ? (int) $result['current_sequence'] : 0;
    }

    public function previewNext(TenantId $tenantId, ?int $year = null): InvoiceNumber
    {
        $year = $year ?? (int) date('Y');
        $currentSequence = $this->getCurrentSequence($tenantId, $year);

        return InvoiceNumber::create($year, $currentSequence + 1);
    }
}
