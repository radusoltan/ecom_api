<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\SubmitDataSubjectRequestCommand;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @implements ProcessorInterface<DataSubjectRequestEntity, DataSubjectRequestEntity>
 */
final readonly class SubmitDataSubjectRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private DataSubjectRequestRepositoryInterface $requestRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DataSubjectRequestEntity
    {
        assert($data instanceof DataSubjectRequestEntity);

        $tenantId = $this->getTenantId($context);

        $customerId = $data->getCustomerId();
        $requestType = $data->getRequestType();

        if ('' === $customerId) {
            throw new BadRequestHttpException('Customer ID is required');
        }

        if ('' === $requestType) {
            throw new BadRequestHttpException('Request type is required');
        }

        $command = new SubmitDataSubjectRequestCommand(
            tenantId: $tenantId,
            customerId: CustomerId::fromString($customerId),
            requestType: RequestType::fromString($requestType),
            reason: $data->getReason(),
        );

        $envelope = $this->commandBus->dispatch($command);
        $requestId = $envelope->last(HandledStamp::class)?->getResult();

        assert($requestId instanceof DataSubjectRequestId);

        $dsRequest = $this->requestRepository->findById($requestId);
        if (null === $dsRequest) {
            throw new \RuntimeException('Data subject request was created but could not be loaded');
        }

        return DataSubjectRequestEntity::fromDomainModel($dsRequest);
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
