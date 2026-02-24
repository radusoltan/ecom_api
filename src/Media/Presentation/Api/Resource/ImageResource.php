<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model as OpenApiModel;
use App\Media\Presentation\Api\State\ImageCollectionProvider;
use App\Media\Presentation\Api\State\ImageDeleteProcessor;
use App\Media\Presentation\Api\State\ImageItemProvider;
use App\Media\Presentation\Api\State\ImageUploadProcessor;
use App\Media\Presentation\Api\State\RegenerateThumbnailsProcessor;
use App\Media\Presentation\Api\State\UpdateImageMetadataProcessor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MediaImage',
    operations: [
        new Get(
            provider: ImageItemProvider::class,
            normalizationContext: ['groups' => ['media:image:read']]
        ),
        new GetCollection(
            provider: ImageCollectionProvider::class,
            normalizationContext: ['groups' => ['media:image:read']]
        ),
        new Post(
            processor: ImageUploadProcessor::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
            denormalizationContext: ['groups' => ['media:image:write']],
            openapi: new OpenApiModel\Operation(
                summary: 'Upload a media image',
                requestBody: new OpenApiModel\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['tenantId', 'ownerType', 'ownerId', 'file'],
                                'properties' => [
                                    'tenantId' => [
                                        'type' => 'string',
                                    ],
                                    'ownerType' => [
                                        'type' => 'string',
                                        'enum' => ['product', 'category', 'user'],
                                    ],
                                    'ownerId' => [
                                        'type' => 'string',
                                    ],
                                    'title' => [
                                        'type' => 'string',
                                    ],
                                    'altText' => [
                                        'type' => 'string',
                                    ],
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            )
        ),
        new Patch(
            uriTemplate: '/media_images/{id}/regenerate-thumbnails',
            processor: RegenerateThumbnailsProcessor::class,
            input: ThumbnailRegenerationRequest::class,
            read: false,
            inputFormats: ['json' => ['application/merge-patch+json', 'application/json']],
            outputFormats: ['jsonld' => ['application/ld+json'], 'json' => ['application/json']],
            denormalizationContext: ['groups' => ['thumbnail:write']],
            normalizationContext: ['groups' => ['media:image:read']],
            openapi: new OpenApiModel\Operation(
                summary: 'Regenerate thumbnails with optional crop area',
                description: 'Regenerates thumbnails for an image with optional crop area from react-image-crop',
                requestBody: new OpenApiModel\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'cropJson' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'x' => ['type' => 'number'],
                                            'y' => ['type' => 'number'],
                                            'width' => ['type' => 'number'],
                                            'height' => ['type' => 'number'],
                                        ],
                                        'example' => ['x' => 10, 'y' => 20, 'width' => 200, 'height' => 300],
                                    ],
                                    'sizes' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string', 'enum' => ['sm', 'md', 'lg', 'xl']],
                                        'example' => ['md', 'lg'],
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            )
        ),
        new Patch(
            processor: UpdateImageMetadataProcessor::class,
            denormalizationContext: ['groups' => ['media:image:write']],
            normalizationContext: ['groups' => ['media:image:read']],
            openapi: new OpenApiModel\Operation(
                summary: 'Update image metadata (title, alt text)',
            )
        ),
        new Delete(
            processor: ImageDeleteProcessor::class
        ),
    ]
)]
class ImageResource
{
    #[Groups(['media:image:read'])]
    public ?string $id = null;

    #[Groups(['media:image:read', 'media:image:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 36)]
    public ?string $tenantId = null;

    #[Groups(['media:image:read', 'media:image:write'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['product', 'category', 'user'])]
    public ?string $ownerType = null;

    #[Groups(['media:image:read', 'media:image:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 36)]
    public ?string $ownerId = null;

    #[Groups(['media:image:write'])]
    #[Assert\NotNull(groups: ['media:image:write'])]
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif']
    )]
    public ?UploadedFile $file = null;

    #[Groups(['media:image:read', 'media:image:write'])]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Groups(['media:image:read', 'media:image:write'])]
    #[Assert\Length(max: 255)]
    public ?string $altText = null;

    #[Groups(['media:image:read'])]
    public ?string $contentUrl = null;

    #[Groups(['media:image:read'])]
    public ?array $thumbnails = null;
}
