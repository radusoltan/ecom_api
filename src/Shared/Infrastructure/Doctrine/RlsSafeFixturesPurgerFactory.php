<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Forces TRUNCATE purging for fixture loads so RLS-protected rows are fully removed.
 */
/** @template-implements PurgerFactory<ORMPurger> */
final class RlsSafeFixturesPurgerFactory implements PurgerFactory
{
    public function createForEntityManager(
        ?string $emName,
        EntityManagerInterface $em,
        array $excluded = [],
        bool $purgeWithTruncate = false,
    ): ORMPurger {
        $purger = new ORMPurger($em, $excluded);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        return $purger;
    }
}
