<?php

namespace Tests\Odoo\Support;

use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Services\Api\OdooApiClientInterface;

/**
 * In-memory stand-in for an Odoo server.
 *
 * Stores records per model, answers search/read/create/write like Odoo's
 * external API (including [id, "Name"] many2one tuples when seeded that way,
 * and `false` for empty fields), records every call for assertions, and can
 * be told to fail specific calls (rate limits, network errors) or to emulate
 * Odoo's "cannot marshal None" fault on workflow action methods.
 */
class FakeOdooClient implements OdooApiClientInterface
{
    /** @var array<string, array<int, array>> model => id => record */
    public array $records = [];

    /** @var array<int, array{method: string, model: string, args: array}> */
    public array $calls = [];

    /** @var array<int, array{model: string, method: string, ids: array}> workflow actions run via execute() */
    public array $actions = [];

    /** @var array<int, array{model: ?string, method: ?string, exception: \Throwable}> */
    protected array $failures = [];

    /** Emulate Odoo's RPC marshal fault on successful action_* methods. */
    public bool $marshalNoneOnActions = false;

    protected array $nextId = [];

    protected bool $authenticated = false;

    protected ?OdooConnection $connection = null;

    public int $uid = 2;

    // ------------------------------------------------------------------
    // Test helpers
    // ------------------------------------------------------------------

    /**
     * Seed a record. Assigns an id when not given. Returns the id.
     */
    public function seed(string $model, array $record): int
    {
        $id = $record['id'] ?? $this->nextIdFor($model);
        $record['id'] = $id;
        $record += [
            'create_date' => now('UTC')->format('Y-m-d H:i:s'),
            'write_date' => now('UTC')->format('Y-m-d H:i:s'),
        ];

        $this->records[$model][$id] = $record;
        $this->nextId[$model] = max($this->nextId[$model] ?? 0, $id) + 1;

        return $id;
    }

    /**
     * Queue an exception for the next matching call.
     * Pass '*' (or null) to match any model/method.
     */
    public function failNext(\Throwable $exception, ?string $model = null, ?string $method = null): void
    {
        $this->failures[] = ['model' => $model, 'method' => $method, 'exception' => $exception];
    }

    /** All calls of a given client method (create, write, searchRead, ...). */
    public function callsTo(string $method, ?string $model = null): array
    {
        return array_values(array_filter(
            $this->calls,
            fn (array $c) => $c['method'] === $method && ($model === null || $c['model'] === $model)
        ));
    }

    /** The domain used by the most recent search/searchRead/searchCount on a model. */
    public function lastDomain(string $model): ?array
    {
        foreach (array_reverse($this->calls) as $call) {
            if ($call['model'] === $model && in_array($call['method'], ['search', 'searchRead', 'searchCount'], true)) {
                return $call['args']['domain'] ?? null;
            }
        }

        return null;
    }

    public function find(string $model, int $id): ?array
    {
        return $this->records[$model][$id] ?? null;
    }

    protected function nextIdFor(string $model): int
    {
        return $this->nextId[$model] ?? ($this->nextId[$model] = 1000);
    }

    protected function log(string $method, string $model, array $args = []): void
    {
        $this->calls[] = ['method' => $method, 'model' => $model, 'args' => $args];
    }

    protected function maybeFail(string $model, string $method): void
    {
        foreach ($this->failures as $i => $failure) {
            $modelMatches = $failure['model'] === null || $failure['model'] === '*' || $failure['model'] === $model;
            $methodMatches = $failure['method'] === null || $failure['method'] === '*' || $failure['method'] === $method;

            if ($modelMatches && $methodMatches) {
                unset($this->failures[$i]);
                throw $failure['exception'];
            }
        }
    }

    // ------------------------------------------------------------------
    // Domain evaluation
    // ------------------------------------------------------------------

