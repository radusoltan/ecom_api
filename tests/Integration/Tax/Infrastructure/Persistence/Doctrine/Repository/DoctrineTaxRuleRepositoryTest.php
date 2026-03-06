<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTaxRuleRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private TaxRuleRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(TaxRuleRepositoryInterface::class);

        $this->cleanupTaxRuleData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTaxRuleData();
        parent::tearDown();
    }

    private function cleanupTaxRuleData(): void
    {
        $em = $this->getEntityManager();
        $em->getConnection()->executeStatement(
            'DELETE FROM tax_rules WHERE tenant_id = :tid',
            ['tid' => $this->tenantId->toString()]
        );
    }

    private function createTaxRule(?TaxRuleId $id = null, string $name = 'Standard VAT DE'): TaxRule
    {
        return TaxRule::create(
            id: $id ?? TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: $name,
        );
    }

    public function testSaveAndFindById(): void
    {
        $rule = $this->createTaxRule();

        $this->repository->save($rule);

        $found = $this->repository->findById($rule->id());

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($rule->id()));
        self::assertTrue($found->tenantId()->equals($this->tenantId));
        self::assertSame('Standard VAT DE', $found->name());
        self::assertSame(19.0, $found->rate()->percentage());
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $nonExistentId = TaxRuleId::generate();

        $result = $this->repository->findById($nonExistentId);

        self::assertNull($result);
    }

    public function testFindByTenantId(): void
    {
        $rule1 = $this->createTaxRule(name: 'Standard VAT DE');
        $rule2 = $this->createTaxRule(name: 'Reduced VAT DE');

        // Adjust rule2 to use reduced category to avoid any conflicts
        $rule2 = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::REDUCED,
            rate: TaxRate::fromPercentage(7.0),
            name: 'Reduced VAT DE',
        );

        $this->repository->save($rule1);
        $this->repository->save($rule2);

        $rules = $this->repository->findByTenantId($this->tenantId);

        self::assertGreaterThanOrEqual(2, count($rules));
    }

    public function testDeleteTaxRule(): void
    {
        $rule = $this->createTaxRule();
        $this->repository->save($rule);

        self::assertNotNull($this->repository->findById($rule->id()));

        $this->repository->delete($rule->id());

        self::assertNull($this->repository->findById($rule->id()));
    }

    public function testNextIdentityGeneratesValidId(): void
    {
        $id = $this->repository->nextIdentity();

        self::assertInstanceOf(TaxRuleId::class, $id);
        self::assertNotEmpty($id->toString());
    }
}
