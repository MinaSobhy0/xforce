<?php

namespace XLinic\Framework\Core\Report;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use XLinic\Framework\Core\Tenancy\TenantManager;
use XLinic\Framework\Core\Security\RecordPolicyEngine;

/**
 * Base Report Class
 *
 * Abstract base class for all reports in the system. Provides
 * common functionality for data retrieval, filtering, formatting,
 * and export capabilities with tenant awareness and security.
 *
 * @package XLinic\Framework\Core\Report
 */
abstract class BaseReport
{
    /**
     * Report output formats
     */
    public const FORMAT_HTML = 'html';
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_EXCEL = 'excel';
    public const FORMAT_CSV = 'csv';
    public const FORMAT_JSON = 'json';

    /**
     * Data aggregation types
     */
    public const AGGREGATE_SUM = 'sum';
    public const AGGREGATE_COUNT = 'count';
    public const AGGREGATE_AVG = 'average';
    public const AGGREGATE_MIN = 'min';
    public const AGGREGATE_MAX = 'max';

    /**
     * The tenant manager instance
     */
    protected TenantManager $tenantManager;

    /**
     * The record policy engine
     */
    protected RecordPolicyEngine $policyEngine;

    /**
     * Report parameters
     */
    protected array $parameters = [];

    /**
     * Report filters
     */
    protected array $filters = [];

    /**
     * Report data
     */
    protected Collection $data;

    /**
     * Report metadata
     */
    protected array $metadata = [];

    /**
     * Create a new report instance
     */
    public function __construct(
        TenantManager $tenantManager,
        RecordPolicyEngine $policyEngine
    ) {
        $this->tenantManager = $tenantManager;
        $this->policyEngine = $policyEngine;
        $this->data = new Collection();
        $this->initialize();
    }

    /**
     * Initialize the report
     */
    protected function initialize(): void
    {
        // Override in child classes for setup
    }

    /**
     * Generate the report
     */
    abstract public function generate(array $parameters = []): Collection;

    /**
     * Get report metadata
     */
    public static function getMetadata(): array
    {
        return [
            'name' => static::class,
            'description' => '',
            'category' => 'general',
            'type' => 'standard',
        ];
    }

    /**
     * Get report parameters definition
     */
    public function getParameters(): array
    {
        return [];
    }

    /**
     * Set report parameters
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }

    /**
     * Get a parameter value
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Set report filters
     */
    public function setFilters(array $filters): self
    {
        $this->filters = array_merge($this->filters, $filters);
        return $this;
    }

    /**
     * Add a filter
     */
    public function addFilter(string $field, mixed $value, string $operator = '='): self
    {
        $this->filters[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator,
        ];

        return $this;
    }

    /**
     * Get report data
     */
    public function getData(): Collection
    {
        return $this->data;
    }

    /**
     * Set report data
     */
    public function setData(Collection $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Apply tenant filtering to query
     */
    protected function applyTenantFilter(Builder $query): Builder
    {
        $tenant = $this->tenantManager->getCurrentTenant();

        if ($tenant && method_exists($query->getModel(), 'getTenantColumn')) {
            $tenantColumn = $query->getModel()->getTenantColumn();
            $query->where($tenantColumn, $tenant->id);
        }

        return $query;
    }

    /**
     * Apply security policies to query
     */
    protected function applySecurityPolicies(Builder $query): Builder
    {
        return $this->policyEngine->applyToQuery($query);
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters(Builder $query): Builder
    {
        foreach ($this->filters as $filter) {
            $field = $filter['field'];
            $value = $filter['value'];
            $operator = $filter['operator'] ?? '=';

            switch ($operator) {
                case '=':
                    $query->where($field, $value);
                    break;
                case '!=':
                    $query->where($field, '!=', $value);
                    break;
                case '>':
                    $query->where($field, '>', $value);
                    break;
                case '>=':
                    $query->where($field, '>=', $value);
                    break;
                case '<':
                    $query->where($field, '<', $value);
                    break;
                case '<=':
                    $query->where($field, '<=', $value);
                    break;
                case 'like':
                    $query->where($field, 'like', "%{$value}%");
                    break;
                case 'in':
                    $query->whereIn($field, is_array($value) ? $value : [$value]);
                    break;
                case 'not_in':
                    $query->whereNotIn($field, is_array($value) ? $value : [$value]);
                    break;
                case 'between':
                    if (is_array($value) && count($value) === 2) {
                        $query->whereBetween($field, $value);
                    }
                    break;
                case 'null':
                    $query->whereNull($field);
                    break;
                case 'not_null':
                    $query->whereNotNull($field);
                    break;
                default:
                    $query->where($field, $operator, $value);
            }
        }

        return $query;
    }

    /**
     * Apply date range filter
     */
    protected function applyDateRange(Builder $query, string $field, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->where($field, '>=', $from);
        }

        if ($to) {
            $query->where($field, '<=', $to);
        }

        return $query;
    }

    /**
     * Group data by field
     */
    protected function groupBy(string $field): Collection
    {
        return $this->data->groupBy($field);
    }

    /**
     * Aggregate data
     */
    protected function aggregate(string $field, string $type = self::AGGREGATE_SUM): mixed
    {
        return match ($type) {
            self::AGGREGATE_SUM => $this->data->sum($field),
            self::AGGREGATE_COUNT => $this->data->count(),
            self::AGGREGATE_AVG => $this->data->avg($field),
            self::AGGREGATE_MIN => $this->data->min($field),
            self::AGGREGATE_MAX => $this->data->max($field),
            default => null
        };
    }

    /**
     * Sort data
     */
    protected function sortBy(string $field, bool $descending = false): Collection
    {
        return $descending
            ? $this->data->sortByDesc($field)
            : $this->data->sortBy($field);
    }

    /**
     * Paginate data
     */
    protected function paginate(int $page = 1, int $perPage = 50): array
    {
        $total = $this->data->count();
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $this->data->slice($offset, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ]
        ];
    }

