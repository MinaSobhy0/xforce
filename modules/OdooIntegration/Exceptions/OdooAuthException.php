<?php

namespace Modules\OdooIntegration\Exceptions;

use Exception;
use Throwable;

class OdooAuthException extends Exception
{
    protected ?string $username = null;
    protected ?string $database = null;

    public function __construct(
        string $message = 'Odoo authentication failed',
        int $code = 401,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public static function invalidCredentials(string $username, string $database): self
    {
        $exception = new self(
            "Invalid credentials for user '{$username}' on database '{$database}'"
        );
        $exception->username = $username;
        $exception->database = $database;

        return $exception;
    }

    public static function sessionExpired(): self
    {
        return new self('Odoo session has expired', 401);
    }

    public static function accessDenied(string $model, string $operation): self
    {
        return new self(
            "Access denied: cannot {$operation} on model '{$model}'",
            403
        );
    }

    public static function databaseNotFound(string $database): self
    {
        $exception = new self(
            "Database '{$database}' not found on Odoo server",
            404
        );
        $exception->database = $database;

        return $exception;
    }

    public static function apiKeyInvalid(): self
    {
        return new self('Invalid or expired API key', 401);
    }
}
