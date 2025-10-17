<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ValidCropArea extends Constraint
{
    public string $message = 'The crop area is not valid: {{ reason }}.';
    public string $invalidJsonMessage = 'The crop area must be a valid JSON object.';
    public string $missingFieldsMessage = 'The crop area must contain x, y, width, and height fields.';
    public string $nonNumericMessage = 'All crop area values must be numeric.';
    public string $invalidDimensionsMessage = 'Crop dimensions must be positive (width: {{ width }}, height: {{ height }}).';
    public string $outOfBoundsMessage = 'Crop area exceeds image bounds (max: {{ maxWidth }}x{{ maxHeight }}).';
    public string $invalidAspectRatioMessage = 'Invalid aspect ratio. Expected: {{ expected }}, got: {{ actual }}.';

    public ?int $maxWidth = null;
    public ?int $maxHeight = null;
    public ?float $aspectRatio = null;
    public float $aspectRatioTolerance = 0.1;

    public function __construct(
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?float $aspectRatio = null,
        float $aspectRatioTolerance = 0.1,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
        $this->aspectRatio = $aspectRatio;
        $this->aspectRatioTolerance = $aspectRatioTolerance;

        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function validatedBy(): string
    {
        return CropAreaValidator::class;
    }
}