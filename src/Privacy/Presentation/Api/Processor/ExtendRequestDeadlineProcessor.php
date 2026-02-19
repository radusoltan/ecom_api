<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Privacy\Application\Command\ExtendRequestDeadlineCommand;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<DataSubjectRequestEntity, DataSubjectRequestEntity>
 */
final readonly class ExtendRequestDeadlineProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private DataSubjectRequestRepositoryInterface $requestRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DataSubjectRequestEntity
    {
        $id = $uriVariables['id'] ?? null;
        if (null === $id) {
            throw new NotFoundHttpException('Request ID is required');
        }

        $requestId = DataSubjectRequestId::fromString($id);

        $request = $this->requestRepository->findById($requestId);
        if (null === $request) {
            throw new NotFoundHttpException(sprintf('Data subject request "%s" not found', $id));
        }

        $command = new ExtendRequestDeadlineCommand(
            requestId: $requestId,
        );

        $this->commandBus->dispatch($command);

        $updatedRequest = $this->requestRepository->findById($requestId);

        return DataSubjectRequestEntity::fromDomainModel($updatedRequest ?? $request);
    }
}
