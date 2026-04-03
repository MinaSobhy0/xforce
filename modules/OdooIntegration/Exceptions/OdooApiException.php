<?php

namespace Modules\OdooIntegration\Exceptions;

use Exception;
use Throwable;

class OdooApiException extends Exception
{
    protected array $context = [];

    public function __construct(
        string $message = 'Odoo API error',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    public static function connectionFailed(string $host, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to connect to Odoo server at {$host}",
            500,
            $previous,
            ['host' => $host]
        );
    }

    public static function methodNotAllowed(string $method, string $model): self
    {
        return new self(
            "Method '{$method}' is not allowed on model '{$model}'",
            403,
            null,
            ['method' => $method, 'model' => $model]
        );
    }

    public static function recordNotFound(string $model, int $id): self
    {
        return new self(
            "Record not found: {$model}#{$id}",
            404,
            null,
            ['model' => $model, 'id' => $id]
        );
    }

    public static function invalidResponse(string $message, mixed $response = null): self
    {
        return new self(
            "Invalid API response: {$message}",
            500,
            null,
            ['response' => $response]
        );
    }
}
