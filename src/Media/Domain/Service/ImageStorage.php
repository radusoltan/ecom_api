<?php

declare(strict_types=1);

namespace App\Media\Domain\Service;

use App\Media\Domain\ValueObject\FilePath;
use App\Media\Domain\ValueObject\ImageId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageStorage
{
    public function storeOriginal(UploadedFile $file, TenantId $tenantId, ImageId $imageId): FilePath;

    public function remove(FilePath $path): void;
}
