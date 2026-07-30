<?php

namespace Modules\OdooIntegration\Services\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Exceptions\OdooApiException;
use Modules\OdooIntegration\Exceptions\OdooAuthException;
use Modules\OdooIntegration\Exceptions\OdooRateLimitException;

class XmlRpcClient implements OdooApiClientInterface
{
    protected OdooConnection $connection;
    protected ?int $uid = null;
    protected string $commonUrl;
    protected string $objectUrl;

    public function init(OdooConnection $connection): self
    {
        $this->connection = $connection;
        $this->commonUrl = $connection->xml_rpc_url . '/common';
        $this->objectUrl = $connection->xml_rpc_url . '/object';

        return $this;
    }

    public function testConnection(): bool
    {
        try {
            $version = $this->xmlRpcCall($this->commonUrl, 'version', []);
            return isset($version['server_version']);
        } catch (\Exception $e) {
            Log::warning('Odoo connection test failed', [
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
            $uid = $this->xmlRpcCall($this->commonUrl, 'authenticate', [
                $this->connection->database_name,
                $this->connection->username,
                // Uses api_key when set, else password. Required for Odoo
                // users with 2FA (their password auth is disabled).
                $this->connection->getAuthSecret(),
                [],
            ]);

            if (!$uid || $uid === false) {
                throw OdooAuthException::invalidCredentials(
                    $this->connection->username,
                    $this->connection->database_name
                );
            }

            $this->uid = (int) $uid;
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
        return $this->uid !== null;
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
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        $kwargs = ['offset' => $offset];
        if ($limit !== null) {
            $kwargs['limit'] = $limit;
        }
        if ($order !== null) {
            $kwargs['order'] = $order;
        }

        return $this->executeKw($model, 'search', [$domain], $kwargs);
    }

    public function read(string $model, array $ids, array $fields = []): array
    {
        if (empty($ids)) {
            return [];
        }

        $this->ensureAuthenticated();
        $this->checkRateLimit();

        $kwargs = [];
        if (!empty($fields)) {
            $kwargs['fields'] = $fields;
        }

        return $this->executeKw($model, 'read', [$ids], $kwargs);
    }

    public function searchRead(
        string $model,
        array $domain = [],
        array $fields = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array {
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        $kwargs = ['offset' => $offset];
        if (!empty($fields)) {
            $kwargs['fields'] = $fields;
        }
        if ($limit !== null) {
            $kwargs['limit'] = $limit;
        }
        if ($order !== null) {
            $kwargs['order'] = $order;
        }

        return $this->executeKw($model, 'search_read', [$domain], $kwargs);
    }

    public function searchCount(string $model, array $domain = []): int
    {
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        return (int) $this->executeKw($model, 'search_count', [$domain], []);
    }

    public function create(string $model, array $values): int
    {
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        return (int) $this->executeKw($model, 'create', [$values], []);
    }

    public function createBatch(string $model, array $valuesList): array
    {
        if (empty($valuesList)) {
            return [];
        }

        $this->ensureAuthenticated();

        // Odoo's create method can accept a list of dicts for batch create
        $ids = [];
        foreach (array_chunk($valuesList, $this->connection->settings['batch_chunk_size'] ?? 50) as $chunk) {
            $this->checkRateLimit();
            foreach ($chunk as $values) {
                $ids[] = (int) $this->executeKw($model, 'create', [$values], []);
            }
        }

        return $ids;
    }

    public function write(string $model, array $ids, array $values): bool
    {
        if (empty($ids)) {
            return true;
        }

        $this->ensureAuthenticated();
        $this->checkRateLimit();

        return (bool) $this->executeKw($model, 'write', [$ids, $values], []);
    }

    public function writeBatch(string $model, array $updates): bool
    {
        if (empty($updates)) {
            return true;
        }

        $this->ensureAuthenticated();

        foreach ($updates as $id => $values) {
            $this->checkRateLimit();
            $this->executeKw($model, 'write', [[$id], $values], []);
        }

        return true;
    }

    public function unlink(string $model, array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $this->ensureAuthenticated();
        $this->checkRateLimit();

        return (bool) $this->executeKw($model, 'unlink', [$ids], []);
    }

    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        return $this->executeKw($model, $method, $args, $kwargs);
    }

    public function fieldsGet(string $model, array $attributes = []): array
    {
        $this->ensureAuthenticated();
        $this->checkRateLimit();

        $kwargs = [];
        if (!empty($attributes)) {
            $kwargs['attributes'] = $attributes;
        }

        return $this->executeKw($model, 'fields_get', [], $kwargs);
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

    protected function executeKw(string $model, string $method, array $args, array $kwargs): mixed
    {
        return $this->xmlRpcCall($this->objectUrl, 'execute_kw', [
            $this->connection->database_name,
            $this->uid,
            // Every execute_kw call also has to carry the credential —
            // pick the same source as authenticate() so a connection
            // authenticated with an API key doesn't silently downgrade
            // to password on the very next query.
            $this->connection->getAuthSecret(),
            $model,
            $method,
            $args,
            $kwargs,
        ]);
    }

    protected function xmlRpcCall(string $url, string $method, array $params): mixed
    {
        $request = $this->encodeXmlRpcRequest($method, $params);

        Log::debug('Odoo XML-RPC Request', [
            'url' => $url,
            'method' => $method,
            'connection_id' => $this->connection->id,
        ]);

        try {
            $response = Http::timeout($this->connection->timeout)
                ->withHeaders([
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($request, 'text/xml')
                ->post($url);
        } catch (\Exception $e) {
            Log::error('Odoo XML-RPC Connection Error', [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            throw OdooApiException::connectionFailed($this->connection->host, $e);
        }

        if (!$response->successful()) {
            Log::error('Odoo XML-RPC HTTP Error', [
                'url' => $url,
                'method' => $method,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            throw OdooApiException::connectionFailed(
                $this->connection->host,
                new \Exception('HTTP ' . $response->status() . ': ' . $response->body())
            );
        }

        $result = $this->decodeXmlRpcResponse($response->body());

        if (is_array($result) && isset($result['faultCode'])) {
            $this->handleFault($result);
        }

        return $result;
    }

    /**
     * Encode an XML-RPC request (pure PHP implementation).
     */
    protected function encodeXmlRpcRequest(string $method, array $params): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<methodCall>';
        $xml .= '<methodName>' . htmlspecialchars($method) . '</methodName>';
        $xml .= '<params>';

        foreach ($params as $param) {
            $xml .= '<param>' . $this->encodeValue($param) . '</param>';
        }

        $xml .= '</params>';
        $xml .= '</methodCall>';

        return $xml;
    }

    /**
     * Encode a PHP value to XML-RPC format.
     */
    protected function encodeValue(mixed $value): string
    {
        if (is_null($value)) {
            return '<value><nil/></value>';
        }

        if (is_bool($value)) {
            return '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>';
        }

        if (is_int($value)) {
            return '<value><int>' . $value . '</int></value>';
        }

        if (is_float($value)) {
            return '<value><double>' . $value . '</double></value>';
        }

        if (is_string($value)) {
            return '<value><string>' . htmlspecialchars($value, ENT_XML1, 'UTF-8') . '</string></value>';
        }

        if (is_array($value)) {
            // Check if it's an associative array (struct) or indexed array (array)
            if ($this->isAssociativeArray($value)) {
                return $this->encodeStruct($value);
            }
            return $this->encodeArray($value);
        }

        // Fallback to string
        return '<value><string>' . htmlspecialchars((string) $value, ENT_XML1, 'UTF-8') . '</string></value>';
    }

    /**
     * Check if array is associative.
     */
    protected function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Encode an indexed array.
     */
    protected function encodeArray(array $value): string
    {
        $xml = '<value><array><data>';
        foreach ($value as $item) {
            $xml .= $this->encodeValue($item);
        }
        $xml .= '</data></array></value>';
        return $xml;
    }

    /**
     * Encode an associative array as struct.
     */
    protected function encodeStruct(array $value): string
    {
        $xml = '<value><struct>';
        foreach ($value as $key => $val) {
            $xml .= '<member>';
            $xml .= '<name>' . htmlspecialchars((string) $key, ENT_XML1, 'UTF-8') . '</name>';
            $xml .= $this->encodeValue($val);
            $xml .= '</member>';
        }
        $xml .= '</struct></value>';
        return $xml;
    }

    /**
     * Decode an XML-RPC response (pure PHP implementation).
     */
    protected function decodeXmlRpcResponse(string $response): mixed
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new OdooApiException('Failed to parse XML-RPC response: ' . ($errors[0]->message ?? 'Unknown error'));
        }

        // Check for fault response
        if (isset($xml->fault)) {
            $fault = $this->decodeValue($xml->fault->value);
            return [
                'faultCode' => $fault['faultCode'] ?? 0,
                'faultString' => $fault['faultString'] ?? 'Unknown fault',
            ];
        }

        // Parse normal response
        if (isset($xml->params->param->value)) {
            return $this->decodeValue($xml->params->param->value);
        }

        return null;
    }

    /**
     * Decode an XML-RPC value node.
     */
    protected function decodeValue(\SimpleXMLElement $value): mixed
    {
        // Get the first child element to determine the type
        $children = $value->children();

        if (count($children) === 0) {
            // No type specified, treat as string
            return (string) $value;
        }

        $child = $children[0];
        $type = $child->getName();

        return match ($type) {
            'string' => (string) $child,
            'int', 'i4', 'i8' => (int) $child,
            'double' => (float) $child,
            'boolean' => ((string) $child === '1' || strtolower((string) $child) === 'true'),
            'nil' => null,
            'array' => $this->decodeArray($child),
            'struct' => $this->decodeStruct($child),
            'dateTime.iso8601' => (string) $child,
            'base64' => base64_decode((string) $child),
            default => (string) $child,
        };
    }

    /**
     * Decode an XML-RPC array.
     */
    protected function decodeArray(\SimpleXMLElement $array): array
    {
        $result = [];
        if (isset($array->data->value)) {
            foreach ($array->data->value as $value) {
                $result[] = $this->decodeValue($value);
            }
        }
        return $result;
    }

    /**
     * Decode an XML-RPC struct.
     */
    protected function decodeStruct(\SimpleXMLElement $struct): array
    {
        $result = [];
        if (isset($struct->member)) {
            foreach ($struct->member as $member) {
                $name = (string) $member->name;
                $result[$name] = $this->decodeValue($member->value);
            }
        }
        return $result;
    }

    protected function handleFault(array $fault): void
    {
        $code = $fault['faultCode'] ?? 0;
        $message = $fault['faultString'] ?? 'Unknown error';

        // Check for common error types
        if (str_contains($message, 'Access Denied') || str_contains($message, 'access_denied')) {
            throw OdooAuthException::accessDenied('unknown', 'execute');
        }

        if (str_contains($message, 'does not exist')) {
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
