<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

final class DompdfFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options
    ) {}

    public function create(): Dompdf
    {
        $options = new Options();

        // Apply configuration
        $options->set('defaultPaperSize', $this->options['defaultPaperSize'] ?? 'A4');
        $options->set('defaultPaperOrientation', $this->options['defaultPaperOrientation'] ?? 'portrait');
        $options->set('defaultFont', $this->options['defaultFont'] ?? 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', $this->options['isFontSubsettingEnabled'] ?? true);
        $options->set('isRemoteEnabled', $this->options['isRemoteEnabled'] ?? true);
        $options->set('isHtml5ParserEnabled', $this->options['isHtml5ParserEnabled'] ?? true);

        if (isset($this->options['fontDir'])) {
            $options->set('fontDir', $this->options['fontDir']);
        }
        if (isset($this->options['fontCache'])) {
            $options->set('fontCache', $this->options['fontCache']);
        }
        if (isset($this->options['tempDir'])) {
            $options->set('tempDir', $this->options['tempDir']);
        }

        return new Dompdf($options);
    }
}
