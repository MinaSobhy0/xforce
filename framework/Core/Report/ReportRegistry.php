<?php

namespace XLinic\Framework\Core\Report;

use InvalidArgumentException;
use XLinic\Framework\Core\Tenancy\TenantManager;
use XLinic\Framework\Core\Security\SecurityManager;

/**
 * Report Registry
 *
 * Central registry for managing report definitions, instances,
 * and metadata. Handles report registration, discovery,
 * and access control for the reporting system.
 *
 * @package XLinic\Framework\Core\Report
 */
class ReportRegistry
{
    /**
     * Registered reports
     */
    protected array $reports = [];

    /**
     * Report categories
     */
    protected array $categories = [];

    /**
     * Report metadata cache
     */
    protected array $metadataCache = [];

    /**
     * The tenant manager instance
     */
    protected TenantManager $tenantManager;

    /**
     * The security manager instance
     */
    protected SecurityManager $securityManager;

    /**
     * Create a new report registry
     */
    public function __construct(
        TenantManager $tenantManager,
        SecurityManager $securityManager
    ) {
        $this->tenantManager = $tenantManager;
        $this->securityManager = $securityManager;
    }

    /**
     * Register a report
     */
    public function register(string $key, string $reportClass, array $metadata = []): void
    {
        if (!class_exists($reportClass)) {
            throw new InvalidArgumentException("Report class {$reportClass} does not exist");
        }

        if (!is_subclass_of($reportClass, BaseReport::class)) {
            throw new InvalidArgumentException("Report class {$reportClass} must extend BaseReport");
        }

        $this->reports[$key] = [
            'class' => $reportClass,
            'metadata' => array_merge($this->getDefaultMetadata($reportClass), $metadata),
            'registered_at' => now(),
        ];

        // Clear metadata cache
        unset($this->metadataCache[$key]);
    }

    /**
     * Unregister a report
     */
    public function unregister(string $key): void
    {
        unset($this->reports[$key]);
        unset($this->metadataCache[$key]);
    }

    /**
     * Check if a report is registered
     */
    public function has(string $key): bool
    {
        return isset($this->reports[$key]);
    }

