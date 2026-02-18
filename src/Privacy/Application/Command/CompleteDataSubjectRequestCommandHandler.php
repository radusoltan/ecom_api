<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CompleteDataSubjectRequestCommandHandler
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository,
    ) {
    }

    public function __invoke(CompleteDataSubjectRequestCommand $command): void
    {
        $request = $this->requestRepository->findById($command->requestId);

        if (null === $request) {
            throw new \InvalidArgumentException(sprintf('Data subject request not found: %s', $command->requestId->toString()));
        }

        $request->complete($command->exportData);

        $this->requestRepository->save($request);
    }
}
