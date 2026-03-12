<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Faker\Generator;

abstract class AbstractTenantAwareFixture extends Fixture
{
    private const US_STATE_CODES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
    ];

    /** @var array<string, Generator> */
    private array $fakerPool = [];

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
    ) {
    }

    protected function activateTenantContext(TenantId|string $tenantId): void
    {
        $resolved = $tenantId instanceof TenantId ? $tenantId : TenantId::fromString($tenantId);

        $this->tenantContext->setCurrentTenant($resolved);
    }

    protected function clearTenantContext(): void
    {
        $this->tenantContext->clearCurrentTenant();
    }

    protected function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    protected function faker(string $scope): Generator
    {
        if (!isset($this->fakerPool[$scope])) {
            $faker = Factory::create('en_US');
            $faker->seed(abs(crc32(static::class.'|'.$scope)));
            $this->fakerPool[$scope] = $faker;
        }

        return $this->fakerPool[$scope];
    }

    protected function usStateCode(Generator $faker): string
    {
        /** @var string $stateCode */
        $stateCode = $faker->randomElement(self::US_STATE_CODES);

        return $stateCode;
    }

    /**
     * @return array<string, string>
     */
    protected function tenantIdsByOwnerEmail(): array
    {
        $tenantIds = [];
        $this->connection()->executeStatement("SELECT set_config('app.bypass_rls', 'true', false)");

        try {
            foreach (Sprint3SeedData::tenants() as $tenant) {
                $tenantIds[$tenant['ownerEmail']] = (string) $this->connection()->fetchOne(
                    'SELECT id FROM tenants WHERE owner_email = :ownerEmail LIMIT 1',
                    ['ownerEmail' => $tenant['ownerEmail']]
                );
            }
        } finally {
            $this->connection()->executeStatement('RESET app.bypass_rls');
        }

        return $tenantIds;
    }
}
