<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Console;

use App\Cart\Application\Service\CartAbandonmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to process abandoned carts and send recovery emails.
 *
 * Usage:
 *   php bin/console app:cart:process-abandoned
 *   php bin/console app:cart:process-abandoned --dry-run
 *
 * Recommended cron schedule (every 6 hours):
 *   Run this command every 6 hours to process abandoned carts
 */
#[AsCommand(
    name: 'app:cart:process-abandoned',
    description: 'Process abandoned carts and send recovery emails'
)]
final class ProcessAbandonedCartsCommand extends Command
{
    public function __construct(
        private readonly CartAbandonmentService $abandonmentService,
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
                'Run in dry-run mode (show what would be processed without sending emails)'
            )
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command processes abandoned shopping carts and sends recovery emails to customers.

Business Rules:
- Carts are considered abandoned after 24 hours of inactivity
- Only sends one email per cart (tracks via abandonment_email_sent flag)
- Only processes carts with authenticated customers (requires email)
- Processes up to 100 carts per run

<info>php %command.full_name%</info>

You can run in dry-run mode to see what would be processed:
<info>php %command.full_name% --dry-run</info>

Recommended Cron Schedule:
<comment># Process abandoned carts every 6 hours</comment>
<comment>0 */6 * * * cd /path/to/project && php bin/console app:cart:process-abandoned</comment>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Cart Abandonment Recovery');

        if ($dryRun) {
            $io->warning('Running in DRY-RUN mode - no emails will be sent');
        }

        $io->section('Processing abandoned carts...');

        try {
            $startTime = microtime(true);

            if ($dryRun) {
                $io->info('DRY-RUN: Would process abandoned carts (no emails sent)');
                $stats = [
                    'processed' => 0,
                    'sent' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                ];
            } else {
                $stats = $this->abandonmentService->processAbandonedCarts();
            }

            $duration = round(microtime(true) - $startTime, 2);

            $io->section('Results');

            $io->table(
                ['Metric', 'Count'],
                [
                    ['Processed', $stats['processed']],
                    ['Emails Sent', $stats['sent']],
                    ['Skipped', $stats['skipped']],
                    ['Errors', $stats['errors']],
                ]
            );

            $io->info(sprintf('Completed in %s seconds', $duration));

            if ($stats['errors'] > 0) {
                $io->warning(sprintf(
                    '%d cart(s) encountered errors. Check logs for details.',
                    $stats['errors']
                ));

                return Command::FAILURE;
            }

            if (0 === $stats['processed']) {
                $io->success('No abandoned carts found to process.');
            } else {
                $io->success(sprintf(
                    'Successfully processed %d abandoned cart(s). Sent %d email(s).',
                    $stats['processed'],
                    $stats['sent']
                ));
            }

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error([
                'Failed to process abandoned carts',
                $exception->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->block($exception->getTraceAsString(), 'ERROR', 'fg=white;bg=red', ' ', true);
            }

            return Command::FAILURE;
        }
    }
}
