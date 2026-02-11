<?php

declare(strict_types=1);

namespace App\Tests\Functional\Media\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploadApiTest extends ApiTestCase
{
    private const TEST_TENANT_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const TEST_PRODUCT_ID = '660e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();
        // Set ASYNC_THUMBNAILS to false for testing to ensure synchronous processing
        $_ENV['ASYNC_THUMBNAILS'] = 'false';
    }

    public function testUploadImageForProduct(): void
    {
        // Create a test image file
        $testImagePath = $this->createTestImage();
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'test-product.jpg',
            'image/jpeg',
            null,
            true
        );

        $client = static::createClient();
        $client->request('POST', '/api/v1/media_images', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'X-Tenant-ID' => self::TEST_TENANT_ID,
            ],
            'extra' => [
                'parameters' => [
                    'tenantId' => self::TEST_TENANT_ID,
                    'ownerType' => 'product',
                    'ownerId' => self::TEST_PRODUCT_ID,
                    'title' => 'Product Main Image',
                    'altText' => 'A beautiful product image',
                ],
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('contentUrl', $response);
        $this->assertArrayHasKey('thumbnails', $response);
        $this->assertEquals(self::TEST_TENANT_ID, $response['tenantId']);
        $this->assertEquals('product', $response['ownerType']);
        $this->assertEquals(self::TEST_PRODUCT_ID, $response['ownerId']);
        $this->assertEquals('Product Main Image', $response['title']);
        $this->assertEquals('A beautiful product image', $response['altText']);

        // Clean up
        @unlink($testImagePath);
    }

    public function testUploadImageValidation(): void
    {
        $client = static::createClient();

        // Test with missing file
        $client->request('POST', '/api/v1/media_images', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'X-Tenant-ID' => self::TEST_TENANT_ID,
            ],
            'extra' => [
                'parameters' => [
                    'tenantId' => self::TEST_TENANT_ID,
                    'ownerType' => 'product',
                    'ownerId' => self::TEST_PRODUCT_ID,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteImage(): void
    {
        // First upload an image
        $testImagePath = $this->createTestImage();
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'test-delete.jpg',
            'image/jpeg',
            null,
            true
        );

        $client = static::createClient();
        $client->request('POST', '/api/v1/media_images', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'X-Tenant-ID' => self::TEST_TENANT_ID,
            ],
            'extra' => [
                'parameters' => [
                    'tenantId' => self::TEST_TENANT_ID,
                    'ownerType' => 'product',
                    'ownerId' => self::TEST_PRODUCT_ID,
                ],
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $imageId = $response['id'];

        // Now delete the image
        $client->request('DELETE', '/api/v1/media_images/'.$imageId, [
            'headers' => [
                'X-Tenant-ID' => self::TEST_TENANT_ID,
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Clean up
        @unlink($testImagePath);
    }

    public function testRegenerateThumbnails(): void
    {
        // First upload an image
        $testImagePath = $this->createTestImage();
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'test-regenerate.jpg',
            'image/jpeg',
            null,
            true
        );

        $client = static::createClient();
        $client->request('POST', '/api/v1/media_images', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
                'X-Tenant-ID' => self::TEST_TENANT_ID,
            ],
            'extra' => [
                'parameters' => [
                    'tenantId' => self::TEST_TENANT_ID,
                    'ownerType' => 'category',
                    'ownerId' => '770e8400-e29b-41d4-a716-446655440000',
                ],
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $imageId = $response['id'];

        // Now regenerate thumbnails with crop
        $client->request('PATCH', '/api/v1/media_images/'.$imageId.'/regenerate-thumbnails', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-ID' => self::TEST_TENANT_ID,
            ],
            'json' => [
                'cropJson' => [
                    'x' => 10,
                    'y' => 10,
                    'width' => 100,
                    'height' => 100,
                ],
                'sizes' => ['md', 'lg'],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('thumbnails', $response);
        $this->assertNotEmpty($response['thumbnails']);

        // Clean up
        @unlink($testImagePath);
    }

    private function createTestImage(): string
    {
        $width = 800;
        $height = 600;

        $image = imagecreatetruecolor($width, $height);
        $backgroundColor = imagecolorallocate($image, 100, 100, 100);
        $textColor = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $width, $height, $backgroundColor);
        imagestring($image, 5, $width / 2 - 50, $height / 2 - 10, 'TEST IMAGE', $textColor);

        $tempPath = sys_get_temp_dir().'/'.uniqid('test_image_').'.jpg';
        imagejpeg($image, $tempPath, 90);
        imagedestroy($image);

        return $tempPath;
    }
}
