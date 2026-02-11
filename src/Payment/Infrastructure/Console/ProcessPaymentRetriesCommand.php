<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Console;

use App\Payment\Application\Service\PaymentRetryService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to process payment retries.
 *
 * This command should be run periodically (e.g., via cron every hour) to:
 * - Find payments that are due for retry
 * - Attempt to reprocess them through the payment gateway
 * - Update payment status based on retry result
 * - Schedule next retry or mark as exhausted
 *
 * Usage:
 * - php bin/console app:payment:process-retries
 * - php bin/console app:payment:process-retries --dry-run
 * - php bin/console app:payment:process-retries --limit=10
 *
 * Recommended cron schedule:
 * - 0 * * * * cd /path/to/project && php bin/console app:payment:process-retries >> /var/log/payment-retries.log 2>&1
 */
#[AsCommand(
    name: 'app:payment:process-retries',
    description: 'Process pending payment retries',
)]
final class ProcessPaymentRetriesCommand extends Command
{
    public function __construct(
        private readonly PaymentRetryService $retryService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run in dry-run mode (don\'t actually process retries, just show what would be done)'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of payments to process',
                100
            )
            ->setHelp(
                <<<'HELP'
The <info>app:payment:process-retries</info> command processes payments that are due for retry.

This command should be run periodically via cron to automatically retry failed payments.

<comment>Examples:</comment>

  # Process all pending retries (up to 100)
  <info>php bin/console app:payment:process-retries</info>

  # Dry run to see what would be processed
  <info>php bin/console app:payment:process-retries --dry-run</info>

  # Process only 10 payments
  <info>php bin/console app:payment:process-retries --limit=10</info>

<comment>Recommended cron schedule:</comment>

  # Run every hour
  0 * * * * cd /path/to/project && php bin/console app:payment:process-retries

<comment>How it works:</comment>

1. Finds payments with status=failed AND next_retry_at <= now
2. Attempts to process each payment through the gateway
3. Updates payment status based on result:
   - Success: Payment authorized/captured
   - Failure (retries remaining): Schedule next retry
   - Failure (max retries): Mark as exhausted, notify customer

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        $io->title('Payment Retry Processor');

        if ($isDryRun) {
            $io->warning('Running in DRY-RUN mode - no actual retries will be processed');
        }

        try {
            // Get payments due for retry
            $now = new \DateTimeImmutable();
            $payments = $this->retryService->getPaymentsDueForRetry($now);
            $totalPayments = count($payments);

            if (0 === $totalPayments) {
                $io->success('No payments due for retry');

                return Command::SUCCESS;
            }

            $io->note(sprintf('Found %d payment(s) due for retry', $totalPayments));

            // Limit processing
            $payments = array_slice($payments, 0, $limit);
            $processCount = count($payments);

            if ($processCount < $totalPayments) {
                $io->warning(sprintf('Processing only %d of %d payments (limit applied)', $processCount, $totalPayments));
            }

            // Process each payment
            $successCount = 0;
            $failureCount = 0;
            $skippedCount = 0;

            $io->progressStart($processCount);

            foreach ($payments as $payment) {
                $paymentId = $payment->id()->toString();

                try {
                    if ($isDryRun) {
                        $io->writeln(sprintf(
                            "\n[DRY-RUN] Would retry payment %s (attempt %d/%d, next retry: %s)",
                            $paymentId,
                            $payment->retryCount() + 1,
                            3, // max attempts from policy
                            $payment->nextRetryAt()?->format('Y-m-d H:i:s') ?? 'N/A'
                        ));
                        ++$skippedCount;
                    } else {
                        $wasProcessed = $this->retryService->processRetry($payment);

                        if ($wasProcessed) {
                            ++$successCount;
                            $this->logger->info('Payment retry processed successfully', [
                                'payment_id' => $paymentId,
                                'attempt' => $payment->retryCount(),
                            ]);
                        } else {
                            ++$failureCount;
                            $this->logger->warning('Payment retry processing failed', [
                                'payment_id' => $paymentId,
                            ]);
                        }
                    }

                    $io->progressAdvance();
                } catch (\Throwable $e) {
                    ++$failureCount;
                    $this->logger->error('Exception while processing payment retry', [
                        'payment_id' => $paymentId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $io->progressAdvance();
                }
            }

            $io->progressFinish();

            // Display results
            $io->newLine(2);
            $io->section('Results');

            $resultTable = [];

            if ($isDryRun) {
                $resultTable[] = ['Payments found', $totalPayments];
                $resultTable[] = ['Would process', $skippedCount];
            } else {
                $resultTable[] = ['Total found', $totalPayments];
                $resultTable[] = ['Processed', $processCount];
                $resultTable[] = ['Successful', $successCount];
                $resultTable[] = ['Failed', $failureCount];
            }

            $io->horizontalTable(
                ['Metric', 'Count'],
                $resultTable
            );

            if ($isDryRun) {
                $io->info('Dry-run completed. Re-run without --dry-run to process retries.');

                return Command::SUCCESS;
            }

            if ($failureCount > 0) {
                $io->warning(sprintf('%d payment(s) failed to process. Check logs for details.', $failureCount));

                return Command::FAILURE;
            }

            $io->success(sprintf('Successfully processed %d payment retry(ies)', $successCount));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Payment retry command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Command failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
