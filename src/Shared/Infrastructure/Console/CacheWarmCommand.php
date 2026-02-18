<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheWarmingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:warm',
    description: 'Warm cache with frequently accessed data for better performance'
)]
final class CacheWarmCommand extends Command
{
    public function __construct(
        private readonly CacheWarmingService $cacheWarmingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant-id', InputArgument::OPTIONAL, 'Tenant ID to warm cache for (leave empty for all tenants)')
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Locale to warm (e.g., en_US, ro_RO)', 'en_US')
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command warms the cache with frequently accessed data.

Warm cache for specific tenant:
  <info>php %command.full_name% TENANT_ID</info>

Warm cache for all tenants:
  <info>php %command.full_name%</info>

Warm cache for specific locale:
  <info>php %command.full_name% TENANT_ID --locale=ro_RO</info>

This command should be run:
- After deployment
- During off-peak hours (scheduled via cron)
- After major data updates

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantIdString = $input->getArgument('tenant-id');
        $localeString = $input->getOption('locale');

        $io->title('Cache Warming Service');

        try {
            if (null !== $tenantIdString) {
                // Warm specific tenant
                $tenantId = TenantId::fromString($tenantIdString);
                $locale = Locale::fromString($localeString);

                $io->info(sprintf(
                    'Warming cache for tenant: %s (locale: %s)',
                    $tenantId->toString(),
                    $locale->toString()
                ));

                $stats = $this->cacheWarmingService->warmTenant($tenantId, $locale);

                $io->success('Cache warmed successfully!');
                $io->table(
                    ['Category', 'Items Cached'],
                    [
                        ['Translations', $stats['translations']],
                        ['Products', $stats['products']],
                        ['Categories', $stats['categories']],
                    ]
                );
            } else {
                // Warm all tenants
                $io->info('Warming cache for all active tenants...');

                $allStats = $this->cacheWarmingService->warmAllTenants();

                $io->success(sprintf('Cache warmed for %d tenants!', count($allStats)));

                // Show summary
                $totalTranslations = array_sum(array_column($allStats, 'translations'));
                $totalProducts = array_sum(array_column($allStats, 'products'));
                $totalCategories = array_sum(array_column($allStats, 'categories'));

                $io->table(
                    ['Category', 'Total Items Cached'],
                    [
                        ['Translations', $totalTranslations],
                        ['Products', $totalProducts],
                        ['Categories', $totalCategories],
                        ['Tenants', count($allStats)],
                    ]
                );
            }

            return Command::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('Cache warming failed: '.$e->getMessage());
            $io->note('Check logs for more details');

            return Command::FAILURE;
        }
    }
}
