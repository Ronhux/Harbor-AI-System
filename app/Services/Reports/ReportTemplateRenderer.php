<?php

namespace App\Services\Reports;

/**
 * Renders the downloadable HarborAI administrative report document (PDF)
 * following the official template at docs/report-templates/HarborAI_Report.docx:
 *
 * - A4 portrait with the template's margins (top 14mm, sides 17mm; bottom
 *   slightly enlarged to house the running footer without clipping).
 * - Institutional masthead: "Republic of the Philippines / Province of
 *   Cagayan / Municipality of Aparri", HarborAI brand, tagline, and the
 *   "ADMINISTRATIVE REPORT" label, repeated as a compact running header.
 * - Report title, subtitle (reporting period), and the template metadata
 *   block (Report Reference, Reporting Period, Date Generated, Prepared By,
 *   Report Category, Data As Of, Intended Audience, Status).
 * - Sections 1-10 exactly as in the template, including the three-column
 *   Key Highlights table and the PREPARED BY / REVIEWED BY / APPROVED BY
 *   signature blocks.
 * - "HARBORAI | ADMINISTRATIVE REPORT" footer and the document control note.
 *
 * All values are taken from the live report snapshot (real HarborAI
 * database records and the selected filters); no placeholders are emitted.
 */
class ReportTemplateRenderer
{
    /**
     * The section headings defined by the supplied template at
     * docs/report-templates/HarborAI_Report.docx, in template order.
     *
     * Exposed so the download tests can assert that every generated format
     * (PDF, XLSX, CSV) really carries the template's structure rather than an
     * approximation of it.
     *
     * @var list<string>
     */
    public const SECTION_HEADINGS = [
        '1. Executive Summary',
        '2. Purpose and Scope',
        '3. Key Highlights',
        '4. Findings and Observations',
        '5. Detailed Records',
        '6. Analysis and Interpretation',
        '7. Issues, Limitations, and Data Notes',
        '8. Conclusion',
        '9. Recommendations / Next Steps',
        '10. Review and Approval',
    ];

    /**
     * The template's metadata field labels, in template order.
     *
     * @var list<string>
     */
    public const METADATA_LABELS = [
        'Report Reference',
        'Reporting Period',
        'Date Generated',
        'Prepared By',
        'Report Category',
        'Data As Of',
        'Intended Audience',
        'Status',
    ];

    /**
     * The template's three-column Key Highlights header row.
     *
     * @var list<string>
     */
    public const HIGHLIGHT_HEADINGS = ['TOTAL RECORDS', 'KEY METRIC', 'CHANGE / STATUS'];

    /**
     * The template's signature block headings.
     *
     * @var list<string>
     */
    public const APPROVAL_HEADINGS = ['PREPARED BY', 'REVIEWED BY', 'APPROVED BY'];

    public function render(array $report): string
    {
        $presentation = $report['presentation'] ?? app(ReportPresentationService::class)->decorate($report)['presentation'];
        $escape = fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $format = app(ReportPresentationService::class);
        $logoPath = public_path('logo.jpg');
        $logo = is_file($logoPath) ? 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($logoPath)) : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><style>'
            . $this->styles()
            . '</style></head><body>';

        // Running header on every page (template header content, compacted).
        $html .= '<div class="running-header">Republic of the Philippines · Province of Cagayan · Municipality of Aparri<br>'
            . '<strong>HARBORAI</strong> | ADMINISTRATIVE REPORT</div>';
        // Footer on every page (template footer text).
        $html .= '<div class="footer">HARBORAI | ADMINISTRATIVE REPORT</div>';

        $html .= '<header class="masthead">';
        if ($logo !== '') {
            $html .= '<img src="' . $logo . '" alt="HarborAI">';
        }
        $html .= '<div class="place">Republic of the Philippines</div>'
            . '<div class="place">Province of Cagayan</div>'
            . '<div class="place">Municipality of Aparri</div>'
            . '<div class="brand">HARBORAI</div>'
            . '<div class="tagline">SMART TRADING AND ENTERPRISE PLATFORM FOR AGRICULTURE IN APARRI</div>'
            . '<div class="report-label">ADMINISTRATIVE REPORT</div>'
            . '</header>';

        $html .= '<h1>' . $escape($report['title'] ?? 'HarborAI Report') . '</h1><p class="period">'
            . $escape($report['period'] ?? '') . '</p>';

