<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<ConsentEntity>
 */
final readonly class ConsentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ConsentRepositoryInterface $consentRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return ConsentEntity[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenantId = $this->getTenantId($context);
        $request = $this->requestStack->getCurrentRequest();

        $customerId = $request?->query->get('customerId');
        $activeOnly = filter_var($request?->query->get('activeOnly', 'false'), \FILTER_VALIDATE_BOOLEAN);

        if (null !== $customerId && '' !== $customerId) {
            $customerIdVo = CustomerId::fromString($customerId);

            $consents = $activeOnly
                ? $this->consentRepository->findActiveByCustomerId($customerIdVo)
                : $this->consentRepository->findByCustomerId($customerIdVo);
        } else {
            $consents = $this->consentRepository->findByTenantId($tenantId);
        }

        return array_map(
            fn ($consent) => ConsentEntity::fromDomainModel($consent),
            $consents,
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
