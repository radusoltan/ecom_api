<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<DataSubjectRequestEntity>
 */
final readonly class DataSubjectRequestCollectionProvider implements ProviderInterface
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return DataSubjectRequestEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $this->getTenantId($context);
        $request = $this->requestStack->getCurrentRequest();

        $customerId = $request?->query->get('customerId');
        $status = $request?->query->get('status');
        $type = $request?->query->get('type');

        if (null !== $customerId && '' !== $customerId) {
            $requests = $this->requestRepository->findByCustomerId(
                CustomerId::fromString($customerId),
            );
        } elseif (null !== $status && '' !== $status) {
            $requests = $this->requestRepository->findByStatus(
                RequestStatus::fromString($status),
                $tenantId,
            );
        } elseif (null !== $type && '' !== $type) {
            $requests = $this->requestRepository->findByType(
                RequestType::fromString($type),
                $tenantId,
            );
        } else {
            $requests = $this->requestRepository->findByTenantId($tenantId);
        }

        return array_map(
            fn ($dsRequest) => DataSubjectRequestEntity::fromDomainModel($dsRequest),
            $requests,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function getTenantId(array $context): TenantId
    {
        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new BadRequestHttpException('Tenant ID not found in context. Ensure X-Tenant-ID header is provided.');
        }

        return TenantId::fromString($tenantIdString);
    }
}
