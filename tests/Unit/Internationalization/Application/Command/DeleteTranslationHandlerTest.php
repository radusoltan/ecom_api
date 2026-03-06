<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Application\Command;

use App\Internationalization\Application\Command\DeleteTranslation;
use App\Internationalization\Application\Command\DeleteTranslationHandler;
use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Domain\Repository\TranslationRepositoryInterface;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteTranslationHandlerTest extends TestCase
{
    private TranslationRepositoryInterface&MockObject $repository;
    private TranslationCacheService&MockObject $cacheService;
    private DeleteTranslationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationRepositoryInterface::class);
        $this->cacheService = $this->createMock(TranslationCacheService::class);
        $this->handler = new DeleteTranslationHandler($this->repository, $this->cacheService);
    }

    public function testDeleteTranslationSuccessfully(): void
    {
        $translationId = TranslationId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $translation = Translation::create(
            id: $translationId,
            tenantId: $tenantId,
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('greeting'),
            locale: LanguageCode::en(),
            value: TranslationValue::fromString('Hello'),
        );

        $command = new DeleteTranslation(
            id: $translationId->toString(),
            tenantId: $tenantId->toString(),
        );

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn($translation);

        $this->repository->expects(self::once())
            ->method('delete');

        $this->cacheService->expects(self::once())
            ->method('invalidate');

        ($this->handler)($command);
    }

    public function testDeleteTranslationThrowsWhenNotFound(): void
    {
        $command = new DeleteTranslation(
            id: TranslationId::generate()->toString(),
            tenantId: '00000000-0000-4000-8000-000000000001',
        );

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