    /**
     * Get a report instance
     */
    public function get(string $key): BaseReport
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException("Report {$key} is not registered");
        }

        $reportClass = $this->reports[$key]['class'];
        return app($reportClass);
    }

    /**
     * Get report metadata
     */
    public function getMetadata(string $key): array
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException("Report {$key} is not registered");
        }

        if (isset($this->metadataCache[$key])) {
            return $this->metadataCache[$key];
        }

        $metadata = $this->reports[$key]['metadata'];
        $this->metadataCache[$key] = $metadata;

        return $metadata;
    }

    /**
     * Get all registered reports
     */
    public function all(): array
    {
        return array_keys($this->reports);
    }

    /**
     * Get accessible reports for current user
     */
    public function getAccessible(?object $user = null): array
    {
        $user = $user ?? auth()->user();
        $accessible = [];

        foreach ($this->reports as $key => $config) {
            if ($this->canAccess($key, $user)) {
                $accessible[$key] = $this->getMetadata($key);
            }
        }

        return $accessible;
    }

    /**
     * Get reports by category
     */
    public function getByCategory(string $category): array
    {
        $categoryReports = [];

        foreach ($this->reports as $key => $config) {
            $metadata = $this->getMetadata($key);
            if (($metadata['category'] ?? '') === $category) {
                $categoryReports[$key] = $metadata;
            }
        }

        return $categoryReports;
    }

    /**
     * Get reports by module
     */
    public function getByModule(string $module): array
    {
        $moduleReports = [];

        foreach ($this->reports as $key => $config) {
            $metadata = $this->getMetadata($key);
            if (($metadata['module'] ?? '') === $module) {
                $moduleReports[$key] = $metadata;
            }
        }

        return $moduleReports;
    }

    /**
     * Register a category
     */
    public function registerCategory(string $key, array $definition): void
    {
        $this->categories[$key] = array_merge([
            'name' => $key,
            'description' => '',
            'icon' => 'heroicon-o-document-chart-bar',
            'sort_order' => 999,
        ], $definition);
    }

    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * Search reports
     */
    public function search(string $query, array $filters = []): array
    {
        $results = [];
        $query = strtolower($query);

        foreach ($this->reports as $key => $config) {
            $metadata = $this->getMetadata($key);

            // Apply filters
            if (!$this->matchesFilters($metadata, $filters)) {
                continue;
            }

            // Text search
            $searchable = [
                strtolower($metadata['name'] ?? ''),
                strtolower($metadata['description'] ?? ''),
                strtolower($metadata['category'] ?? ''),
                strtolower($metadata['module'] ?? ''),
                strtolower(implode(' ', $metadata['tags'] ?? [])),
            ];

            $searchText = implode(' ', $searchable);

            if (empty($query) || str_contains($searchText, $query)) {
                $results[$key] = $metadata;
            }
        }

        return $results;
    }

    /**
     * Get report statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_reports' => count($this->reports),
            'categories' => count($this->categories),
            'by_category' => [],
            'by_module' => [],
            'by_type' => [],
        ];

        foreach ($this->reports as $key => $config) {
            $metadata = $this->getMetadata($key);

            // Count by category
            $category = $metadata['category'] ?? 'uncategorized';
            $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;

            // Count by module
            $module = $metadata['module'] ?? 'core';
            $stats['by_module'][$module] = ($stats['by_module'][$module] ?? 0) + 1;

            // Count by type
            $type = $metadata['type'] ?? 'standard';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Validate report configuration
     */
    public function validate(string $key): array
    {
        $errors = [];

        if (!$this->has($key)) {
            $errors[] = "Report {$key} is not registered";
            return $errors;
        }

        try {
            $report = $this->get($key);

            // Check if report implements required methods
            if (!method_exists($report, 'generate')) {
                $errors[] = "Report {$key} must implement generate() method";
            }

            // Validate metadata
            $metadata = $this->getMetadata($key);

            if (empty($metadata['name'])) {
                $errors[] = "Report {$key} must have a name";
            }

            if (empty($metadata['description'])) {
                $errors[] = "Report {$key} should have a description";
            }

            // Validate parameters
            if (method_exists($report, 'getParameters')) {
                $parameters = $report->getParameters();
                foreach ($parameters as $param => $config) {
                    if (empty($config['type'])) {
                        $errors[] = "Parameter {$param} in report {$key} must have a type";
                    }
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Failed to instantiate report {$key}: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Check if user can access report
     */
    protected function canAccess(string $key, ?object $user = null): bool
    {
        if (!$user) {
            return false;
        }

        $metadata = $this->getMetadata($key);
        $permissions = $metadata['permissions'] ?? [];

        // Check permissions if specified
        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$user->can($permission)) {
                    return false;
                }
            }
        }

        // Check roles if specified
        $roles = $metadata['roles'] ?? [];
        if (!empty($roles)) {
            $userRoles = $this->getUserRoles($user);
            if (empty(array_intersect($userRoles, $roles))) {
                return false;
            }
        }

        // Check tenant access
        if ($metadata['tenant_aware'] ?? true) {
            if (!$this->tenantManager->getCurrentTenant()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get default metadata from report class
     */
    protected function getDefaultMetadata(string $reportClass): array
    {
        $metadata = [
            'name' => class_basename($reportClass),
            'description' => '',
            'category' => 'general',
            'module' => null,
            'type' => 'standard',
            'permissions' => [],
            'roles' => [],
            'tenant_aware' => true,
            'parameters' => [],
            'exports' => ['pdf', 'excel'],
            'tags' => [],
            'schedule' => false,
            'cacheable' => false,
            'cache_ttl' => 3600,
        ];

        // Get metadata from report class if available
        if (method_exists($reportClass, 'getMetadata')) {
            $classMetadata = $reportClass::getMetadata();
            $metadata = array_merge($metadata, $classMetadata);
        }

        return $metadata;
    }

    /**
     * Check if metadata matches filters
     */
    protected function matchesFilters(array $metadata, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if (!isset($metadata[$key])) {
                return false;
            }

            $metadataValue = $metadata[$key];

            if (is_array($value)) {
                if (is_array($metadataValue)) {
                    if (empty(array_intersect($metadataValue, $value))) {
                        return false;
                    }
                } else {
                    if (!in_array($metadataValue, $value)) {
                        return false;
                    }
                }
            } else {
                if ($metadataValue !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get user roles
     */
    protected function getUserRoles(object $user): array
    {
        if (method_exists($user, 'getRoles')) {
            return $user->getRoles();
        }

        if (method_exists($user, 'roles')) {
            return $user->roles()->pluck('name')->toArray();
        }

        return [$user->role ?? 'user'];
    }

    /**
     * Load reports from configuration
     */
    public function loadFromConfig(array $config): void
    {
        foreach ($config as $key => $reportConfig) {
            if (isset($reportConfig['class'])) {
                $this->register($key, $reportConfig['class'], $reportConfig['metadata'] ?? []);
            }
        }
    }

    /**
     * Load categories from configuration
     */
    public function loadCategoriesFromConfig(array $config): void
    {
        foreach ($config as $key => $categoryConfig) {
            $this->registerCategory($key, $categoryConfig);
        }
    }

    /**
     * Export registry configuration
     */
    public function exportConfig(): array
    {
        $config = [
            'reports' => [],
            'categories' => $this->categories,
        ];

        foreach ($this->reports as $key => $reportConfig) {
            $config['reports'][$key] = [
                'class' => $reportConfig['class'],
                'metadata' => $reportConfig['metadata'],
            ];
        }

        return $config;
    }

    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        $this->metadataCache = [];
    }
}