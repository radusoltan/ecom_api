<?php

declare(strict_types=1);

namespace App\Tests\Integration\Media\Application;

use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Presentation\Api\Resource\ImageResource;
use App\Media\Presentation\Api\State\ImageUploadProcessor;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Domain\ValueObject\TenantId as SharedTenantId;
use App\Shared\Infrastructure\Doctrine\TenantConnectionSubscriber;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\DBAL\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class ImageUploadProcessorTest extends KernelTestCase
{
    private Filesystem $filesystem;
    private TenantContext $tenantContext;
    private static bool $schemaPrepared = false;
    private ProductRepositoryInterface $productRepositoryStub;
    private CategoryRepositoryInterface $categoryRepositoryStub;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();
        $this->tenantContext = static::getContainer()->get(TenantContext::class);

        if (!self::$schemaPrepared) {
            $container = static::getContainer();
            $entityManager = $container->get('doctrine')->getManager();
            $schemaTool = new SchemaTool($entityManager);
            $metadata = [
                $entityManager->getClassMetadata(\App\Media\Infrastructure\Persistence\Doctrine\Entity\ImageEntity::class),
                $entityManager->getClassMetadata(\App\Media\Infrastructure\Persistence\Doctrine\Entity\ThumbnailEntity::class),
            ];

            $schemaTool->updateSchema($metadata, true);
            self::$schemaPrepared = true;
        }

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $eventManager = $entityManager->getEventManager();
        foreach ($eventManager->getListeners(Events::postConnect) as $listener) {
            if ($listener instanceof TenantConnectionSubscriber) {
                $eventManager->removeEventListener([Events::postConnect], $listener);
            }
        }

        $this->productRepositoryStub = $this->createProductRepositoryStub();
        $this->categoryRepositoryStub = $this->createCategoryRepositoryStub();
        static::getContainer()->set(ProductRepositoryInterface::class, $this->productRepositoryStub);
        static::getContainer()->set(CategoryRepositoryInterface::class, $this->categoryRepositoryStub);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $storageBase = (string) $container->getParameter('media.storage.local.base_path');
        $thumbnailBase = (string) $container->getParameter('media.thumbnail.local.base_path');

        if ('' !== $storageBase) {
            $this->filesystem->remove($storageBase);
        }

        if ('' !== $thumbnailBase) {
            $this->filesystem->remove($thumbnailBase);
        }

        $this->tenantContext->clearCurrentTenant();

        parent::tearDown();
    }

    public function testProcessPersistsImageAndGeneratesThumbnails(): void
    {
        $container = static::getContainer();
        /** @var ImageUploadProcessor $processor */
        $processor = $container->get(ImageUploadProcessor::class);
        /** @var ImageRepositoryInterface $imageRepository */
        $imageRepository = $container->get(ImageRepositoryInterface::class);

        $tenantId = SharedTenantId::generate()->toString();
        $ownerId = Uuid::v7()->toString();
        $imagePath = $this->createSampleImage();

        $resource = new ImageResource();
        $resource->tenantId = $tenantId;
        $resource->ownerType = 'product';
        $resource->ownerId = $ownerId;
        $resource->title = 'Hero image';
        $resource->altText = 'Gallery hero';
        $resource->file = new UploadedFile($imagePath, 'sample.png', 'image/png', null, true);

        $this->tenantContext->setCurrentTenant(TenantId::fromString($tenantId));

        $result = $processor->process($resource, new Post());

        self::assertNotNull($result->id);
        self::assertSame('Hero image', $result->title);
        self::assertNotNull($result->contentUrl);
        self::assertIsArray($result->thumbnails);
        self::assertCount(4, $result->thumbnails);

        $images = $imageRepository->findByTenant(SharedTenantId::fromString($tenantId));
        self::assertCount(1, $images);

        $image = $images[0];
        self::assertSame($tenantId, $image->tenantId()->toString());
        self::assertSame($ownerId, $image->owner()->ownerId());
        self::assertCount(4, $image->thumbnails());

        $this->assertFilesExist($image->originalPath()->toString(), $image->thumbnails());

        @unlink($imagePath);
        $this->tenantContext->clearCurrentTenant();
    }

    public function testProcessWithUnauthorizedTenantThrowsAccessDenied(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $container = static::getContainer();
        /** @var ImageUploadProcessor $processor */
        $processor = $container->get(ImageUploadProcessor::class);

        $tenantId = SharedTenantId::generate()->toString();
        $ownerId = Uuid::v7()->toString();
        $imagePath = $this->createSampleImage();

        $resource = new ImageResource();
        $resource->tenantId = $tenantId;
        $resource->ownerType = 'product';
        $resource->ownerId = $ownerId;
        $resource->title = 'Unauthorized upload';
        $resource->file = new UploadedFile($imagePath, 'sample.png', 'image/png', null, true);

        $this->tenantContext->setCurrentTenant(TenantId::generate());

        try {
            $processor->process($resource, new Post());
        } finally {
            @unlink($imagePath);
            $this->tenantContext->clearCurrentTenant();
        }
    }

    public function testProcessAttachesImageToProduct(): void
    {
        $container = static::getContainer();
        /** @var ImageUploadProcessor $processor */
        $processor = $container->get(ImageUploadProcessor::class);

        $tenantIdString = SharedTenantId::generate()->toString();
        $tenantShared = SharedTenantId::fromString($tenantIdString);

        $product = Product::create(
            ProductId::generate(),
            $tenantShared,
            SKU::fromString('ABC-DEF-000001'),
            ProductName::fromString('Attachment Product'),
            'Description',
            'Short description',
            Money::fromScalars(1999, 'USD'),
            null,
            Stock::create(25)
        );

        $this->productRepositoryStub->save($product);

        $imagePath = $this->createSampleImage();

        $resource = new ImageResource();
        $resource->tenantId = $tenantIdString;
        $resource->ownerType = 'product';
        $resource->ownerId = $product->id()->toString();
        $resource->title = 'Product hero';
        $resource->altText = 'Primary gallery image';
        $resource->file = new UploadedFile($imagePath, 'sample.png', 'image/png', null, true);

        $this->tenantContext->setCurrentTenant(TenantId::fromString($tenantIdString));

        $result = $processor->process($resource, new Post());

        $updatedProduct = $this->productRepositoryStub->findById($product->id());
        self::assertNotNull($updatedProduct);
        self::assertCount(1, $updatedProduct->images());
        self::assertSame($result->contentUrl, $updatedProduct->images()[0]->url());

        @unlink($imagePath);
        $this->tenantContext->clearCurrentTenant();
    }

    public function testProcessAssignsCoverImageToCategory(): void
    {
        $container = static::getContainer();
        /** @var ImageUploadProcessor $processor */
        $processor = $container->get(ImageUploadProcessor::class);
        $tenantIdString = SharedTenantId::generate()->toString();
        $tenantShared = SharedTenantId::fromString($tenantIdString);

        $category = Category::create(
            CategoryId::generate(),
            $tenantShared,
            CategoryName::fromString('Cover Category'),
            'Needs cover',
            null,
            0
        );

        $this->categoryRepositoryStub->save($category);

        $imagePath = $this->createSampleImage();

        $resource = new ImageResource();
        $resource->tenantId = $tenantIdString;
        $resource->ownerType = 'category';
        $resource->ownerId = $category->id()->toString();
        $resource->title = 'Category hero';
        $resource->file = new UploadedFile($imagePath, 'sample.png', 'image/png', null, true);

        $this->tenantContext->setCurrentTenant(TenantId::fromString($tenantIdString));

        $result = $processor->process($resource, new Post());

        self::assertSame($result->contentUrl, $category->coverImage());

        $updatedCategory = $this->categoryRepositoryStub->findById($category->id());
        self::assertNotNull($updatedCategory);
        self::assertNotNull($updatedCategory->coverImage());
        self::assertSame($updatedCategory->coverImage(), $result->contentUrl);

        @unlink($imagePath);
        $this->tenantContext->clearCurrentTenant();
    }

    private function createSampleImage(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'media_test_');
        $image = imagecreatetruecolor(800, 600);
        $background = imagecolorallocate($image, 200, 100, 50);
        imagefilledrectangle($image, 0, 0, 800, 600, $background);
        imagepng($image, $tempFile);
        imagedestroy($image);

        return $tempFile;
    }

    private function assertFilesExist(string $publicOriginalPath, array $thumbnails): void
    {
        $container = static::getContainer();
        $originalBase = (string) $container->getParameter('media.storage.local.base_path');
        $originalPrefix = (string) $container->getParameter('media.storage.local.public_prefix');
        $thumbnailBase = (string) $container->getParameter('media.thumbnail.local.base_path');
        $thumbnailPrefix = (string) $container->getParameter('media.thumbnail.local.public_prefix');

        $originalRelative = str_starts_with($publicOriginalPath, $originalPrefix)
            ? ltrim(substr($publicOriginalPath, strlen($originalPrefix)), '/')
            : ltrim($publicOriginalPath, '/');
        $originalAbsolute = sprintf('%s/%s', rtrim($originalBase, '/'), $originalRelative);
        self::assertFileExists($originalAbsolute);

        foreach ($thumbnails as $thumbnail) {
            $path = $thumbnail->path()->toString();
            $relative = str_starts_with($path, $thumbnailPrefix)
                ? ltrim(substr($path, strlen($thumbnailPrefix)), '/')
                : ltrim($path, '/');
            $absolute = sprintf('%s/%s', rtrim($thumbnailBase, '/'), $relative);
            self::assertFileExists($absolute);

            $dimensions = getimagesize($absolute);
            self::assertIsArray($dimensions);
            self::assertSame($thumbnail->width(), $dimensions[0]);
            self::assertSame($thumbnail->height(), $dimensions[1]);
        }
    }

    private function createProductRepositoryStub(): ProductRepositoryInterface
    {
        return new class implements ProductRepositoryInterface {
            /** @var array<string, Product> */
            private array $products = [];

            public function save(Product $product): void
            {
                $this->products[$product->id()->toString()] = $product;
            }

            public function findById(ProductId $id): ?Product
            {
                return $this->products[$id->toString()] ?? null;
            }

            public function findBySKU(TenantId $tenantId, SKU $sku): ?Product
            {
                foreach ($this->products as $product) {
                    if ($product->tenantId()->equals($tenantId) && $product->sku()->equals($sku)) {
                        return $product;
                    }
                }

                return null;
            }

            public function findBySlug(TenantId $tenantId, \App\Catalog\Domain\Model\Slug $slug): ?Product
            {
                foreach ($this->products as $product) {
                    if ($product->tenantId()->equals($tenantId) && $product->slug()->equals($slug)) {
                        return $product;
                    }
                }

                return null;
            }

            public function findByTenant(TenantId $tenantId, int $limit = 100, int $offset = 0): array
            {
                $filtered = array_filter(
                    $this->products,
                    static fn (Product $product): bool => $product->tenantId()->equals($tenantId)
                );

                return array_slice(array_values($filtered), $offset, $limit);
            }

            public function delete(ProductId $id): void
            {
                unset($this->products[$id->toString()]);
            }
        };
    }

    private function createCategoryRepositoryStub(): CategoryRepositoryInterface
    {
        return new class implements CategoryRepositoryInterface {
            /** @var array<string, Category> */
            private array $categories = [];

            public function save(Category $category): void
            {
                $this->categories[$category->id()->toString()] = $category;
            }

            public function findById(CategoryId $id): ?Category
            {
                return $this->categories[$id->toString()] ?? null;
            }

            public function findBySlug(TenantId $tenantId, \App\Catalog\Domain\Model\Slug $slug): ?Category
            {
                foreach ($this->categories as $category) {
                    if ($category->tenantId()->equals($tenantId) && $category->slug()->equals($slug)) {
                        return $category;
                    }
                }

                return null;
            }

            public function findByTenant(TenantId $tenantId): array
            {
                return array_values(array_filter(
                    $this->categories,
                    static fn (Category $category): bool => $category->tenantId()->equals($tenantId)
                ));
            }

            public function findByParent(TenantId $tenantId, ?CategoryId $parentId): array
            {
                return array_values(array_filter(
                    $this->categories,
                    static fn (Category $category): bool => $category->tenantId()->equals($tenantId)
                        && ((null === $parentId && null === $category->parentId())
                            || (null !== $parentId && $category->parentId()?->equals($parentId)))
                ));
            }

            public function delete(CategoryId $id): void
            {
                unset($this->categories[$id->toString()]);
            }
        };
    }
}
