<?php

namespace XLinic\Framework\Core\Action;

use XLinic\Framework\Core\Module\ModuleManifest;

class ActionRegistry
{
    /**
     * Registered server actions.
     *
     * @var array<string, ServerAction>
     */
    protected array $serverActions = [];

    /**
     * Registered scheduled actions.
     *
     * @var array<string, ScheduledAction>
     */
    protected array $scheduledActions = [];

    /**
     * Register actions from a module manifest.
     */
    public function registerFromManifest(ModuleManifest $manifest): void
    {
        // Register server actions
        foreach ($manifest->serverActions as $actionData) {
            if (isset($actionData['class']) && class_exists($actionData['class'])) {
                $this->registerServerAction(new $actionData['class']());
            }
        }

        // Register scheduled actions
        foreach ($manifest->scheduledActions as $actionData) {
            if (isset($actionData['class']) && class_exists($actionData['class'])) {
                $this->registerScheduledAction(new $actionData['class']());
            }
        }
    }

    /**
     * Register a server action.
     */
    public function registerServerAction(ServerAction $action): void
    {
        $this->serverActions[$action->getCode()] = $action;
    }

    /**
     * Register a scheduled action.
     */
    public function registerScheduledAction(ScheduledAction $action): void
    {
        $this->scheduledActions[$action->getCode()] = $action;
    }

    /**
     * Get a server action by code.
     */
    public function getServerAction(string $code): ?ServerAction
    {
        return $this->serverActions[$code] ?? null;
    }

    /**
     * Get a scheduled action by code.
     */
    public function getScheduledAction(string $code): ?ScheduledAction
    {
        return $this->scheduledActions[$code] ?? null;
    }

    /**
     * Get all server actions.
     */
    public function getAllServerActions(): array
    {
        return $this->serverActions;
    }

    /**
     * Get all scheduled actions.
     */
    public function getAllScheduledActions(): array
    {
        return $this->scheduledActions;
    }

    /**
     * Execute a server action.
     */
    public function executeServerAction(string $code, array $data = []): mixed
    {
        $action = $this->getServerAction($code);

        if (!$action) {
            throw new \InvalidArgumentException("Server action not found: {$code}");
        }

        // Check permissions
        if (!$action->canExecute(auth()->user())) {
            throw new \UnauthorizedAccessException("Not authorized to execute action: {$code}");
        }

        // Log the execution
        activity()
            ->withProperties(['action_code' => $code, 'data' => $data])
            ->log("Server action executed: {$code}");

        return $action->execute($data);
    }

    /**
     * Get server actions available to current user.
     */
    public function getAvailableServerActions($user = null): array
    {
        $user = $user ?? auth()->user();
        $available = [];

        foreach ($this->serverActions as $code => $action) {
            if ($action->canExecute($user)) {
                $available[$code] = $action;
            }
        }

        return $available;
    }

    /**
     * Get scheduled actions that should run now.
     */
    public function getDueScheduledActions(): array
    {
        $due = [];

        foreach ($this->scheduledActions as $code => $action) {
            if ($action->isDue()) {
                $due[$code] = $action;
            }
        }

        return $due;
    }

    /**
     * Execute all due scheduled actions.
     */
    public function executeDueScheduledActions(): array
    {
        $dueActions = $this->getDueScheduledActions();
        $results = [];

        foreach ($dueActions as $code => $action) {
            try {
                $result = $action->execute();
                $results[$code] = ['success' => true, 'result' => $result];

                activity()
                    ->withProperties(['action_code' => $code])
                    ->log("Scheduled action executed successfully: {$code}");
            } catch (\Exception $e) {
                $results[$code] = ['success' => false, 'error' => $e->getMessage()];

                activity()
                    ->withProperties(['action_code' => $code, 'error' => $e->getMessage()])
                    ->log("Scheduled action failed: {$code}");
            }
        }

        return $results;
    }

    /**
     * Get actions by module.
     */
    public function getActionsByModule(string $moduleCode): array
    {
        $moduleActions = [
            'server' => [],
            'scheduled' => []
        ];

        foreach ($this->serverActions as $code => $action) {
            if ($action->getModuleCode() === $moduleCode) {
                $moduleActions['server'][$code] = $action;
            }
        }

        foreach ($this->scheduledActions as $code => $action) {
            if ($action->getModuleCode() === $moduleCode) {
                $moduleActions['scheduled'][$code] = $action;
            }
        }

        return $moduleActions;
    }

    /**
     * Validate action data.
     */
    public function validateActionData(string $code, array $data): array
    {
        $action = $this->getServerAction($code);

        if (!$action) {
            throw new \InvalidArgumentException("Action not found: {$code}");
        }

        return $action->validate($data);
    }

    /**
     * Get action statistics.
     */
    public function getStats(): array
    {
        return [
            'total_server_actions' => count($this->serverActions),
            'total_scheduled_actions' => count($this->scheduledActions),
            'due_scheduled_actions' => count($this->getDueScheduledActions()),
        ];
    }

    /**
     * Clear all registered actions.
     */
    public function clear(): void
    {
        $this->serverActions = [];
        $this->scheduledActions = [];
    }
}