<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Infrastructure\Persistence\Doctrine\Repository;

use App\Internationalization\Domain\Model\Translation;
use App\Internationalization\Domain\Model\TranslationDomain;
use App\Internationalization\Domain\Model\TranslationId;
use App\Internationalization\Domain\Model\TranslationKey;
use App\Internationalization\Domain\Model\TranslationValue;
use App\Internationalization\Infrastructure\Persistence\Doctrine\Repository\DoctrineTranslationRepository;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('i18n')]
final class DoctrineTranslationRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private DoctrineTranslationRepository $repository;

    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new DoctrineTranslationRepository($this->connection);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    public function testSaveInsertsNewTranslation(): void
    {
        $translation = Translation::create(
            TranslationId::generate(),
            $this->tenantId,
            TranslationDomain::fromString('messages'),
            TranslationKey::fromString('greeting.hello'),
            LanguageCode::fromString('en'),
            TranslationValue::fromString('Hello'),
        );

        // No existing row
        $this->connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->connection
            ->expects(self::once())
            ->method('insert')
            ->with('ext_translations', self::callback(function (array $data) use ($translation) {
                return 'en' === $data['locale']
                    && 'messages' === $data['field']
                    && str_contains($data['foreign_key'], 'greeting.hello')
                    && 'Hello' === $data['content']
                    && $data['translation_uuid'] === $translation->id()->toString();
            }));

        $this->repository->save($translation);
    }

    public function testSaveUpdatesExistingTranslation(): void
    {
        $translation = Translation::create(
            TranslationId::generate(),
            $this->tenantId,
            TranslationDomain::fromString('messages'),
            TranslationKey::fromString('greeting.hello'),
            LanguageCode::fromString('en'),
            TranslationValue::fromString('Hello Updated'),
        );

        // Existing row found
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 42]);

        $this->connection
            ->expects(self::once())
            ->method('update')
            ->with(
                'ext_translations',
                self::callback(fn (array $data) => 'Hello Updated' === $data['content']),
                ['id' => 42],
            );

        $this->repository->save($translation);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->repository->findById(TranslationId::generate(), $this->tenantId);

        self::assertNull($result);
    }

    public function testFindByIdReturnsTranslationWhenFound(): void
    {
        $id = TranslationId::generate();

        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'translation_uuid' => $id->toString(),
                'locale' => 'de',
                'field' => 'messages',
                'foreign_key' => $this->tenantId->toString().':greeting.hello',
                'content' => 'Hallo',
                'parameters' => '{"name":"world"}',
            ]);

        $result = $this->repository->findById($id, $this->tenantId);

        self::assertNotNull($result);
        self::assertSame('de', $result->locale()->value());
        self::assertSame('Hallo', $result->value()->value());
        self::assertSame(['name' => 'world'], $result->parameters());
    }

    public function testFindByKeyReturnsNullWhenNotFound(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->repository->findByKey(
            $this->tenantId,
            LanguageCode::fromString('en'),
            TranslationDomain::fromString('messages'),
            TranslationKey::fromString('not.found'),
        );

        self::assertNull($result);
    }

    public function testFindByKeyReturnsTranslation(): void
    {
        $id = TranslationId::generate();

        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'translation_uuid' => $id->toString(),
                'locale' => 'fr',
                'field' => 'validators',
                'foreign_key' => $this->tenantId->toString().':error.required',
                'content' => 'Ce champ est requis',
                'parameters' => null,
            ]);

        $result = $this->repository->findByKey(
            $this->tenantId,
            LanguageCode::fromString('fr'),
            TranslationDomain::fromString('validators'),
            TranslationKey::fromString('error.required'),
        );

        self::assertNotNull($result);
        self::assertSame('fr', $result->locale()->value());
        self::assertSame('Ce champ est requis', $result->value()->value());
        self::assertSame([], $result->parameters());
    }

    public function testDeleteExecutesStatement(): void
    {
        $id = TranslationId::generate();

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('DELETE FROM ext_translations'),
                [$id->toString(), $this->tenantId->toString().':%'],
            );

        $this->repository->delete($id, $this->tenantId);
    }

    public function testHydrateWithoutUuidGeneratesNewId(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'translation_uuid' => null,
                'locale' => 'en',
                'field' => 'messages',
                'foreign_key' => $this->tenantId->toString().':test.key',
                'content' => 'Test value',
                'parameters' => null,
            ]);

        $result = $this->repository->findById(TranslationId::generate(), $this->tenantId);

        self::assertNotNull($result);
        // A new ID should have been generated
        self::assertNotEmpty($result->id()->toString());
    }

    public function testHydrateWithCreatedAtAndUpdatedAt(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn([
                'translation_uuid' => TranslationId::generate()->toString(),
                'locale' => 'en',
                'field' => 'messages',
                'foreign_key' => $this->tenantId->toString().':test.key',
                'content' => 'Test',
                'parameters' => '{}',
                'created_at' => '2026-01-15 10:30:00',
                'updated_at' => '2026-01-20 14:00:00',
            ]);

        $result = $this->repository->findById(TranslationId::generate(), $this->tenantId);

        self::assertNotNull($result);
    }
}
