<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\Transformer;

use App\Media\Domain\Model\Image;
use App\Media\Presentation\Api\Resource\ImageResource;

final class ImageResourceTransformer
{
    public function transform(Image $image): ImageResource
    {
        $resource = new ImageResource();
        $resource->id = $image->id()->toString();
        $resource->tenantId = $image->tenantId()->toString();
        $resource->ownerType = $image->owner()->type()->value;
        $resource->ownerId = $image->owner()->ownerId();
        $resource->title = $image->title();
        $resource->altText = $image->altText();
        $resource->contentUrl = $image->originalPath()->toString();
        $thumbnails = [];
        foreach ($image->thumbnails() as $thumbnail) {
            $thumbnails[$thumbnail->sizeLabel()->value] = [
                'path' => $thumbnail->path()->toString(),
                'width' => $thumbnail->width(),
                'height' => $thumbnail->height(),
                'cropArea' => $thumbnail->cropArea()->toArray(),
            ];
        }
        $resource->thumbnails = $thumbnails;

        return $resource;
    }
}
