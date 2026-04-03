<?php

namespace Modules\OdooIntegration\Services\Api;

use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Enums\ApiProtocol;
use Modules\OdooIntegration\Exceptions\OdooApiException;

class OdooApiFactory
{
    /**
     * Cached client instances by connection ID.
     */
    protected array $clients = [];

    /**
     * Create or get a cached API client for a connection.
     */
    public function make(OdooConnection $connection): OdooApiClientInterface
    {
        $cacheKey = $connection->id;

        if (isset($this->clients[$cacheKey])) {
            return $this->clients[$cacheKey];
        }

        $client = $this->createClient($connection);
        $this->clients[$cacheKey] = $client;

        return $client;
    }

    /**
     * Create a new API client instance.
     */
    public function createClient(OdooConnection $connection): OdooApiClientInterface
    {
        $protocol = $connection->protocol;

        return match ($protocol) {
            ApiProtocol::XMLRPC => (new XmlRpcClient())->init($connection),
            ApiProtocol::REST => (new RestApiClient())->init($connection),
            default => throw OdooApiException::invalidResponse("Unsupported protocol: {$protocol->value}"),
        };
    }

    /**
     * Clear cached client for a connection.
     */
    public function forget(OdooConnection $connection): void
    {
        unset($this->clients[$connection->id]);
    }

    /**
     * Clear all cached clients.
     */
    public function flush(): void
    {
        $this->clients = [];
    }

    /**
     * Get the default connection's client.
     */
    public function default(?int $tenantId = null): ?OdooApiClientInterface
    {
        $connection = OdooConnection::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (!$connection) {
            return null;
        }

        return $this->make($connection);
    }
}
