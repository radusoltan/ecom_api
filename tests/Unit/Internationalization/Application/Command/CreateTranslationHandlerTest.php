<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Application\Command;

use App\Internationalization\Application\Command\CreateTranslation;
use App\Internationalization\Application\Command\CreateTranslationHandler;
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

final class CreateTranslationHandlerTest extends TestCase
{
    private TranslationRepositoryInterface&MockObject $repository;
    private TranslationCacheService&MockObject $cacheService;
    private CreateTranslationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TranslationRepositoryInterface::class);
        $this->cacheService = $this->createMock(TranslationCacheService::class);
        $this->handler = new CreateTranslationHandler($this->repository, $this->cacheService);
    }

    public function testCreateTranslationSuccessfully(): void
    {
        $command = new CreateTranslation(
            tenantId: '00000000-0000-4000-8000-000000000001',
            locale: 'en',
            domain: 'messages',
            key: 'greeting.hello',
            value: 'Hello!',
            parameters: ['name' => 'user'],
        );

        $this->repository->expects(self::once())
            ->method('findByKey')
            ->willReturn(null);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Translation::class));

        $this->cacheService->expects(self::once())
            ->method('invalidate');

        $result = ($this->handler)($command);

        self::assertInstanceOf(Translation::class, $result);
        self::assertSame('Hello!', $result->value()->value());
        self::assertSame('en', $result->locale()->value());
        self::assertSame(['name' => 'user'], $result->parameters());
    }

    public function testCreateTranslationThrowsWhenDuplicate(): void
    {
        $command = new CreateTranslation(
            tenantId: '00000000-0000-4000-8000-000000000001',
            locale: 'en',
            domain: 'messages',
            key: 'greeting.hello',
            value: 'Hello!',
        );

        $existingTranslation = Translation::create(
            id: TranslationId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            domain: TranslationDomain::fromString('messages'),
            key: TranslationKey::fromString('greeting.hello'),
            locale: LanguageCode::en(),
            value: TranslationValue::fromString('Existing'),
        );
        $this->repository->expects(self::once())
            ->method('findByKey')
            ->willReturn($existingTranslation);

        $this->repository->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Translation already exists');

        ($this->handler)($command);
    }
}
