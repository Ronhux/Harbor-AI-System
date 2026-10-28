<?php

namespace App\Services\Reports;

use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class ReportPresentationService
{
    private const SUMMARY_LABELS = [
        'available_listing_groups' => 'Available Listing Groups',
        'active_demand_groups' => 'Active Demand Groups',
        'producers' => 'Producer Accounts',
        'verified_producers' => 'Verified Producers',
        'producers_with_listing_activity' => 'Producers with Listing Activity',
        'listings_created' => 'Listings Created',
        'demand_requests' => 'Demand Requests',
        'orders' => 'Orders',
        'recorded_order_amount_total_not_revenue' => 'Recorded Order Amount (Not Revenue)',
    ];

    public function decorate(array $report): array
    {
        $summary = $report['summary'] ?? [];
        $metrics = [];
        $tables = [];

        foreach ($summary as $key => $value) {
            if (is_array($value)) {
                $table = $this->summaryTable((string) $key, $value);
                if ($table !== null) {
                    $tables[] = $table;
                }
                continue;
            }

            $metrics[] = [
                'key' => (string) $key,
                'label' => $this->summaryLabel((string) $key),
                'value' => $value,
                'display_value' => $this->formatValue($value, (string) $key),
            ];
        }

        $definitions = $report['definitions'] ?? [];
        $filters = $report['filters'] ?? [];
        $type = (string) ($report['report_type'] ?? '');
        $period = (string) ($report['period'] ?? 'Not available');
        $timezone = (string) ($report['reporting_period']['timezone'] ?? 'UTC');
        $dateBasis = (string) ($report['reporting_period']['date_basis'] ?? 'Not available');
        $reportType = $this->typeLabel($type);
        $metricHighlights = array_map(
            fn (array $metric): string => $metric['label'] . ': ' . $metric['display_value'],
            $metrics
        );
        $highlightText = $metricHighlights;
        foreach ($tables as $table) {
            foreach ($table['rows'] as $row) {
                $highlightText[] = $table['title'] . ' — ' . implode(', ', array_map(
                    fn (string $column, string $index): string => $column . ': ' . $this->formatValue($row[$index] ?? null, $index),
                    array_column($table['columns'], 'label'),
                    array_column($table['columns'], 'key')
                ));
            }
        }

        $purpose = match ($type) {
            'producer_activity' => 'Summarizes Producer account registrations and listing activity recorded during the selected period.',
            'supply_demand' => 'Summarizes eligible verified-Producer supply listings and active Buyer demand recorded during the selected period.',
            'demand_summary' => 'Lists Buyer demand requests recorded during the selected period.',
            'order_transactions' => 'Lists persisted orders whose order date falls in the selected period.',
            default => 'Summarizes the selected HarborAI records for the reporting period.',
        };

        $scopeParts = [
            'Reporting period: ' . $period . ' (' . $timezone . ').',
            'Date basis: ' . $dateBasis,
            'Date boundaries: ' . (string) ($report['reporting_period']['boundaries'] ?? 'Inclusive calendar dates.'),
            'Detail rows: ' . number_format((int) ($report['row_count'] ?? count($report['rows'] ?? []))) . '.',
            'Filters: ' . ($filters === [] ? 'none applied.' : implode('; ', array_map(
                fn ($key, $value) => $this->humanize((string) $key) . ': ' . $this->formatValue($value),
                array_keys($filters),
                array_values($filters)
            )) . '.'),
        ];

        $analysisKeys = match ($type) {
            'producer_activity' => ['rows', 'registered_at', 'listings_created_in_period', 'activity_status'],
            'supply_demand' => ['available_supply', 'active_demand', 'grouping', 'amounts'],
            'demand_summary' => ['rows', 'requested_quantities_by_unit'],
            'order_transactions' => ['rows', 'order_date', 'recorded_order_amount', 'statuses'],
            default => [],
        };
        $analysis = array_values(array_filter(array_map(
            fn (string $key): ?string => isset($definitions[$key]) ? (string) $definitions[$key] : null,
            $analysisKeys
        )));

        $limitations = [
            'The report is a system-generated snapshot for the selected criteria. It does not contain a calculated comparison with a prior period.',
        ];
        if ($type === 'producer_activity') {
            $limitations[] = 'Producer accounts without a value in the canonical account-creation date field are not included in this date-filtered report.';
        }
        foreach (['amounts', 'recorded_order_amount', 'activity_status'] as $key) {
            if (isset($definitions[$key])) {
                $limitations[] = (string) $definitions[$key];
            }
        }

        $metricSummary = $metricHighlights === []
            ? 'No summary metrics were calculated for this report.'
            : implode('; ', $metricHighlights) . '.';
        $rowCount = (int) ($report['row_count'] ?? count($report['rows'] ?? []));
        $conclusion = $rowCount === 0
            ? 'No records matched the selected ' . Str::lower($reportType) . ' criteria for ' . $period . '.'
            : 'The report contains ' . number_format($rowCount) . ' detail ' . Str::plural('record', $rowCount) . ' for ' . $period . '. ' . $metricSummary;

        $category = match ($type) {
            'producer_activity' => 'Producer Accounts and Activity',
            'supply_demand' => 'Marketplace Supply and Demand',
            'demand_summary' => 'Buyer Demand Requests',
            'order_transactions' => 'Order Transactions',
            default => 'HarborAI Administrative Data',
        };

        return array_merge($report, [
            'presentation' => [
                'metadata' => [
                    ['label' => 'Report Reference', 'value' => $report['report_reference'] ?? 'Not assigned (preview)'],
                    ['label' => 'Reporting Period', 'value' => $period],
                    ['label' => 'Date Generated', 'value' => $this->formatDate($report['generated_at'] ?? null, $timezone)],
                    ['label' => 'Prepared By', 'value' => $report['prepared_by'] ?? 'Not available'],
                    ['label' => 'Report Category', 'value' => $category],
                    ['label' => 'Data As Of', 'value' => $this->formatDateTime($report['generated_at'] ?? null, $timezone)],
                    ['label' => 'Intended Audience', 'value' => 'Authorized HarborAI administrators'],
                    ['label' => 'Status', 'value' => 'System-generated snapshot; not an approval'],
                ],
                'executive_summary' => $reportType . ' for ' . $period . '. ' . $rowCount . ' detail ' . Str::plural('record', $rowCount) . ' matched the selected filters. ' . $metricSummary,
                'purpose_scope' => $purpose . ' ' . implode(' ', $scopeParts),
                'highlights' => $metrics,
                'highlight_summary' => [
                    'total_records' => $rowCount,
                    'key_metric' => $metricHighlights[0] ?? 'Not available',
                    'change_status' => 'Compared with prior period: not available (single-period snapshot)',
                ],
                'summary_tables' => $tables,
                'findings' => $highlightText === [] ? ['No records matched the selected criteria.'] : $highlightText,
                'analysis' => $analysis === [] ? ['This report presents only the selected report type and the records returned by its defined query.'] : $analysis,
                'limitations' => array_values(array_unique($limitations)),
                'conclusion' => $conclusion,
                'recommendations' => 'No automated recommendation is generated from these records. Review the findings through the existing HarborAI administrative workflows as appropriate.',
                'approval' => [
                    'prepared_by' => $report['prepared_by'] ?? 'Not available',
                    'prepared_by_role' => $report['prepared_by_role'] ?? 'Admin',
                    'reviewed_by' => '',
                    'approved_by' => '',
                ],
            ],
        ]);
    }

    public function summaryLabel(string $key): string
    {
        return self::SUMMARY_LABELS[$key] ?? Str::of($key)->replace('_', ' ')->title()->toString();
    }

    public function formatValue(mixed $value, string $key = ''): string
    {
        if ($value === null || $value === '') {
            return 'Not available';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $number = (float) $value;
            if (str_contains($key, 'amount') || str_contains($key, 'price')) {
                return '₱' . number_format($number, 2);
            }
            return number_format($number, fmod($number, 1.0) === 0.0 ? 0 : 2);
        }
        if (is_array($value) || is_object($value)) {
            return 'See structured report table';
        }
        return (string) $value;
    }

    private function summaryTable(string $key, array $value): ?array
    {
        $unitTotals = str_ends_with($key, '_by_unit');
        $rows = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($unitTotals && array_key_exists('unit', $item)) {
                $rows[] = ['unit' => $item['unit'], 'quantity' => $item['quantity'] ?? null];
            } elseif (array_key_exists('value', $item) && array_key_exists('count', $item)) {
                $rows[] = ['status' => $item['value'], 'records' => $item['count']];
            } else {
                $rows[] = array_map(fn ($cell) => is_array($cell) || is_object($cell) ? 'See details' : $cell, $item);
            }
        }

        if ($rows === []) {
            return [
                'key' => $key,
                'title' => $this->summaryLabel($key),
                'columns' => $unitTotals
                    ? [['key' => 'unit', 'label' => 'Unit'], ['key' => 'quantity', 'label' => 'Quantity']]
                    : [['key' => 'status', 'label' => 'Status'], ['key' => 'records', 'label' => 'Records']],
                'rows' => [],
            ];
        }

        $columns = array_map(
            fn (string $column): array => ['key' => $column, 'label' => $this->humanize($column)],
            array_keys($rows[0])
        );

        return [
            'key' => $key,
            'title' => $this->summaryLabel($key),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'producer_activity' => 'Producer Activity Report',
            'supply_demand' => 'Supply-Demand Report',
            'demand_summary' => 'Demand Summary Report',
            'order_transactions' => 'Order Transactions Report',
            default => 'HarborAI Administrative Report',
        };
    }

    private function humanize(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function formatDateTime(mixed $value, string $timezone): string
    {
        if (!is_string($value) || $value === '') {
            return 'Not available';
        }

        return CarbonImmutable::parse($value)->timezone($timezone)->format('F j, Y g:i A T');
    }

    /**
     * Template date format: Month DD, YYYY (used for Date Generated).
     */
    private function formatDate(mixed $value, string $timezone): string
    {
        if (!is_string($value) || $value === '') {
            return 'Not available';
        }

        return CarbonImmutable::parse($value)->timezone($timezone)->format('F j, Y');
    }
}
