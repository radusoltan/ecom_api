<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Application\Command;

use App\Internationalization\Application\Command\UpdateTranslation;
use App\Internationalization\Application\Command\UpdateTranslationHandler;
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

final class UpdateTranslationHandlerTest extends TestCase
{
    private TranslationRepositoryInterface&MockObject $repository;
    private TranslationCacheService&MockObject $cacheService;
    private UpdateTranslationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationRepositoryInterface::class);
        $this->cacheService = $this->createMock(TranslationCacheService::class);
        $this->handler = new UpdateTranslationHandler($this->repository, $this->cacheService);
    }

    public function testUpdateTranslationSuccessfully(): void
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

        $command = new UpdateTranslation(
            id: $translationId->toString(),
            tenantId: $tenantId->toString(),
            value: 'Hello Updated!',
            parameters: ['count' => '3'],
        );

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn($translation);

        $this->repository->expects(self::once())
            ->method('save');

        $this->cacheService->expects(self::once())
            ->method('invalidate');

        $result = ($this->handler)($command);

        self::assertSame('Hello Updated!', $result->value()->value());
        self::assertSame(['count' => '3'], $result->parameters());
    }

    public function testUpdateTranslationThrowsWhenNotFound(): void
    {
        $command = new UpdateTranslation(
            id: TranslationId::generate()->toString(),
            tenantId: '00000000-0000-4000-8000-000000000001',
            value: 'Updated',
        );

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