    protected function matchesDomain(array $record, array $domain): bool
    {
        foreach ($domain as $leaf) {
            // Prefix operators ('&', '|', '!') are treated as AND — the sync
            // engine only ever emits implicit-AND positional leaves.
            if (! is_array($leaf)) {
                continue;
            }

            [$field, $operator, $expected] = array_pad(array_values($leaf), 3, null);

            $actual = $record[$field] ?? null;

            // many2one tuples compare by id
            if (is_array($actual) && count($actual) === 2 && is_int($actual[0] ?? null)) {
                $actual = $actual[0];
            }

            $ok = match ($operator) {
                '=' => $actual == $expected,
                '!=' => $actual != $expected,
                '>' => $actual > $expected,
                '>=' => $actual >= $expected,
                '<' => $actual < $expected,
                '<=' => $actual <= $expected,
                'in' => in_array($actual, (array) $expected),
                'not in' => ! in_array($actual, (array) $expected),
                'like', 'ilike' => str_contains(
                    strtolower((string) $actual),
                    strtolower(trim((string) $expected, '%'))
                ),
                default => true,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    protected function matching(string $model, array $domain, ?string $order = null): array
    {
        $records = array_values(array_filter(
            $this->records[$model] ?? [],
            fn (array $r) => $this->matchesDomain($r, $domain)
        ));

        [$field, $dir] = array_pad(explode(' ', trim($order ?: 'id asc')), 2, 'asc');
        usort($records, function (array $a, array $b) use ($field, $dir) {
            $cmp = ($a[$field] ?? null) <=> ($b[$field] ?? null);

            return strtolower($dir) === 'desc' ? -$cmp : $cmp;
        });

        return $records;
    }

    protected function selectFields(array $record, array $fields): array
    {
        if ($fields === []) {
            return $record;
        }

        $out = ['id' => $record['id']];
        foreach ($fields as $field) {
            // Odoo returns false for empty/missing fields
            $out[$field] = $record[$field] ?? false;
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // OdooApiClientInterface
    // ------------------------------------------------------------------

    public function init(OdooConnection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function authenticate(): int
    {
        $this->maybeFail('*', 'authenticate');
        $this->authenticated = true;

        return $this->uid;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function search(
        string $model,
        array $domain = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array {
        $this->log('search', $model, compact('domain', 'offset', 'limit', 'order'));
        $this->maybeFail($model, 'search');

        $ids = array_column($this->matching($model, $domain, $order), 'id');

        return array_slice($ids, $offset, $limit);
    }

    public function read(string $model, array $ids, array $fields = []): array
    {
        $this->log('read', $model, compact('ids', 'fields'));
        $this->maybeFail($model, 'read');

        $out = [];
        foreach ($ids as $id) {
            if (isset($this->records[$model][$id])) {
                $out[] = $this->selectFields($this->records[$model][$id], $fields);
            }
        }

        return $out;
    }

    public function searchRead(
        string $model,
        array $domain = [],
        array $fields = [],
        int $offset = 0,
        ?int $limit = null,
        ?string $order = null
    ): array {
        $this->log('searchRead', $model, compact('domain', 'fields', 'offset', 'limit', 'order'));
        $this->maybeFail($model, 'searchRead');

        $records = array_slice($this->matching($model, $domain, $order), $offset, $limit);

        return array_map(fn (array $r) => $this->selectFields($r, $fields), $records);
    }

    public function searchCount(string $model, array $domain = []): int
    {
        $this->log('searchCount', $model, compact('domain'));
        $this->maybeFail($model, 'searchCount');

        return count($this->matching($model, $domain));
    }

    /**
     * Server-side defaults applied on create, mirroring real Odoo behavior
     * (e.g. hr.leave enters the workflow in 'confirm' after create).
     */
    protected array $createDefaults = [
        'hr.leave' => ['state' => 'confirm'],
        'hr.leave.allocation' => ['state' => 'confirm'],
        'hr.payslip' => ['state' => 'draft'],
    ];

    public function create(string $model, array $values): int
    {
        $this->log('create', $model, compact('values'));
        $this->maybeFail($model, 'create');

        $id = $this->nextIdFor($model);
        $this->nextId[$model] = $id + 1;

        $this->records[$model][$id] = $values + ($this->createDefaults[$model] ?? []) + [
            'id' => $id,
            'create_date' => now('UTC')->format('Y-m-d H:i:s'),
            'write_date' => now('UTC')->format('Y-m-d H:i:s'),
        ];
        $this->applyHrLeaveComputes($model, $id);

        return $id;
    }

    /**
     * Emulate Odoo 16+'s hr.leave compute: date_from/date_to are DERIVED
     * from request_date_from/request_date_to (the fields clients must send).
     */
    protected function applyHrLeaveComputes(string $model, int $id): void
    {
        if ($model !== 'hr.leave') {
            return;
        }

        $record = &$this->records[$model][$id];

        if (! empty($record['request_date_from'])) {
            $record['date_from'] = $record['request_date_from'].' 06:00:00';
            $record['date_to'] = ($record['request_date_to'] ?? $record['request_date_from']).' 14:00:00';
        }
    }

    public function createBatch(string $model, array $valuesList): array
    {
        return array_map(fn (array $values) => $this->create($model, $values), $valuesList);
    }

    public function write(string $model, array $ids, array $values): bool
    {
        $this->log('write', $model, compact('ids', 'values'));
        $this->maybeFail($model, 'write');

        foreach ($ids as $id) {
            if (! isset($this->records[$model][$id])) {
                return false;
            }

            $this->records[$model][$id] = array_merge($this->records[$model][$id], $values, [
                'write_date' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
            $this->applyHrLeaveComputes($model, $id);
        }

        return true;
    }

    public function writeBatch(string $model, array $updates): bool
    {
        foreach ($updates as $id => $values) {
            $this->write($model, [$id], $values);
        }

        return true;
    }

    public function unlink(string $model, array $ids): bool
    {
        $this->log('unlink', $model, compact('ids'));
        $this->maybeFail($model, 'unlink');

        foreach ($ids as $id) {
            unset($this->records[$model][$id]);
        }

        return true;
    }

    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $this->log('execute', $model, compact('method', 'args', 'kwargs'));
        $this->maybeFail($model, $method);

        $ids = (array) ($args[0] ?? []);
        $this->actions[] = ['model' => $model, 'method' => $method, 'ids' => $ids];

        // Mimic hr.leave workflow transitions
        $state = match ($method) {
            'action_approve', 'action_validate' => 'validate',
            'action_refuse' => 'refuse',
            'action_draft' => 'draft',
            default => null,
        };

        if ($state !== null) {
            foreach ($ids as $id) {
                if (isset($this->records[$model][$id])) {
                    $this->records[$model][$id]['state'] = $state;
                    $this->records[$model][$id]['write_date'] = now('UTC')->format('Y-m-d H:i:s');
                }
            }

            if ($this->marshalNoneOnActions) {
                // Real Odoo applies the transition server-side, then its RPC
                // layer fails to marshal the None return value.
                throw new \RuntimeException('cannot marshal None unless allow_none is enabled');
            }
        }

        return true;
    }

    public function fieldsGet(string $model, array $attributes = []): array
    {
        return [];
    }

    public function getLastWriteDate(string $model, array $domain = []): ?string
    {
        $dates = array_column($this->matching($model, $domain), 'write_date');

        return $dates === [] ? null : max($dates);
    }

    public function getModifiedSince(
        string $model,
        string $since,
        array $domain = [],
        array $fields = []
    ): array {
        $records = array_filter(
            $this->matching($model, $domain),
            fn (array $r) => ($r['write_date'] ?? '') > $since
        );

        return array_map(fn (array $r) => $this->selectFields($r, $fields), array_values($records));
    }
}
