<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Console;

use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\CategoryIndexer;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:reindex',
    description: 'Reindex products and categories in Elasticsearch',
)]
final class ReindexElasticsearchCommand extends Command
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductIndexer $productIndexer,
        private readonly CategoryIndexer $categoryIndexer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant-id', InputArgument::REQUIRED, 'Tenant ID to reindex')
            ->addOption('entity', 't', InputOption::VALUE_OPTIONAL, 'Entity type: products|categories|all', 'all')
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Locale (default: all enabled locales)')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command reindexes products and categories in Elasticsearch.

<info>php %command.full_name% TENANT_ID</info>

You can reindex only specific entities:
<info>php %command.full_name% TENANT_ID --entity=products</info>
<info>php %command.full_name% TENANT_ID --entity=categories</info>

You can reindex for a specific locale:
<info>php %command.full_name% TENANT_ID --locale=ro_RO</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenantIdString = $input->getArgument('tenant-id');
        $entityType = $input->getOption('entity');
        $localeString = $input->getOption('locale');

        try {
            $tenantId = TenantId::fromString($tenantIdString);
        } catch (\InvalidArgumentException $e) {
            $io->error(sprintf('Invalid tenant ID: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $locales = null !== $localeString
            ? [Locale::fromString($localeString)]
            : $this->getEnabledLocales($tenantId);

        $io->title('Elasticsearch Reindexing');
        $io->text(sprintf('Tenant: <info>%s</info>', $tenantId->toString()));
        $io->text(sprintf('Entity: <info>%s</info>', $entityType));
        $io->text(sprintf('Locales: <info>%s</info>', implode(', ', array_map(fn ($l) => $l->toString(), $locales))));
        $io->newLine();

        if ('all' === $entityType || 'products' === $entityType) {
            $this->reindexProducts($tenantId, $locales, $io);
        }

        if ('all' === $entityType || 'categories' === $entityType) {
            $this->reindexCategories($tenantId, $locales, $io);
        }

        $io->success('Reindexing completed!');

        return Command::SUCCESS;
    }

    private function reindexProducts(TenantId $tenantId, array $locales, SymfonyStyle $io): void
    {
        $io->section('Reindexing Products');

        $products = $this->productRepository->findByTenant($tenantId);
        $total = count($products);

        if (0 === $total) {
            $io->warning('No products found for this tenant');

            return;
        }

        $io->progressStart($total * count($locales));

        foreach ($locales as $locale) {
            foreach ($products as $product) {
                try {
                    $this->productIndexer->indexProduct($product, $locale);
                    $io->progressAdvance();
                } catch (\Exception $e) {
                    $io->error(sprintf(
                        'Failed to index product %s: %s',
                        $product->id()->toString(),
                        $e->getMessage()
                    ));
                }
            }
        }

        $io->progressFinish();
        $io->text(sprintf('<info>✓</info> Indexed <comment>%d</comment> products in <comment>%d</comment> locales', $total, count($locales)));
    }

    private function reindexCategories(TenantId $tenantId, array $locales, SymfonyStyle $io): void
    {
        $io->section('Reindexing Categories');

        $categories = $this->categoryRepository->findByTenant($tenantId);
        $total = count($categories);

        if (0 === $total) {
            $io->warning('No categories found for this tenant');

            return;
        }

        $io->progressStart($total * count($locales));

        foreach ($locales as $locale) {
            foreach ($categories as $category) {
                try {
                    $this->categoryIndexer->indexCategory($category, $locale);
                    $io->progressAdvance();
                } catch (\Exception $e) {
                    $io->error(sprintf(
                        'Failed to index category %s: %s',
                        $category->id()->toString(),
                        $e->getMessage()
                    ));
                }
            }
        }

        $io->progressFinish();
        $io->text(sprintf('<info>✓</info> Indexed <comment>%d</comment> categories in <comment>%d</comment> locales', $total, count($locales)));
    }

    /**
     * @return array<Locale>
     */
    private function getEnabledLocales(TenantId $tenantId): array
    {
        // TODO: Get from tenant configuration
        return [
            Locale::fromString('en_US'),
            Locale::fromString('ro_RO'),
        ];
    }
}
