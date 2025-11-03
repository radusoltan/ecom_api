<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExtendRequestDeadlineCommandHandler
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository
    ) {
    }

    public function __invoke(ExtendRequestDeadlineCommand $command): void
    {
        $request = $this->requestRepository->findById($command->requestId);

        if ($request === null) {
            throw new InvalidArgumentException(
                sprintf('Data subject request not found: %s', $command->requestId->toString())
            );
        }

        $request->extendDeadline();

        $this->requestRepository->save($request);
    }
}
