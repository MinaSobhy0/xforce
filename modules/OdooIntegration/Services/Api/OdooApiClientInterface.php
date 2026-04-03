<?php

namespace Modules\OdooIntegration\Services\Api;

use Modules\OdooIntegration\Models\OdooConnection;

interface OdooApiClientInterface
{
    /**
     * Initialize the client with connection settings.
     */
    public function init(OdooConnection $connection): self;

    /**
     * Test the connection to the Odoo server.
     */
    public function testConnection(): bool;

    /**
     * Authenticate with the Odoo server.
     *
     * @return int The user ID (uid) on successful authentication
     */
    public function authenticate(): int;

    /**
     * Check if the client is authenticated.
     */
    public function isAuthenticated(): bool;

    /**
     * Search for records in Odoo.
     *
     * @param string $model The Odoo model name
     * @param array $domain The search domain (filters)
     * @param int $offset Pagination offset
     * @param int|null $limit Maximum records to return
     * @param string|null $order Sort order
     * @return array Array of record IDs
     */
    public function search(
        string $model,
        array $domain = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array;

    /**
     * Read records from Odoo.
     *
     * @param string $model The Odoo model name
     * @param array $ids Record IDs to read
     * @param array $fields Fields to retrieve (empty = all)
     * @return array Array of records
     */
    public function read(string $model, array $ids, array $fields = []): array;

    /**
     * Search and read records in a single call.
     *
     * @param string $model The Odoo model name
     * @param array $domain The search domain
     * @param array $fields Fields to retrieve
     * @param int $offset Pagination offset
     * @param int|null $limit Maximum records
     * @param string|null $order Sort order
     * @return array Array of records
     */
    public function searchRead(
        string $model,
        array $domain = [],
        array $fields = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array;

    /**
     * Count records matching the domain.
     *
     * @param string $model The Odoo model name
     * @param array $domain The search domain
     * @return int Record count
     */
    public function searchCount(string $model, array $domain = []): int;

    /**
     * Create a new record in Odoo.
     *
     * @param string $model The Odoo model name
     * @param array $values Field values
     * @return int The created record ID
     */
    public function create(string $model, array $values): int;

    /**
     * Create multiple records in a batch.
     *
     * @param string $model The Odoo model name
     * @param array $valuesList Array of field values for each record
     * @return array Array of created record IDs
     */
    public function createBatch(string $model, array $valuesList): array;

    /**
     * Update existing records in Odoo.
     *
     * @param string $model The Odoo model name
     * @param array $ids Record IDs to update
     * @param array $values Field values to update
     * @return bool Success status
     */
    public function write(string $model, array $ids, array $values): bool;

    /**
     * Update multiple records with different values.
     *
     * @param string $model The Odoo model name
     * @param array $updates Array of [id => values]
     * @return bool Success status
     */
    public function writeBatch(string $model, array $updates): bool;

    /**
     * Delete records from Odoo.
     *
     * @param string $model The Odoo model name
     * @param array $ids Record IDs to delete
     * @return bool Success status
     */
    public function unlink(string $model, array $ids): bool;

    /**
     * Execute a method on a model.
     *
     * @param string $model The Odoo model name
     * @param string $method The method name
     * @param array $args Positional arguments
     * @param array $kwargs Keyword arguments
     * @return mixed Method return value
     */
    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed;

    /**
     * Get field definitions for a model.
     *
     * @param string $model The Odoo model name
     * @param array $attributes Attributes to retrieve
     * @return array Field definitions
     */
    public function fieldsGet(string $model, array $attributes = []): array;

    /**
     * Get the last modification date for records.
     *
     * @param string $model The Odoo model name
     * @param array $domain The search domain
     * @return string|null ISO datetime of last modification
     */
    public function getLastWriteDate(string $model, array $domain = []): ?string;

    /**
     * Get records modified after a certain date.
     *
     * @param string $model The Odoo model name
     * @param string $since ISO datetime
     * @param array $domain Additional filters
     * @param array $fields Fields to retrieve
     * @return array Records
     */
    public function getModifiedSince(
        string $model,
        string $since,
        array $domain = [],
        array $fields = []
    ): array;
}
