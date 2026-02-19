<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\GrantConsentCommand;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<ConsentEntity, ConsentEntity>
 */
final readonly class GrantConsentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private ConsentRepositoryInterface $consentRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConsentEntity
    {
        assert($data instanceof ConsentEntity);

        $tenantId = $this->getTenantId($context);
        $request = $this->requestStack->getCurrentRequest();

        $customerId = $data->getCustomerId();
        $purpose = $data->getPurpose();
        $consentText = $data->getConsentText();
        $consentVersion = $data->getConsentVersion();

        if ('' === $customerId) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        if ('' === $purpose) {
            throw new BadRequestHttpException('Purpose is required');
        }

        if ('' === $consentText) {
            throw new BadRequestHttpException('Consent text is required');
        }

        if ('' === $consentVersion) {
            throw new BadRequestHttpException('Consent version is required');
        }

        $command = new GrantConsentCommand(
            tenantId: $tenantId,
            customerId: CustomerId::fromString($customerId),
            purpose: ConsentPurpose::fromString($purpose),
            ipAddress: $request?->getClientIp() ?? '0.0.0.0',
            userAgent: $request?->headers->get('User-Agent') ?? 'Unknown',
            consentText: $consentText,
            consentVersion: $consentVersion,
        );

        $envelope = $this->commandBus->dispatch($command);
        $consentId = $envelope->last(HandledStamp::class)?->getResult();

        assert($consentId instanceof ConsentId);

        $consent = $this->consentRepository->findById($consentId);
        if (null === $consent) {
            throw new \RuntimeException('Consent was created but could not be loaded');
        }

        return ConsentEntity::fromDomainModel($consent);
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
