<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Validator;

use App\Media\Domain\ValueObject\CropArea;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CropAreaValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCropArea) {
            throw new UnexpectedTypeException($constraint, ValidCropArea::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // If it's already a CropArea object, validate it
        if ($value instanceof CropArea) {
            $this->validateCropArea($value, $constraint);

            return;
        }

        // If it's an array, validate the structure
        if (is_array($value)) {
            $this->validateCropArray($value, $constraint);

            return;
        }

        // If it's JSON string, decode and validate
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $this->context->buildViolation($constraint->invalidJsonMessage)
                    ->addViolation();

                return;
            }
            $this->validateCropArray($decoded, $constraint);

            return;
        }

        $this->context->buildViolation($constraint->message)
            ->addViolation();
    }

    private function validateCropArea(CropArea $cropArea, ValidCropArea $constraint): void
    {
        // Check if dimensions are positive
        if ($cropArea->width() <= 0 || $cropArea->height() <= 0) {
            $this->context->buildViolation($constraint->invalidDimensionsMessage)
                ->setParameter('{{ width }}', (string) $cropArea->width())
                ->setParameter('{{ height }}', (string) $cropArea->height())
                ->addViolation();

            return;
        }

        // Check if crop area doesn't exceed image bounds (if max dimensions are provided)
        if (null !== $constraint->maxWidth && null !== $constraint->maxHeight) {
            if ($cropArea->x() + $cropArea->width() > $constraint->maxWidth
                || $cropArea->y() + $cropArea->height() > $constraint->maxHeight) {
                $this->context->buildViolation($constraint->outOfBoundsMessage)
                    ->setParameter('{{ maxWidth }}', (string) $constraint->maxWidth)
                    ->setParameter('{{ maxHeight }}', (string) $constraint->maxHeight)
                    ->addViolation();
            }
        }

        // Check aspect ratio if specified
        if (null !== $constraint->aspectRatio) {
            $actualRatio = $cropArea->width() / $cropArea->height();
            $tolerance = $constraint->aspectRatioTolerance;

            if (abs($actualRatio - $constraint->aspectRatio) > $tolerance) {
                $this->context->buildViolation($constraint->invalidAspectRatioMessage)
                    ->setParameter('{{ expected }}', (string) $constraint->aspectRatio)
                    ->setParameter('{{ actual }}', (string) round($actualRatio, 2))
                    ->addViolation();
            }
        }
    }

    private function validateCropArray(array $value, ValidCropArea $constraint): void
    {
        // Check required fields
        if (!isset($value['x'], $value['y'], $value['width'], $value['height'])) {
            $this->context->buildViolation($constraint->missingFieldsMessage)
                ->addViolation();

            return;
        }

        // Validate numeric values
        if (!is_numeric($value['x']) || !is_numeric($value['y'])
            || !is_numeric($value['width']) || !is_numeric($value['height'])) {
            $this->context->buildViolation($constraint->nonNumericMessage)
                ->addViolation();

            return;
        }

        try {
            $cropArea = CropArea::fromDimensions(
                (int) $value['x'],
                (int) $value['y'],
                (int) $value['width'],
                (int) $value['height']
            );
            $this->validateCropArea($cropArea, $constraint);
        } catch (\InvalidArgumentException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ reason }}', $e->getMessage())
                ->addViolation();
        }
    }
}
