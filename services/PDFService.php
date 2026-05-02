<?php
/**
 * Centralized PDF generation service.
 * Requires mpdf via Composer: composer require mpdf/mpdf
 */
class PDFService {
    /**
     * Reusable system-level PDF generator.
     *
     * @param array $data {
     *   @type string title
     *   @type string html
     *   @type string filename
     *   @type string css
     *   @type string mode     I|D|S
     * }
     */
    public function generatePDF(array $data): string {
        if (!class_exists('\Mpdf\Mpdf')) {
            throw new RuntimeException('mPDF is not installed. Run: composer require mpdf/mpdf');
        }

        $title = $data['title'] ?? 'ABED Report';
        $html = $data['html'] ?? '<p>No content.</p>';
        $css = $data['css'] ?? '';
        $mode = $data['mode'] ?? 'S';

        $tmpDir = __DIR__ . '/../tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdfClass = '\Mpdf\Mpdf';
        $mpdf = new $mpdfClass([
            'format' => 'A4',
            'tempDir' => $tmpDir,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle($title);
        $headerCssMode = defined('\Mpdf\HTMLParserMode::HEADER_CSS') ? constant('\Mpdf\HTMLParserMode::HEADER_CSS') : 1;
        $htmlBodyMode = defined('\Mpdf\HTMLParserMode::HTML_BODY') ? constant('\Mpdf\HTMLParserMode::HTML_BODY') : 2;
        if ($css !== '') {
            $mpdf->WriteHTML($css, $headerCssMode);
        }
        $mpdf->WriteHTML($html, $htmlBodyMode);

        $filename = $data['filename'] ?? ('report-' . date('Ymd-His') . '.pdf');
        return $mpdf->Output($filename, $mode);
    }
}

function generatePDF(array $data): string {
    $service = new PDFService();
    return $service->generatePDF($data);
}
