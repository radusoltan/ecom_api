<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Command;

use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Warm Translation Cache Command
 *
 * Pre-loads all translations into Redis cache.
 * Should be executed on deployment or after cache clear.
 *
 * Usage:
 *   php bin/console translations:cache:warm {tenantId}
 *   php bin/console translations:cache:warm --all
 */
#[AsCommand(
    name: 'translations:cache:warm',
    description: 'Warm translation cache by pre-loading all translations into Redis'
)]
final class WarmTranslationCacheCommand extends Command
{
    public function __construct(
        private readonly TranslationCacheService $cacheService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenantId', InputArgument::OPTIONAL, 'Tenant ID to warm cache for')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Warm cache for all tenants')
            ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear cache before warming')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command pre-loads all translations into Redis cache:

  <info>php %command.full_name% {tenantId}</info>

To warm cache for all tenants:

  <info>php %command.full_name% --all</info>

To clear and warm cache:

  <info>php %command.full_name% {tenantId} --clear</info>

This command should be executed:
- On deployment
- After cache clear
- After bulk import operations

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenantIdStr = $input->getArgument('tenantId');
        $warmAll = $input->getOption('all');
        $clearFirst = $input->getOption('clear');

        // Validate arguments
        if (!$tenantIdStr && !$warmAll) {
            $io->error('You must provide either a tenantId or use --all option');
            return Command::FAILURE;
        }

        if ($tenantIdStr && $warmAll) {
            $io->error('You cannot use both tenantId and --all option');
            return Command::FAILURE;
        }

        // Get tenant IDs to process
        $tenantIds = [];
        if ($warmAll) {
            // In production, this would query all tenant IDs from database
            // For now, using environment variable
            $envTenantId = $_ENV['DEFAULT_TENANT_ID'] ?? null;
            if ($envTenantId) {
                $tenantIds[] = $envTenantId;
            } else {
                $io->warning('No tenants found. Please implement tenant lookup logic.');
                return Command::FAILURE;
            }
        } else {
            $tenantIds[] = $tenantIdStr;
        }

        $io->title('Translation Cache Warming');

        $totalWarmed = 0;

        foreach ($tenantIds as $tenantIdStr) {
            try {
                $tenantId = TenantId::fromString($tenantIdStr);

                $io->section(sprintf('Processing tenant: %s', $tenantId->toString()));

                // Clear cache if requested
                if ($clearFirst) {
                    $io->text('Clearing existing cache...');
                    $this->cacheService->clearCache($tenantId);
                    $io->success('Cache cleared');
                }

                // Warm cache
                $io->text('Warming cache...');
                $startTime = microtime(true);

                $warmedCount = $this->cacheService->warmCache($tenantId);

                $duration = (microtime(true) - $startTime) * 1000; // Convert to ms

                $io->success(sprintf(
                    'Cache warmed: %d locale/domain combinations in %.2fms',
                    $warmedCount,
                    $duration
                ));

                $totalWarmed += $warmedCount;
            } catch (\Exception $e) {
                $io->error(sprintf(
                    'Failed to warm cache for tenant %s: %s',
                    $tenantIdStr,
                    $e->getMessage()
                ));

                if ($io->isVerbose()) {
                    $io->text($e->getTraceAsString());
                }

                return Command::FAILURE;
            }
        }

        $io->success(sprintf(
            'Translation cache warming completed: %d total combinations warmed',
            $totalWarmed
        ));

        return Command::SUCCESS;
    }
}
