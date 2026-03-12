<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Factory for creating DomPDF instances with SSRF-safe configuration.
 *
 * Remote resource fetching is disabled to prevent SSRF attacks.
 * All resources (logos, images) must be provided as local file paths.
 */
final class DompdfFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
    ) {
    }

    public function create(): Dompdf
    {
        $options = new Options();

        // Apply configuration
        $options->set('defaultPaperSize', $this->options['defaultPaperSize'] ?? 'A4');
        $options->set('defaultPaperOrientation', $this->options['defaultPaperOrientation'] ?? 'portrait');
        $options->set('defaultFont', $this->options['defaultFont'] ?? 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', $this->options['isFontSubsettingEnabled'] ?? true);
        $options->set('isHtml5ParserEnabled', $this->options['isHtml5ParserEnabled'] ?? true);

        // SECURITY: Disable remote resource fetching to prevent SSRF.
        // All resources (logos, images) must be local file paths.
        $options->set('isRemoteEnabled', false);

        // Belt-and-suspenders: restrict to file:// protocol only
        $options->setAllowedProtocols(['file://']);

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
