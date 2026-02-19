<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Repository;

use App\Payment\Domain\Model\WebhookEvent;
use App\Payment\Domain\Repository\WebhookEventRepositoryInterface;
use App\Payment\Infrastructure\Persistence\Doctrine\Entity\WebhookEventEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineORMWebhookEventRepository implements WebhookEventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function existsByGatewayAndExternalEventId(string $gateway, string $externalEventId): bool
    {
        $repository = $this->entityManager->getRepository(WebhookEventEntity::class);

        return null !== $repository->findOneBy([
            'gateway' => strtolower($gateway),
            'externalEventId' => $externalEventId,
        ]);
    }

    public function save(WebhookEvent $webhookEvent): void
    {
        $entity = WebhookEventEntity::fromDomainModel($webhookEvent);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(WebhookEventEntity::class, 'w')
            ->where('w.processedAt < :before')
            ->setParameter('before', $before);

        return (int) $qb->getQuery()->execute();
    }
}
