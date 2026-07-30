<?php

namespace Modules\OdooIntegration\Services\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Client\PendingRequest;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Exceptions\OdooApiException;
use Modules\OdooIntegration\Exceptions\OdooAuthException;
use Modules\OdooIntegration\Exceptions\OdooRateLimitException;

class RestApiClient implements OdooApiClientInterface
{
    protected OdooConnection $connection;
    protected ?string $sessionId = null;
    protected ?int $uid = null;

    public function init(OdooConnection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    protected function http(): PendingRequest
    {
        $request = Http::baseUrl($this->connection->rest_api_url)
            ->timeout($this->connection->timeout)
            ->acceptJson()
            ->asJson();

        if ($this->sessionId) {
            $request->withCookies(['session_id' => $this->sessionId], parse_url($this->connection->host, PHP_URL_HOST));
        }

        if ($this->connection->api_key) {
            $request->withHeader('X-API-Key', $this->connection->api_key);
        }

        return $request;
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->http()->get('/');
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Odoo REST connection test failed', [
                'connection_id' => $this->connection->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function authenticate(): int
    {
        $this->checkRateLimit();

        try {
            $response = Http::baseUrl($this->connection->base_url)
                ->timeout($this->connection->timeout)
                ->acceptJson()
                ->asJson()
                ->post('/web/session/authenticate', [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'db' => $this->connection->database_name,
                        'login' => $this->connection->username,
                        // Uses api_key when set, else password. Required for Odoo
                        // users with 2FA (their password auth is disabled).
                        'password' => $this->connection->getAuthSecret(),
                    ],
                    'id' => uniqid(),
                ]);

            if (!$response->successful()) {
                throw OdooApiException::connectionFailed($this->connection->host);
            }

            $data = $response->json();

            if (isset($data['error'])) {
                throw OdooAuthException::invalidCredentials(
                    $this->connection->username,
                    $this->connection->database_name
                );
            }

            $result = $data['result'] ?? null;
            if (!$result || !isset($result['uid']) || $result['uid'] === false) {
                throw OdooAuthException::invalidCredentials(
                    $this->connection->username,
                    $this->connection->database_name
                );
            }

            $this->uid = (int) $result['uid'];

            // Extract session_id from cookies
            $cookies = $response->cookies();
            foreach ($cookies as $cookie) {
                if ($cookie->getName() === 'session_id') {
                    $this->sessionId = $cookie->getValue();
                    break;
                }
            }

            $this->connection->markConnected();

            return $this->uid;
        } catch (OdooAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw OdooApiException::connectionFailed($this->connection->host, $e);
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->uid !== null && $this->sessionId !== null;
    }

    protected function ensureAuthenticated(): void
    {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }
    }

    public function search(
        string $model,
        array $domain = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array {
        return $this->call($model, 'search', [
            'domain' => $domain,
            'offset' => $offset,
            'limit' => $limit,
            'order' => $order,
        ]);
    }

    public function read(string $model, array $ids, array $fields = []): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->call($model, 'read', [
            'ids' => $ids,
            'fields' => $fields ?: null,
        ]);
    }

    public function searchRead(
        string $model,
        array $domain = [],
        array $fields = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array {
        return $this->call($model, 'search_read', [
            'domain' => $domain,
            'fields' => $fields ?: null,
            'offset' => $offset,
            'limit' => $limit,
            'order' => $order,
        ]);
    }

    public function searchCount(string $model, array $domain = []): int
    {
        return (int) $this->call($model, 'search_count', [
            'domain' => $domain,
        ]);
    }

    public function create(string $model, array $values): int
    {
        return (int) $this->call($model, 'create', [
            'values' => $values,
        ]);
    }

    public function createBatch(string $model, array $valuesList): array
    {
        if (empty($valuesList)) {
            return [];
        }

        // REST API might support batch create
        $ids = [];
        foreach ($valuesList as $values) {
            $ids[] = $this->create($model, $values);
        }

        return $ids;
    }

    public function write(string $model, array $ids, array $values): bool
    {
        if (empty($ids)) {
            return true;
        }

        return (bool) $this->call($model, 'write', [
            'ids' => $ids,
            'values' => $values,
        ]);
    }

    public function writeBatch(string $model, array $updates): bool
    {
        if (empty($updates)) {
            return true;
        }

        foreach ($updates as $id => $values) {
            $this->write($model, [$id], $values);
        }

        return true;
    }

    public function unlink(string $model, array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        return (bool) $this->call($model, 'unlink', [
            'ids' => $ids,
        ]);
    }

    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        return $this->call($model, $method, array_merge($args, $kwargs));
    }

    public function fieldsGet(string $model, array $attributes = []): array
    {
        return $this->call($model, 'fields_get', [
            'attributes' => $attributes ?: null,
        ]);
    }

    public function getLastWriteDate(string $model, array $domain = []): ?string
    {
        $records = $this->searchRead(
            $model,
            $domain,
            ['write_date'],
            0,
            1,
            'write_date desc'
        );

        return $records[0]['write_date'] ?? null;
    }

    public function getModifiedSince(
        string $model,
        string $since,
        array $domain = [],
        array $fields = []
    ): array {
        $combinedDomain = array_merge(
            [['write_date', '>=', $since]],
            $domain
        );

        return $this->searchRead(
            $model,
            $combinedDomain,
            $fields,
            0,
            null,
            'write_date asc'
        );
    }

    protected function call(string $model, string $method, array $params = []): mixed
    {
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        try {
            $response = $this->http()->post('/web/dataset/call_kw', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'model' => $model,
                    'method' => $method,
                    'args' => [],
                    'kwargs' => array_filter($params, fn ($v) => $v !== null),
                ],
                'id' => uniqid(),
            ]);

            if (!$response->successful()) {
                throw OdooApiException::connectionFailed($this->connection->host);
            }

            $data = $response->json();

            if (isset($data['error'])) {
                $this->handleError($data['error']);
            }

            return $data['result'] ?? null;
        } catch (OdooApiException | OdooAuthException | OdooRateLimitException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw OdooApiException::connectionFailed($this->connection->host, $e);
        }
    }

    protected function handleError(array $error): void
    {
        $message = $error['data']['message'] ?? $error['message'] ?? 'Unknown error';
        $code = $error['code'] ?? 0;

        if (str_contains($message, 'Access Denied') || str_contains($message, 'access_denied')) {
            throw OdooAuthException::accessDenied('unknown', 'execute');
        }

        if ($code === 404 || str_contains($message, 'does not exist')) {
            throw OdooApiException::recordNotFound('unknown', 0);
        }

        throw new OdooApiException($message, $code);
    }

    protected function checkRateLimit(): void
    {
        $key = 'odoo_api_' . $this->connection->id;
        $limit = $this->connection->rate_limit_per_minute;

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw OdooRateLimitException::exceeded($retryAfter, $limit);
        }

        RateLimiter::hit($key, 60);
    }
}