        $html .= '<table class="metadata"><tbody>';
        foreach (array_chunk($presentation['metadata'], 2) as $pair) {
            $html .= '<tr>';
            foreach ($pair as $item) {
                $html .= '<td>' . $escape($item['label']) . '</td><td>' . $escape($item['value']) . '</td>';
            }
            if (count($pair) === 1) {
                $html .= '<td></td><td></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $html .= '<section class="section"><h2>1. Executive Summary</h2><p>' . $escape($presentation['executive_summary']) . '</p></section>';
        $html .= '<section class="section"><h2>2. Purpose and Scope</h2><p>' . $escape($presentation['purpose_scope']) . '</p></section>';

        $html .= '<section class="section"><h2>3. Key Highlights</h2>' . $this->highlightSummaryTable($presentation, $escape);
        if ($presentation['highlights'] === []) {
            $html .= '<p>No summary metrics were calculated.</p>';
        } else {
            $html .= '<table class="highlight"><thead><tr><th>Measured Metric</th><th>Value</th></tr></thead><tbody>';
            foreach ($presentation['highlights'] as $metric) {
                $html .= '<tr><td>' . $escape($metric['label']) . '</td><td class="metric-value">' . $escape($metric['display_value']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        foreach ($presentation['summary_tables'] as $table) {
            $html .= '<h3>' . $escape($table['title']) . '</h3>';
            $html .= $this->htmlDataTable($table['columns'], $table['rows'], $escape, $format);
        }
        $html .= '</section>';

        $html .= '<section class="section"><h2>4. Findings and Observations</h2><ul>';
        foreach ($presentation['findings'] as $finding) {
            $html .= '<li>' . $escape($finding) . '</li>';
        }
        $html .= '</ul></section>';

        $html .= '<section class="section records"><h2>5. Detailed Records</h2>';
        $html .= '<p>The table below lists the records returned by the selected report query and filters. Total records: '
            . number_format((int) ($report['row_count'] ?? 0)) . '.</p>';
        if (($report['rows'] ?? []) === []) {
            $html .= '<p class="empty">No records matched this report and filter set.</p>';
        } else {
            $html .= $this->htmlDataTable($report['columns'] ?? [], $report['rows'], $escape, $format);
        }
        $html .= '</section>';

        $html .= '<section class="section"><h2>6. Analysis and Interpretation</h2><ul>';
        foreach ($presentation['analysis'] as $item) {
            $html .= '<li>' . $escape($item) . '</li>';
        }
        $html .= '</ul></section>';
        $html .= '<section class="section"><h2>7. Issues, Limitations, and Data Notes</h2><ul>';
        foreach ($presentation['limitations'] as $item) {
            $html .= '<li>' . $escape($item) . '</li>';
        }
        $html .= '</ul></section>';
        $html .= '<section class="section"><h2>8. Conclusion</h2><p>' . $escape($presentation['conclusion']) . '</p></section>';
        $html .= '<section class="section"><h2>9. Recommendations / Next Steps</h2><p>' . $escape($presentation['recommendations']) . '</p></section>';
        $html .= '<section class="section"><h2>10. Review and Approval</h2>' . $this->approvalTable($presentation, $escape) . '</section>';
        $html .= '<div class="control"><strong>DOCUMENT CONTROL NOTE.</strong> This HarborAI administrative report follows the official HarborAI report template. It is not an official Municipality of Aparri form or letterhead. Use official seals, logos, and signatories only when authorized. All figures are generated from HarborAI system records as of the Data As Of timestamp and should be verified before distribution.</div>';

        return $html . '</body></html>';
    }

    /**
     * Template section 3 opening table: TOTAL RECORDS / KEY METRIC /
     * CHANGE / STATUS, populated from the real snapshot values.
     */
    private function highlightSummaryTable(array $presentation, callable $escape): string
    {
        $summary = $presentation['highlight_summary'] ?? null;
        if (!is_array($summary)) {
            return '';
        }

        $html = '<table class="highlight-summary"><thead><tr>'
            . '<th>TOTAL RECORDS</th><th>KEY METRIC</th><th>CHANGE / STATUS</th>'
            . '</tr></thead><tbody><tr>'
            . '<td>' . $escape($summary['total_records'] ?? 'Not available') . '</td>'
            . '<td>' . $escape($summary['key_metric'] ?? 'Not available') . '</td>'
            . '<td>' . $escape($summary['change_status'] ?? 'Not available') . '</td>'
            . '</tr></tbody></table>';

        return $html;
    }

    /**
     * Template section 10 signature blocks.
     */
    private function approvalTable(array $presentation, callable $escape): string
    {
        $approval = $presentation['approval'] ?? [];
        $preparedBy = $escape($approval['prepared_by'] ?? 'Not available');
        $preparedRole = $escape($approval['prepared_by_role'] ?? 'Admin');
        $signatureCell = '<span class="signature">____________________________</span>'
            . '<span class="signature-name">Name / Position</span>'
            . '<span class="signature-date">Date: ______________________</span>';

        return '<table class="approval"><thead><tr>'
            . '<th>PREPARED BY</th><th>REVIEWED BY</th><th>APPROVED BY</th>'
            . '</tr></thead><tbody><tr>'
            . '<td>' . $preparedBy . '<br>' . $preparedRole . $signatureCell . '</td>'
            . '<td>' . $signatureCell . '</td>'
            . '<td>' . $signatureCell . '</td>'
            . '</tr></tbody></table>';
    }

    private function htmlDataTable(array $columns, array $rows, callable $escape, ReportPresentationService $presentation): string
    {
        $html = '<table><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>' . $escape($column['label'] ?? $column['key'] ?? '') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $key = (string) ($column['key'] ?? '');
                $value = $row[$key] ?? null;
                $text = is_numeric($value)
                    ? $presentation->formatValue($value, $key)
                    : ($value === null || $value === ''
                        ? 'Not available'
                        : (is_scalar($value) ? (string) $value : 'See structured report table'));
                $html .= '<td>' . $escape($text) . '</td>';
            }
            $html .= '</tr>';
        }
        if ($rows === []) {
            $html .= '<tr><td colspan="' . max(1, count($columns)) . '" class="empty">No records found.</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * Template-derived page styles: A4 portrait, template margins, serif
     * body font (Cambria with serif fallback), repeating table headers,
     * page-break control, running header, and footer.
     */
    private function styles(): string
    {
        return
            '@page { margin: 14mm 17mm 16mm; } '
            . 'body { font-family: Cambria, "DejaVu Serif", Georgia, serif; color: #123C5C; font-size: 10.5pt; line-height: 1.5; } '
            . '.running-header { position: fixed; top: -9mm; left: 0; right: 0; text-align: center; color: #60717D; font-size: 7pt; line-height: 1.25; } '
            . '.footer { position: fixed; bottom: -11mm; left: 0; right: 0; text-align: center; color: #60717D; font-size: 7.5pt; border-top: 1px solid #D8D3C4; padding-top: 3px; } '
            . '.masthead { text-align: center; border-bottom: 2px solid #0F9488; padding-bottom: 8px; margin-bottom: 12px; } '
            . '.masthead img { width: 60px; height: 60px; } .place { color: #45586B; font-size: 8.5pt; line-height: 1.3; } '
            . '.brand { font-size: 19pt; font-weight: bold; letter-spacing: 2px; margin: 4px 0 0; } '
            . '.tagline { color: #0F766E; font-size: 8pt; font-weight: bold; } '
            . '.report-label { color: #45586B; font-size: 8pt; font-weight: bold; letter-spacing: 1px; margin-top: 5px; } '
            . 'h1 { font-size: 17pt; text-align: center; margin: 10px 0 3px; page-break-after: avoid; } '
            . '.period { text-align: center; color: #45586B; margin: 0 0 12px; } '
            . 'h2 { font-size: 11pt; color: #fff; background: #123C5C; padding: 6px 8px; margin: 14px 0 7px; page-break-after: avoid; } '
            . 'h3 { font-size: 10pt; color: #0F766E; margin: 12px 0 5px; page-break-after: avoid; } '
            . 'p { margin: 4px 0 7px; } ul { margin: 4px 0 8px; padding-left: 18px; } li { margin: 0 0 4px; } '
            . 'table { width: 100%; border-collapse: collapse; table-layout: auto; margin: 6px 0 10px; word-wrap: break-word; overflow-wrap: anywhere; } '
            . 'thead { display: table-header-group; } tr { page-break-inside: avoid; } '
            . 'th, td { border: 1px solid #C9D4DA; padding: 5px 6px; text-align: left; vertical-align: top; overflow-wrap: anywhere; } '
            . 'th { background: #123C5C; color: #fff; font-size: 8.5pt; } td { color: #334155; font-size: 8.5pt; } '
            . '.metadata td { width: 25%; } .metadata td:nth-child(odd) { background: #EAF4F5; font-weight: bold; color: #123C5C; } '
            . '.highlight-summary td { background: #F3F7F8; text-align: center; font-weight: bold; color: #123C5C; font-size: 9pt; } '
            . '.highlight td { background: #EAF4F5; } .metric-value { font-size: 11pt; font-weight: bold; color: #123C5C; } '
            . '.section { page-break-inside: auto; } .empty { text-align: center; padding: 14px; color: #60717D; font-style: italic; } '
            . '.approval td { height: 104px; vertical-align: top; width: 33.33%; } '
            . '.signature { display: block; margin-top: 34px; border-top: 1px solid #94A3B8; padding-top: 4px; } '
            . '.signature-name, .signature-date { display: block; font-size: 8pt; color: #60717D; margin-top: 2px; } '
            . '.control { margin-top: 14px; padding: 8px 10px; background: #F3F7F8; border-left: 3px solid #0F9488; font-size: 8pt; color: #45586B; page-break-inside: avoid; }';
    }
}
