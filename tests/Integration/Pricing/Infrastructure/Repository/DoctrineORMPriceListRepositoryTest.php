<?php

declare(strict_types=1);

namespace App\Tests\Integration\Pricing\Infrastructure\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\PricingRule;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineORMPriceListRepositoryTest extends KernelTestCase
{
    private PriceListRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->repository = $container->get(PriceListRepositoryInterface::class);

        // Clean up test price lists
        $this->cleanupTestPriceLists();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestPriceLists();
        parent::tearDown();
    }

    private function cleanupTestPriceLists(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $qb = $em->createQueryBuilder();
        $qb->delete(PriceListEntity::class, 'pl')
            ->where('pl.name LIKE :prefix1 OR pl.name LIKE :prefix2 OR pl.name LIKE :prefix3 OR pl.name LIKE :prefix4 OR pl.name LIKE :prefix5 OR pl.name LIKE :prefix6 OR pl.name LIKE :prefix7')
            ->setParameter('prefix1', 'Test Price List%')
            ->setParameter('prefix2', 'Active Price List%')
            ->setParameter('prefix3', 'Valid Price List%')
            ->setParameter('prefix4', 'Inactive Price List%')
            ->setParameter('prefix5', 'Summer Sale%')
            ->setParameter('prefix6', 'Tenant 1 Price%')
            ->setParameter('prefix7', 'Tenant 2 Price%')
            ->getQuery()
            ->execute();
    }

    // ============================================
    // save() Tests
    // ============================================

    public function testSaveInsertsPriceList(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        $this->repository->save($priceList);

        $found = $this->repository->findById($priceList->id());

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($priceList->id()));
        $this->assertSame('Test Price List', $found->name()->value());
        $this->assertSame(100, $found->priority()); // Default priority
        $this->assertFalse($found->isActive()); // Created inactive
    }

    public function testSaveUpdatesPriceList(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        $this->repository->save($priceList);

        // Update the price list
        $priceList->update(
            PriceListName::fromString('Updated Price List'),
            200,
            null,
            null
        );

        $this->repository->save($priceList);

        $found = $this->repository->findById($priceList->id());

        $this->assertNotNull($found);
        $this->assertSame('Updated Price List', $found->name()->value());
        $this->assertSame(200, $found->priority());
    }

    public function testSavePriceListWithRules(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        $productId = ProductId::generate();
        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(10.0)));
        $priceList->addRule(PricingRule::forCategory('electronics', Discount::fixed(Money::of('20.00', 'USD'))));

        $this->repository->save($priceList);

        $found = $this->repository->findById($priceList->id());

        $this->assertNotNull($found);
        $this->assertCount(2, $found->rules());
    }

    public function testSaveDispatchesDomainEvents(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        // Domain events are recorded
        $this->assertCount(1, $priceList->popEvents()); // PriceListCreated

        // Create new instance to test event dispatching
        $freshPriceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List 2')
        );

        $this->repository->save($freshPriceList);

        // Events should be cleared after save (popEvents)
        $this->assertCount(0, $freshPriceList->popEvents());
    }

    // ============================================
    // findById() Tests
    // ============================================

    public function testFindByIdReturnsNull(): void
    {
        $nonExistentId = PriceListId::generate();

        $found = $this->repository->findById($nonExistentId);

        $this->assertNull($found);
    }

    public function testFindByIdReturnsPriceList(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        $this->repository->save($priceList);

        $found = $this->repository->findById($priceList->id());

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($priceList->id()));
    }

    // ============================================
    // findByTenant() Tests
    // ============================================

    public function testFindByTenantReturnsAllPriceLists(): void
    {
        $tenantId = TenantId::generate();

        $priceList1 = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Test Price List 1'),
            300
        );

        $priceList2 = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Test Price List 2'),
            100
        );

        $this->repository->save($priceList1);
        $this->repository->save($priceList2);

        $priceLists = $this->repository->findByTenant($tenantId);

        $this->assertGreaterThanOrEqual(2, count($priceLists));

        // Should be ordered by priority DESC
        $priorities = array_map(fn ($pl) => $pl->priority(), $priceLists);
        $this->assertGreaterThanOrEqual($priorities[1], $priorities[0]);
    }

    public function testFindByTenantActiveOnlyFiltersInactive(): void
    {
        $tenantId = TenantId::generate();

        $activePriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Active Price List')
        );
        $activePriceList->activate();

        $inactivePriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Inactive Price List')
        );

        $this->repository->save($activePriceList);
        $this->repository->save($inactivePriceList);

        $priceLists = $this->repository->findByTenant($tenantId, activeOnly: true);

        $names = array_map(fn ($pl) => $pl->name()->value(), $priceLists);

        $this->assertContains('Active Price List', $names);
        $this->assertNotContains('Inactive Price List', $names);
    }

    // ============================================
    // findValidForTenant() Tests
    // ============================================

    public function testFindValidForTenantReturnsOnlyActive(): void
    {
        $tenantId = TenantId::generate();

        $activePriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Active Price List')
        );
        $activePriceList->activate();

        $inactivePriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Inactive Price List')
        );

        $this->repository->save($activePriceList);
        $this->repository->save($inactivePriceList);

        $validPriceLists = $this->repository->findValidForTenant($tenantId);

        $names = array_map(fn ($pl) => $pl->name()->value(), $validPriceLists);

        $this->assertContains('Active Price List', $names);
        $this->assertNotContains('Inactive Price List', $names);
    }

    public function testFindValidForTenantRespectsValidFromDate(): void
    {
        $tenantId = TenantId::generate();

        // Future price list (not yet valid)
        $futurePriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Valid Price List Future'),
            100,
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable('+7 days')
        );
        $futurePriceList->activate();

        // Current price list (valid now)
        $currentPriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Valid Price List Current'),
            100,
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('+7 days')
        );
        $currentPriceList->activate();

        $this->repository->save($futurePriceList);
        $this->repository->save($currentPriceList);

        $validPriceLists = $this->repository->findValidForTenant($tenantId);

        $names = array_map(fn ($pl) => $pl->name()->value(), $validPriceLists);

        $this->assertContains('Valid Price List Current', $names);
        $this->assertNotContains('Valid Price List Future', $names);
    }

    public function testFindValidForTenantRespectsValidToDate(): void
    {
        $tenantId = TenantId::generate();

        // Expired price list
        $expiredPriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Valid Price List Expired'),
            100,
            new \DateTimeImmutable('-7 days'),
            new \DateTimeImmutable('-1 day')
        );
        $expiredPriceList->activate();

        // Current price list
        $currentPriceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Valid Price List Current'),
            100,
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('+7 days')
        );
        $currentPriceList->activate();

        $this->repository->save($expiredPriceList);
        $this->repository->save($currentPriceList);

        $validPriceLists = $this->repository->findValidForTenant($tenantId);

        $names = array_map(fn ($pl) => $pl->name()->value(), $validPriceLists);

        $this->assertContains('Valid Price List Current', $names);
        $this->assertNotContains('Valid Price List Expired', $names);
    }

    public function testFindValidForTenantAcceptsNullDates(): void
    {
        $tenantId = TenantId::generate();

        // Price list with no date restrictions
        $priceList = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Valid Price List No Dates'),
            100,
            null,
            null
        );
        $priceList->activate();

        $this->repository->save($priceList);

        $validPriceLists = $this->repository->findValidForTenant($tenantId);

        $names = array_map(fn ($pl) => $pl->name()->value(), $validPriceLists);

        $this->assertContains('Valid Price List No Dates', $names);
    }

    // ============================================
    // delete() Tests
    // ============================================

    public function testDeleteRemovesPriceList(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        $this->repository->save($priceList);

        $found = $this->repository->findById($priceList->id());
        $this->assertNotNull($found);

        $this->repository->delete($priceList);

        $notFound = $this->repository->findById($priceList->id());
        $this->assertNull($notFound);
    }

    public function testDeleteNonExistentPriceListDoesNothing(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        // Don't save, just try to delete
        $this->repository->delete($priceList);

        // Should not throw exception
        $this->assertTrue(true);
    }

    // ============================================
    // Multi-tenancy Tests
    // ============================================

    public function testTenantIsolation(): void
    {
        $tenant1Id = TenantId::generate();
        $tenant2Id = TenantId::generate();

        $priceList1 = PriceList::create(
            PriceListId::generate(),
            $tenant1Id,
            PriceListName::fromString('Tenant 1 Price List')
        );

        $priceList2 = PriceList::create(
            PriceListId::generate(),
            $tenant2Id,
            PriceListName::fromString('Tenant 2 Price List')
        );

        $this->repository->save($priceList1);
        $this->repository->save($priceList2);

        $tenant1PriceLists = $this->repository->findByTenant($tenant1Id);
        $tenant2PriceLists = $this->repository->findByTenant($tenant2Id);

        $tenant1Names = array_map(fn ($pl) => $pl->name()->value(), $tenant1PriceLists);
        $tenant2Names = array_map(fn ($pl) => $pl->name()->value(), $tenant2PriceLists);

        $this->assertContains('Tenant 1 Price List', $tenant1Names);
        $this->assertNotContains('Tenant 2 Price List', $tenant1Names);
        $this->assertContains('Tenant 2 Price List', $tenant2Names);
        $this->assertNotContains('Tenant 1 Price List', $tenant2Names);
    }

    // ============================================
    // Complex Scenario Tests
    // ============================================

    public function testRoundTripWithComplexPriceList(): void
    {
        $validFrom = new \DateTimeImmutable('2025-06-01');
        $validTo = new \DateTimeImmutable('2025-08-31');

        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Summer Sale 2025'),
            500,
            $validFrom,
            $validTo
        );

        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $priceList->addRule(PricingRule::forProduct($product1, Discount::percentage(20.0), 3, Money::of('50.00', 'USD')));
        $priceList->addRule(PricingRule::forProduct($product2, Discount::fixed(Money::of('25.00', 'EUR')), 1, null));
        $priceList->addRule(PricingRule::forCategory('summer-wear', Discount::percentage(15.0), 2, Money::of('100.00', 'USD')));
        $priceList->activate();

        $this->repository->save($priceList);

        $found = $this->repository->findById($priceList->id());

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($priceList->id()));
        $this->assertSame('Summer Sale 2025', $found->name()->value());
        $this->assertSame(500, $found->priority());
        $this->assertTrue($found->isActive());
        $this->assertCount(3, $found->rules());
        $this->assertEquals($validFrom, $found->validFrom());
        $this->assertEquals($validTo, $found->validTo());
    }
}
