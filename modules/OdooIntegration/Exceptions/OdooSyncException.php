<?php

namespace Modules\OdooIntegration\Exceptions;

use Exception;
use Throwable;

class OdooSyncException extends Exception
{
    protected ?string $entityMapping = null;
    protected ?int $localId = null;
    protected ?int $odooId = null;
    protected array $errors = [];

    public function __construct(
        string $message = 'Sync operation failed',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getEntityMapping(): ?string
    {
        return $this->entityMapping;
    }

    public function getLocalId(): ?int
    {
        return $this->localId;
    }

    public function getOdooId(): ?int
    {
        return $this->odooId;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function importFailed(string $entity, int $odooId, string $reason, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to import {$entity} record #{$odooId}: {$reason}",
            500,
            $previous
        );
        $exception->entityMapping = $entity;
        $exception->odooId = $odooId;

        return $exception;
    }

    public static function exportFailed(string $entity, int $localId, string $reason, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to export {$entity} record #{$localId}: {$reason}",
            500,
            $previous
        );
        $exception->entityMapping = $entity;
        $exception->localId = $localId;

        return $exception;
    }

    public static function transformFailed(string $field, string $reason): self
    {
        return new self("Failed to transform field '{$field}': {$reason}");
    }

    public static function relationNotFound(string $model, int $odooId): self
    {
        return new self(
            "Related {$model} record with Odoo ID #{$odooId} not found locally"
        );
    }

    public static function conflictDetected(string $entity, int $localId, int $odooId): self
    {
        $exception = new self(
            "Conflict detected for {$entity}: local #{$localId} and Odoo #{$odooId} have diverged"
        );
        $exception->entityMapping = $entity;
        $exception->localId = $localId;
        $exception->odooId = $odooId;

        return $exception;
    }

    public static function validationFailed(string $entity, array $errors): self
    {
        $exception = new self(
            "Validation failed for {$entity}: " . json_encode($errors)
        );
        $exception->entityMapping = $entity;
        $exception->errors = $errors;

        return $exception;
    }

    public static function mappingNotFound(string $localModel): self
    {
        return new self("No entity mapping found for model '{$localModel}'");
    }

    public static function connectionNotConfigured(): self
    {
        return new self('No active Odoo connection configured');
    }
}
