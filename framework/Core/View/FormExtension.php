<?php

namespace XLinic\Framework\Core\View;

abstract class FormExtension
{
    /**
     * Extend the form.
     */
    abstract public function extend($form): void;

    /**
     * Get the priority for this extension.
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Check if this extension should be applied.
     */
    public function shouldApply($form): bool
    {
        return true;
    }

    /**
     * Add field to form.
     */
    protected function addField($form, string $name, $field): void
    {
        if (method_exists($form, 'schema')) {
            $schema = $form->getSchema();
            $schema[] = $field;
            $form->schema($schema);
        }
    }

    /**
     * Add fields to a specific position in form.
     */
    protected function addFieldsAt($form, int $position, array $fields): void
    {
        if (method_exists($form, 'schema')) {
            $schema = $form->getSchema();
            array_splice($schema, $position, 0, $fields);
            $form->schema($schema);
        }
    }

    /**
     * Add fields before a specific field.
     */
    protected function addFieldsBefore($form, string $fieldName, array $fields): void
    {
        if (method_exists($form, 'schema')) {
            $schema = $form->getSchema();
            $position = $this->findFieldPosition($schema, $fieldName);

            if ($position !== -1) {
                array_splice($schema, $position, 0, $fields);
                $form->schema($schema);
            }
        }
    }

    /**
     * Add fields after a specific field.
     */
    protected function addFieldsAfter($form, string $fieldName, array $fields): void
    {
        if (method_exists($form, 'schema')) {
            $schema = $form->getSchema();
            $position = $this->findFieldPosition($schema, $fieldName);

            if ($position !== -1) {
                array_splice($schema, $position + 1, 0, $fields);
                $form->schema($schema);
            }
        }
    }

    /**
     * Remove field from form.
     */
    protected function removeField($form, string $fieldName): void
    {
        if (method_exists($form, 'schema')) {
            $schema = $form->getSchema();
            $position = $this->findFieldPosition($schema, $fieldName);

            if ($position !== -1) {
                array_splice($schema, $position, 1);
                $form->schema($schema);
            }
        }
    }

    /**
     * Modify existing field.
     */
    protected function modifyField($form, string $fieldName, callable $callback): void
    {
        if (method_exists($form, 'schema')) {
            $schema = $form->getSchema();
            $position = $this->findFieldPosition($schema, $fieldName);

            if ($position !== -1 && isset($schema[$position])) {
                $schema[$position] = $callback($schema[$position]);
                $form->schema($schema);
            }
        }
    }

    /**
     * Find field position in schema.
     */
    protected function findFieldPosition(array $schema, string $fieldName): int
    {
        foreach ($schema as $index => $field) {
            if (method_exists($field, 'getName') && $field->getName() === $fieldName) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Add validation rules to form.
     */
    protected function addValidationRules($form, array $rules): void
    {
        if (method_exists($form, 'rules')) {
            $existingRules = $form->getRules() ?? [];
            $form->rules(array_merge($existingRules, $rules));
        }
    }

    /**
     * Add form actions.
     */
    protected function addActions($form, array $actions): void
    {
        if (method_exists($form, 'actions')) {
            $existingActions = $form->getActions() ?? [];
            $form->actions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Add header actions.
     */
    protected function addHeaderActions($form, array $actions): void
    {
        if (method_exists($form, 'headerActions')) {
            $existingActions = $form->getHeaderActions() ?? [];
            $form->headerActions(array_merge($existingActions, $actions));
        }
    }

    /**
     * Modify form using method chaining.
     */
    protected function modifyForm($form, callable $callback): void
    {
        $callback($form);
    }

    /**
     * Get current user for context.
     */
    protected function getCurrentUser()
    {
        return auth()->user();
    }

    /**
     * Get current tenant for context.
     */
    protected function getCurrentTenant()
    {
        return app(\XLinic\Framework\Core\Tenancy\TenantManager::class)->current();
    }

    /**
     * Check user permission.
     */
    protected function can(string $permission): bool
    {
        $user = $this->getCurrentUser();
        return $user && app(\XLinic\Framework\Core\Security\PermissionRegistry::class)->userCan($user, $permission);
    }

    /**
     * Check user role.
     */
    protected function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();
        return $user && app(\XLinic\Framework\Core\Security\PermissionRegistry::class)->userHasRole($user, $role);
    }
}