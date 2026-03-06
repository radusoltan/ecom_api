<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\ValueObject\TaxJurisdiction as TaxJurisdictionVO;
use App\Tax\Infrastructure\Persistence\Doctrine\Entity\TaxRuleEntity;
use App\Tax\Infrastructure\Persistence\Doctrine\Repository\DoctrineTaxRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(DoctrineTaxRuleRepository::class)]
final class DoctrineTaxRuleRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $eventBus;
    private DoctrineTaxRuleRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);
        $this->repository = new DoctrineTaxRuleRepository($this->entityManager, $this->eventBus);
    }

    private function createTaxRuleMock(): TaxRule&MockObject
    {
        $id = $this->createMock(TaxRuleId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $taxRule = $this->createMock(TaxRule::class);
        $taxRule->method('id')->willReturn($id);
        $taxRule->method('pullDomainEvents')->willReturn([]);
        $taxRule->method('category')->willReturn(TaxCategory::STANDARD);

        return $taxRule;
    }

    private function createQueryBuilderMock(): QueryBuilder&MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    // ---- save() tests ----

    #[Test]
    public function saveCreatesNewEntityWhenNotExists(): void
    {
        $taxRule = $this->createTaxRuleMock();

        $this->entityManager->method('find')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($taxRule);
    }

    #[Test]
    public function saveUpdatesExistingEntity(): void
    {
        $taxRule = $this->createTaxRuleMock();

        $existingEntity = $this->createMock(TaxRuleEntity::class);
        $existingEntity->expects(self::once())->method('updateFromDomainModel');

        $this->entityManager->method('find')->willReturn($existingEntity);
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->repository->save($taxRule);
    }

    #[Test]
    public function saveDispatchesDomainEvents(): void
    {
        $event = new \stdClass();

        $id = $this->createMock(TaxRuleId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $taxRule = $this->createMock(TaxRule::class);
        $taxRule->method('id')->willReturn($id);
        $taxRule->method('pullDomainEvents')->willReturn([$event]);
        $taxRule->method('category')->willReturn(TaxCategory::STANDARD);

        $this->entityManager->method('find')->willReturn(null);
        $this->eventBus->expects(self::once())->method('dispatch')
            ->with($event)
            ->willReturn(new Envelope($event));

        $this->repository->save($taxRule);
    }

    // ---- findById() tests ----

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $id = $this->createMock(TaxRuleId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        self::assertNull($this->repository->findById($id));
    }

    #[Test]
    public function findByIdReturnsDomainModel(): void
    {
        $domainModel = $this->createMock(TaxRule::class);
        $entity = $this->createMock(TaxRuleEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $this->entityManager->method('find')->willReturn($entity);

        $id = $this->createMock(TaxRuleId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        self::assertSame($domainModel, $this->repository->findById($id));
    }

    // ---- findByTenantId() tests ----

    #[Test]
    public function findByTenantIdReturnsEmptyArray(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        self::assertSame([], $this->repository->findByTenantId($tenantId));
    }

    #[Test]
    public function findByTenantIdReturnsMappedModels(): void
    {
        $domainModel = $this->createMock(TaxRule::class);
        $entity = $this->createMock(TaxRuleEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$entity]);
        $this->entityManager->method('getRepository')->willReturn($repo);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $result = $this->repository->findByTenantId($tenantId);
        self::assertCount(1, $result);
        self::assertInstanceOf(TaxRule::class, $result[0]);
    }

    // ---- findApplicableRules() tests ----

    #[Test]
    public function findApplicableRulesReturnsEmptyWhenNoRegion(): void
    {
        $qb = $this->createQueryBuilderMock();
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $jurisdiction = $this->createMock(TaxJurisdiction::class);
        $jurisdiction->method('countryCode')->willReturn('US');
        $jurisdiction->method('regionCode')->willReturn(null);

        $result = $this->repository->findApplicableRules(
            $tenantId,
            $jurisdiction,
            TaxCategory::STANDARD,
            new \DateTimeImmutable(),
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function findApplicableRulesWithRegionCode(): void
    {
        $qb = $this->createQueryBuilderMock();
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $jurisdiction = $this->createMock(TaxJurisdiction::class);
        $jurisdiction->method('countryCode')->willReturn('US');
        $jurisdiction->method('regionCode')->willReturn('CA');

        $result = $this->repository->findApplicableRules(
            $tenantId,
            $jurisdiction,
            TaxCategory::STANDARD,
            new \DateTimeImmutable(),
        );

        self::assertSame([], $result);
    }

    // ---- findBestMatchingRule() tests ----

    #[Test]
    public function findBestMatchingRuleReturnsNullWhenNotFound(): void
    {
        $qb = $this->createQueryBuilderMock();
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $jurisdiction = $this->createMock(TaxJurisdiction::class);
        $jurisdiction->method('countryCode')->willReturn('US');
        $jurisdiction->method('regionCode')->willReturn(null);

        self::assertNull($this->repository->findBestMatchingRule(
            $tenantId,
            $jurisdiction,
            TaxCategory::STANDARD,
            new \DateTimeImmutable(),
        ));
    }

    #[Test]
    public function findBestMatchingRuleReturnsDomainModel(): void
    {
        $domainModel = $this->createMock(TaxRule::class);
        $entity = $this->createMock(TaxRuleEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($entity);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $jurisdiction = $this->createMock(TaxJurisdiction::class);
        $jurisdiction->method('countryCode')->willReturn('US');
        $jurisdiction->method('regionCode')->willReturn('CA');

        $result = $this->repository->findBestMatchingRule(
            $tenantId,
            $jurisdiction,
            TaxCategory::STANDARD,
            new \DateTimeImmutable(),
        );

        self::assertSame($domainModel, $result);
    }

    // ---- findByJurisdiction() tests ----

    #[Test]
    public function findByJurisdictionReturnsNullWhenNotFound(): void
    {
        $qb = $this->createQueryBuilderMock();
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $jurisdiction = $this->createMock(TaxJurisdictionVO::class);
        $jurisdiction->method('getCountryCode')->willReturn('US');
        $jurisdiction->method('getRegionCode')->willReturn(null);

        self::assertNull($this->repository->findByJurisdiction($jurisdiction, $tenantId));
    }

    #[Test]
    public function findByJurisdictionWithRegionReturnsModel(): void
    {
        $domainModel = $this->createMock(TaxRule::class);
        $entity = $this->createMock(TaxRuleEntity::class);
        $entity->method('toDomainModel')->willReturn($domainModel);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($entity);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $tenantId = $this->createMock(TenantId::class);
        $tenantId->method('toString')->willReturn('00000000-0000-4000-8000-000000000001');

        $jurisdiction = $this->createMock(TaxJurisdictionVO::class);
        $jurisdiction->method('getCountryCode')->willReturn('US');
        $jurisdiction->method('getRegionCode')->willReturn('NY');

        $result = $this->repository->findByJurisdiction($jurisdiction, $tenantId);
        self::assertSame($domainModel, $result);
    }

    // ---- delete() tests ----

    #[Test]
    public function deleteRemovesEntityWhenFound(): void
    {
        $entity = $this->createMock(TaxRuleEntity::class);
        $this->entityManager->method('find')->willReturn($entity);
        $this->entityManager->expects(self::once())->method('remove')->with($entity);
        $this->entityManager->expects(self::once())->method('flush');

        $id = $this->createMock(TaxRuleId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $this->repository->delete($id);
    }

    #[Test]
    public function deleteDoesNothingWhenNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);
        $this->entityManager->expects(self::never())->method('remove');
        $this->entityManager->expects(self::never())->method('flush');

        $id = $this->createMock(TaxRuleId::class);
        $id->method('toString')->willReturn('00000000-0000-4000-8000-000000000010');

        $this->repository->delete($id);
    }

    // ---- nextIdentity() test ----

    #[Test]
    public function nextIdentityReturnsTaxRuleId(): void
    {
        $result = $this->repository->nextIdentity();
        self::assertInstanceOf(TaxRuleId::class, $result);
    }
}
