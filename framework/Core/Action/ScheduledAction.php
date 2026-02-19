<?php

namespace XLinic\Framework\Core\Action;

abstract class ScheduledAction
{
    /**
     * The action code (unique identifier).
     */
    protected string $code;

    /**
     * The action name (translatable).
     */
    protected array $name = [];

    /**
     * The action description (translatable).
     */
    protected array $description = [];

    /**
     * The module this action belongs to.
     */
    protected ?string $moduleCode = null;

    /**
     * The cron expression for scheduling.
     */
    protected string $schedule = '0 0 * * *'; // Default: daily at midnight

    /**
     * Whether the action is enabled.
     */
    protected bool $enabled = true;

    /**
     * Maximum execution time in seconds.
     */
    protected int $timeout = 300; // 5 minutes

    /**
     * Last execution timestamp.
     */
    protected ?\DateTime $lastExecution = null;

    /**
     * Next execution timestamp.
     */
    protected ?\DateTime $nextExecution = null;

    /**
     * Execute the scheduled action.
     */
    abstract public function execute(): mixed;

    /**
     * Get the action code.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Get the action name for current locale.
     */
    public function getName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (isset($this->name[$locale])) {
            return $this->name[$locale];
        }

        if (isset($this->name['en'])) {
            return $this->name['en'];
        }

        if (!empty($this->name)) {
            return array_values($this->name)[0];
        }

        return str_replace(['_', '-'], ' ', ucwords($this->code, '_-'));
    }

    /**
     * Get the action description for current locale.
     */
    public function getDescription(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (isset($this->description[$locale])) {
            return $this->description[$locale];
        }

        if (isset($this->description['en'])) {
            return $this->description['en'];
        }

        if (!empty($this->description)) {
            return array_values($this->description)[0];
        }

        return $this->getName($locale);
    }

    /**
     * Get the module code this action belongs to.
     */
    public function getModuleCode(): ?string
    {
        return $this->moduleCode;
    }

    /**
     * Get the cron schedule.
     */
    public function getSchedule(): string
    {
        return $this->schedule;
    }

    /**
     * Set the cron schedule.
     */
    public function setSchedule(string $schedule): self
    {
        $this->schedule = $schedule;
        $this->calculateNextExecution();
        return $this;
    }

    /**
     * Check if the action is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable the action.
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Disable the action.
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Get the timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the timeout in seconds.
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Check if the action is due to run.
     */
    public function isDue(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now = new \DateTime();

        // If we've never run, we're due
        if (!$this->nextExecution) {
            $this->calculateNextExecution();
        }

        return $this->nextExecution && $now >= $this->nextExecution;
    }

    /**
     * Calculate the next execution time based on cron schedule.
     */
    protected function calculateNextExecution(): void
    {
        try {
            $cron = new \Cron\CronExpression($this->schedule);
            $this->nextExecution = $cron->getNextRunDate();
        } catch (\Exception $e) {
            // Invalid cron expression, default to daily
            $this->nextExecution = (new \DateTime())->modify('+1 day');
        }
    }

    /**
     * Mark the action as executed.
     */
    public function markAsExecuted(): void
    {
        $this->lastExecution = new \DateTime();
        $this->calculateNextExecution();

        // Log the execution
        activity()
            ->withProperties([
                'action_code' => $this->code,
                'module_code' => $this->moduleCode,
                'last_execution' => $this->lastExecution->format('Y-m-d H:i:s'),
                'next_execution' => $this->nextExecution?->format('Y-m-d H:i:s'),
            ])
            ->log("Scheduled action executed: {$this->getName()}");
    }

    /**
     * Get the last execution time.
     */
    public function getLastExecution(): ?\DateTime
    {
        return $this->lastExecution;
    }

    /**
     * Get the next execution time.
     */
    public function getNextExecution(): ?\DateTime
    {
        if (!$this->nextExecution) {
            $this->calculateNextExecution();
        }

        return $this->nextExecution;
    }

    /**
     * Before execution hook.
     */
    protected function beforeExecution(): void
    {
        // Override in subclasses if needed
    }

    /**
     * After execution hook.
     */
    protected function afterExecution(mixed $result): void
    {
        // Override in subclasses if needed
    }

    /**
     * Handle execution with hooks and error handling.
     */
    final public function handle(): mixed
    {
        $startTime = microtime(true);

        try {
            $this->beforeExecution();
            $result = $this->execute();
            $this->afterExecution($result);
            $this->markAsExecuted();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            activity()
                ->withProperties([
                    'action_code' => $this->code,
                    'execution_time_ms' => $executionTime,
                    'result' => is_scalar($result) ? $result : 'complex_result',
                ])
                ->log("Scheduled action completed successfully: {$this->getName()}");

            return $result;
        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            activity()
                ->withProperties([
                    'action_code' => $this->code,
                    'execution_time_ms' => $executionTime,
                    'error' => $e->getMessage(),
                    'error_line' => $e->getLine(),
                    'error_file' => $e->getFile(),
                ])
                ->log("Scheduled action failed: {$this->getName()}");

            throw $e;
        }
    }

    /**
     * Get action metadata.
     */
    public function getMetadata(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'module_code' => $this->moduleCode,
            'schedule' => $this->schedule,
            'enabled' => $this->enabled,
            'timeout' => $this->timeout,
            'last_execution' => $this->lastExecution?->format('Y-m-d H:i:s'),
            'next_execution' => $this->getNextExecution()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'module_code' => $this->moduleCode,
            'schedule' => $this->schedule,
            'schedule_human' => $this->getHumanReadableSchedule(),
            'enabled' => $this->enabled,
            'is_due' => $this->isDue(),
            'last_execution' => $this->lastExecution?->format('Y-m-d H:i:s'),
            'next_execution' => $this->getNextExecution()?->format('Y-m-d H:i:s'),
            'timeout' => $this->timeout,
        ];
    }

    /**
     * Get human-readable schedule description.
     */
    public function getHumanReadableSchedule(): string
    {
        try {
            $cron = new \Cron\CronExpression($this->schedule);
            return $cron->getExpression();
        } catch (\Exception $e) {
            return $this->schedule;
        }
    }

    /**
     * Common scheduled action helpers.
     */

    /**
     * Get current tenant for tenant-aware actions.
     */
    protected function getCurrentTenant()
    {
        return app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
    }

    /**
     * Execute for all tenants.
     */
    protected function executeForAllTenants(callable $callback): array
    {
        $tenantManager = app(\XLinic\Framework\Core\Tenancy\TenantManager::class);
        $tenants = $tenantManager->getAllTenants();
        $results = [];

        foreach ($tenants as $tenant) {
            try {
                $result = $tenantManager->runForTenant($tenant, $callback);
                $results[$tenant->id] = ['success' => true, 'result' => $result];
            } catch (\Exception $e) {
                $results[$tenant->id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Send email notification.
     */
    protected function sendEmailNotification(string $subject, string $body, array $recipients = []): void
    {
        // Implementation depends on mail system
        // This is a placeholder
    }

    /**
     * Log action progress.
     */
    protected function logProgress(string $message, array $properties = []): void
    {
        activity()
            ->withProperties(array_merge($properties, [
                'action_code' => $this->code,
                'module_code' => $this->moduleCode,
                'progress' => true,
            ]))
            ->log($message);
    }
}