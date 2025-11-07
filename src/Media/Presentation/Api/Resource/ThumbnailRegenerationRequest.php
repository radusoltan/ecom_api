<?php

declare(strict_types=1);

namespace App\Media\Presentation\Api\Resource;

use App\Media\Infrastructure\Validator\ValidCropArea;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ThumbnailRegenerationRequest
{
    /**
     * @var array|string|null Crop area in JSON format or array
     *                        Example: {"x": 10, "y": 20, "width": 200, "height": 300}
     */
    #[Groups(['thumbnail:write'])]
    #[ValidCropArea]
    public $cropJson;

    /**
     * @var string[]|null Array of size labels to regenerate
     *                    If null, all sizes will be regenerated
     *                    Example: ["sm", "md", "lg", "xl"]
     */
    #[Groups(['thumbnail:write'])]
    #[Assert\All([
        new Assert\Choice(choices: ['sm', 'md', 'lg', 'xl']),
    ])]
    public ?array $sizes = null;
}
