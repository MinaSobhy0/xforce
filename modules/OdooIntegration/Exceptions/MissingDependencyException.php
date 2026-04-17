<?php

namespace Modules\OdooIntegration\Exceptions;

use Exception;

/**
 * Exception thrown when a record depends on a related entity that doesn't exist locally.
 * Records with this exception should be skipped during sync rather than failing.
 */
class MissingDependencyException extends Exception
{
    protected string $dependencyType;
    protected string $dependencyField;
    protected int $odooId;
    protected ?string $relatedModel;

    public function __construct(
        string $field,
        int $odooId,
        ?string $relatedModel = null,
        string $message = ''
    ) {
        $this->dependencyField = $field;
        $this->odooId = $odooId;
        $this->relatedModel = $relatedModel;
        $this->dependencyType = $relatedModel ? class_basename($relatedModel) : 'unknown';

        if (empty($message)) {
            $message = "Missing dependency: {$this->dependencyType} with Odoo ID {$odooId} not found (required for field '{$field}')";
        }

        parent::__construct($message);
    }

    public function getDependencyField(): string
    {
        return $this->dependencyField;
    }

    public function getDependencyOdooId(): int
    {
        return $this->odooId;
    }

    public function getRelatedModel(): ?string
    {
        return $this->relatedModel;
    }

    public function getDependencyType(): string
    {
        return $this->dependencyType;
    }

    /**
     * Create exception for missing user dependency.
     */
    public static function missingUser(string $field, int $odooId): self
    {
        return new self($field, $odooId, \Modules\Auth\Models\User::class);
    }

    /**
     * Create exception for missing staff profile dependency.
     */
    public static function missingStaffProfile(string $field, int $odooId): self
    {
        return new self($field, $odooId, \Modules\Staff\Models\StaffProfile::class);
    }

    /**
     * Create exception for missing related record.
     */
    public static function missingRelation(string $field, int $odooId, ?string $model = null): self
    {
        return new self($field, $odooId, $model);
    }
}
