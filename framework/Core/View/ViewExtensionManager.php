<?php

namespace XLinic\Framework\Core\View;

use XLinic\Framework\Core\View\Extensions\FormExtension;
use XLinic\Framework\Core\View\Extensions\TableExtension;
use XLinic\Framework\Core\View\Extensions\DashboardExtension;
use XLinic\Framework\Core\View\Extensions\WidgetExtension;

class ViewExtensionManager
{
    /**
     * Registered extensions.
     *
     * @var array<string, array<string, array<string>>>
     */
    protected array $extensions = [
        'form' => [],
        'table' => [],
        'dashboard' => [],
        'widget' => [],
    ];

    /**
     * Extension instances cache.
     *
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * Register an extension.
     */
    public function registerExtension(string $type, string $target, string $extensionClass): void
    {
        if (!isset($this->extensions[$type])) {
            $this->extensions[$type] = [];
        }

        if (!isset($this->extensions[$type][$target])) {
            $this->extensions[$type][$target] = [];
        }

        $this->extensions[$type][$target][] = $extensionClass;
    }

    /**
     * Get extensions for a target.
     */
    public function getExtensions(string $type, string $target): array
    {
        return $this->extensions[$type][$target] ?? [];
    }

    /**
     * Apply form extensions.
     */
    public function applyFormExtensions(string $target, $form): void
    {
        $extensions = $this->getExtensions('form', $target);

        foreach ($extensions as $extensionClass) {
            $extension = $this->getExtensionInstance($extensionClass);

            if ($extension instanceof FormExtension) {
                $extension->extend($form);
            }
        }
    }

    /**
     * Apply table extensions.
     */
    public function applyTableExtensions(string $target, $table): void
    {
        $extensions = $this->getExtensions('table', $target);

        foreach ($extensions as $extensionClass) {
            $extension = $this->getExtensionInstance($extensionClass);

            if ($extension instanceof TableExtension) {
                $extension->extend($table);
            }
        }
    }

    /**
     * Apply dashboard extensions.
     */
    public function applyDashboardExtensions(string $target, $dashboard): void
    {
        $extensions = $this->getExtensions('dashboard', $target);

        foreach ($extensions as $extensionClass) {
            $extension = $this->getExtensionInstance($extensionClass);

            if ($extension instanceof DashboardExtension) {
                $extension->extend($dashboard);
            }
        }
    }

    /**
     * Apply widget extensions.
     */
    public function applyWidgetExtensions(string $target, $widget): void
    {
        $extensions = $this->getExtensions('widget', $target);

        foreach ($extensions as $extensionClass) {
            $extension = $this->getExtensionInstance($extensionClass);

            if ($extension instanceof WidgetExtension) {
                $extension->extend($widget);
            }
        }
    }

    /**
     * Get extension instance (with caching).
     */
    protected function getExtensionInstance(string $extensionClass): object
    {
        if (!isset($this->instances[$extensionClass])) {
            $this->instances[$extensionClass] = app($extensionClass);
        }

        return $this->instances[$extensionClass];
    }

    /**
     * Get all registered extensions.
     */
    public function getAllExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Get extensions by type.
     */
    public function getExtensionsByType(string $type): array
    {
        return $this->extensions[$type] ?? [];
    }

    /**
     * Check if target has extensions.
     */
    public function hasExtensions(string $type, string $target): bool
    {
        return !empty($this->extensions[$type][$target] ?? []);
    }

    /**
     * Remove extensions for a target.
     */
    public function removeExtensions(string $type, string $target): void
    {
        unset($this->extensions[$type][$target]);
    }

    /**
     * Clear all extensions.
     */
    public function clear(): void
    {
        $this->extensions = [
            'form' => [],
            'table' => [],
            'dashboard' => [],
            'widget' => [],
        ];
        $this->instances = [];
    }

    /**
     * Get extension statistics.
     */
    public function getStats(): array
    {
        $stats = [];

        foreach ($this->extensions as $type => $targets) {
            $stats[$type] = [
                'targets' => count($targets),
                'extensions' => array_sum(array_map('count', $targets)),
            ];
        }

        $stats['total_extensions'] = array_sum(array_column($stats, 'extensions'));
        $stats['total_targets'] = array_sum(array_column($stats, 'targets'));

        return $stats;
    }

    /**
     * Validate extension class.
     */
    public function validateExtension(string $type, string $extensionClass): array
    {
        $errors = [];

        if (!class_exists($extensionClass)) {
            $errors[] = "Extension class does not exist: {$extensionClass}";
            return $errors;
        }

        $expectedInterface = match($type) {
            'form' => FormExtension::class,
            'table' => TableExtension::class,
            'dashboard' => DashboardExtension::class,
            'widget' => WidgetExtension::class,
            default => null,
        };

        if ($expectedInterface && !is_subclass_of($extensionClass, $expectedInterface)) {
            $errors[] = "Extension class must implement {$expectedInterface}";
        }

        return $errors;
    }

    /**
     * Register multiple extensions.
     */
    public function registerMultiple(array $extensions): void
    {
        foreach ($extensions as $type => $targets) {
            foreach ($targets as $target => $extensionClasses) {
                foreach ((array) $extensionClasses as $extensionClass) {
                    $this->registerExtension($type, $target, $extensionClass);
                }
            }
        }
    }

    /**
     * Get extension priorities (for ordering).
     */
    public function getExtensionPriorities(string $type, string $target): array
    {
        $extensions = $this->getExtensions($type, $target);
        $priorities = [];

        foreach ($extensions as $extensionClass) {
            $instance = $this->getExtensionInstance($extensionClass);
            $priority = method_exists($instance, 'getPriority') ? $instance->getPriority() : 0;
            $priorities[$extensionClass] = $priority;
        }

        return $priorities;
    }

    /**
     * Apply extensions in priority order.
     */
    public function applyExtensionsWithPriority(string $type, string $target, $subject): void
    {
        $extensions = $this->getExtensions($type, $target);
        $priorities = $this->getExtensionPriorities($type, $target);

        // Sort by priority (higher first)
        uasort($extensions, function ($a, $b) use ($priorities) {
            return ($priorities[$b] ?? 0) <=> ($priorities[$a] ?? 0);
        });

        // Apply extensions based on type
        foreach ($extensions as $extensionClass) {
            $extension = $this->getExtensionInstance($extensionClass);

            switch ($type) {
                case 'form':
                    if ($extension instanceof FormExtension) {
                        $extension->extend($subject);
                    }
                    break;
                case 'table':
                    if ($extension instanceof TableExtension) {
                        $extension->extend($subject);
                    }
                    break;
                case 'dashboard':
                    if ($extension instanceof DashboardExtension) {
                        $extension->extend($subject);
                    }
                    break;
                case 'widget':
                    if ($extension instanceof WidgetExtension) {
                        $extension->extend($subject);
                    }
                    break;
            }
        }
    }
}