    /**
     * Format currency value
     */
    protected function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }

    /**
     * Format date
     */
    protected function formatDate(mixed $date, string $format = 'Y-m-d'): string
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format($format);
    }

    /**
     * Format percentage
     */
    protected function formatPercentage(float $value, int $decimals = 2): string
    {
        return number_format($value * 100, $decimals) . '%';
    }

    /**
     * Calculate percentage change
     */
    protected function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return (($newValue - $oldValue) / $oldValue) * 100;
    }

    /**
     * Get summary statistics
     */
    protected function getSummaryStats(string $field): array
    {
        $values = $this->data->pluck($field)->filter()->values();

        if ($values->isEmpty()) {
            return [
                'count' => 0,
                'sum' => 0,
                'average' => 0,
                'min' => 0,
                'max' => 0,
                'median' => 0,
            ];
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intval($count / 2);

        return [
            'count' => $count,
            'sum' => $sorted->sum(),
            'average' => $sorted->average(),
            'min' => $sorted->min(),
            'max' => $sorted->max(),
            'median' => $count % 2 === 0
                ? ($sorted[$middle - 1] + $sorted[$middle]) / 2
                : $sorted[$middle],
        ];
    }

    /**
     * Export report to format
     */
    public function export(string $format = self::FORMAT_PDF, array $options = []): mixed
    {
        return match ($format) {
            self::FORMAT_HTML => $this->exportToHtml($options),
            self::FORMAT_PDF => $this->exportToPdf($options),
            self::FORMAT_EXCEL => $this->exportToExcel($options),
            self::FORMAT_CSV => $this->exportToCsv($options),
            self::FORMAT_JSON => $this->exportToJson($options),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Export to HTML
     */
    protected function exportToHtml(array $options = []): string
    {
        $metadata = static::getMetadata();
        $title = $options['title'] ?? $metadata['name'];

        $html = "<html><head><title>{$title}</title></head><body>";
        $html .= "<h1>{$title}</h1>";
        $html .= "<table border='1'>";

        // Add headers
        if (!$this->data->isEmpty()) {
            $headers = array_keys($this->data->first()->toArray());
            $html .= "<tr>";
            foreach ($headers as $header) {
                $html .= "<th>" . ucwords(str_replace('_', ' ', $header)) . "</th>";
            }
            $html .= "</tr>";

            // Add data rows
            foreach ($this->data as $row) {
                $html .= "<tr>";
                foreach ($row->toArray() as $value) {
                    $html .= "<td>{$value}</td>";
                }
                $html .= "</tr>";
            }
        }

        $html .= "</table></body></html>";

        return $html;
    }

    /**
     * Export to PDF
     */
    protected function exportToPdf(array $options = []): string
    {
        // This would typically use a PDF library like TCPDF or DOMPDF
        // For now, return HTML that can be converted to PDF
        return $this->exportToHtml($options);
    }

    /**
     * Export to Excel
     */
    protected function exportToExcel(array $options = []): string
    {
        // This would typically use PhpSpreadsheet
        // For now, return CSV format
        return $this->exportToCsv($options);
    }

    /**
     * Export to CSV
     */
    protected function exportToCsv(array $options = []): string
    {
        if ($this->data->isEmpty()) {
            return '';
        }

        $output = '';
        $headers = array_keys($this->data->first()->toArray());

        // Add headers
        $output .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

        // Add data rows
        foreach ($this->data as $row) {
            $values = array_map([$this, 'csvEscape'], array_values($row->toArray()));
            $output .= implode(',', $values) . "\n";
        }

        return $output;
    }

    /**
     * Export to JSON
     */
    protected function exportToJson(array $options = []): string
    {
        $output = [
            'metadata' => static::getMetadata(),
            'parameters' => $this->parameters,
            'data' => $this->data,
            'generated_at' => now()->toISOString(),
        ];

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * Escape CSV value
     */
    protected function csvEscape(mixed $value): string
    {
        $stringValue = (string) $value;

        if (str_contains($stringValue, ',') || str_contains($stringValue, '"') || str_contains($stringValue, "\n")) {
            return '"' . str_replace('"', '""', $stringValue) . '"';
        }

        return $stringValue;
    }

    /**
     * Validate parameters
     */
    protected function validateParameters(): array
    {
        $errors = [];
        $parameterDefinitions = $this->getParameters();

        foreach ($parameterDefinitions as $key => $definition) {
            $value = $this->getParameter($key);

            if (($definition['required'] ?? false) && $value === null) {
                $errors[] = "Parameter {$key} is required";
            }

            if ($value !== null && isset($definition['type'])) {
                $errors = array_merge($errors, $this->validateParameterType($key, $value, $definition));
            }
        }

        return $errors;
    }

    /**
     * Validate parameter type
     */
    protected function validateParameterType(string $key, mixed $value, array $definition): array
    {
        $errors = [];
        $type = $definition['type'];

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "Parameter {$key} must be a string";
                }
                break;
            case 'integer':
                if (!is_int($value) && !ctype_digit((string) $value)) {
                    $errors[] = "Parameter {$key} must be an integer";
                }
                break;
            case 'date':
                if (!strtotime($value)) {
                    $errors[] = "Parameter {$key} must be a valid date";
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    $errors[] = "Parameter {$key} must be an array";
                }
                break;
        }

        return $errors;
    }
}