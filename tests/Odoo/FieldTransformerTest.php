<?php

use Modules\Auth\Models\User;
use Modules\OdooIntegration\Exceptions\MissingDependencyException;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Services\Transform\FieldTransformer;
use Modules\OdooIntegration\Services\Transform\RelationResolver;
use Modules\OdooIntegration\Services\Transform\TimezoneConverter;
use Tests\Odoo\Support\OdooScenario;

/**
 * FieldTransformer: every transform type, both directions.
 */
function transformerMapping(array $fields): OdooEntityMapping
{
    $connection = OdooScenario::connection(['code' => 'transformer-'.uniqid()]);

    return OdooScenario::mapping($connection, [
        'name' => 'Transformer Fixture',
        'local_model' => User::class,
        'local_table' => 'users',
        'odoo_model' => 'res.partner',
        'sync_direction' => 'bidirectional',
    ], $fields);
}

function importValue(array $field, array $odooData): array
{
    return app(FieldTransformer::class)->transformImport(transformerMapping([$field]), $odooData);
}

function exportValue(array $field, array $localData): array
{
    return app(FieldTransformer::class)->transformExport(transformerMapping([$field]), $localData);
}

test('direct transform converts odoo false and empty string to null', function () {
    $out = importValue(['local' => 'phone', 'odoo' => 'phone'], ['phone' => false]);
    expect($out['phone'])->toBeNull();

    $out = importValue(['local' => 'phone', 'odoo' => 'phone'], ['phone' => '']);
    expect($out['phone'])->toBeNull();

    $out = importValue(['local' => 'phone', 'odoo' => 'phone'], ['phone' => '0100']);
    expect($out['phone'])->toBe('0100');
});

test('many2one tuples resolve to their id before transformation', function () {
    $user = OdooScenario::localUser(['odoo_id' => 77]);

    $out = importValue(
        ['local' => 'manager_id', 'odoo' => 'manager_id', 'transform' => 'relation', 'config' => ['model' => User::class]],
        ['manager_id' => [77, 'Some Manager']]
    );

    expect($out['manager_id'])->toBe($user->id);
});

test('relation import throws a dependency exception for unknown related records', function () {
    importValue(
        ['local' => 'manager_id', 'odoo' => 'manager_id', 'transform' => 'relation', 'config' => ['model' => User::class]],
        ['manager_id' => [424242, 'Ghost']]
    );
})->throws(MissingDependencyException::class);

test('relation import tolerates unknown records when skip_on_missing is false', function () {
    $out = importValue(
        ['local' => 'manager_id', 'odoo' => 'manager_id', 'transform' => 'relation', 'config' => ['model' => User::class, 'skip_on_missing' => false]],
        ['manager_id' => [424242, 'Ghost']]
    );

    expect($out['manager_id'])->toBeNull();
});

test('relation export maps the local id to the odoo id', function () {
    $user = OdooScenario::localUser(['odoo_id' => 88]);

    $out = exportValue(
        ['local' => 'manager_id', 'odoo' => 'manager_id', 'transform' => 'relation', 'config' => ['model' => User::class]],
        ['manager_id' => $user->id]
    );

    expect($out['manager_id'])->toBe(88);
});

test('boolean transform casts odoo booleans both ways', function () {
    expect(importValue(['local' => 'is_active', 'odoo' => 'active', 'transform' => 'boolean'], ['active' => false]))
        ->toMatchArray(['is_active' => false]);

    expect(exportValue(['local' => 'is_active', 'odoo' => 'active', 'transform' => 'boolean'], ['is_active' => 1]))
        ->toMatchArray(['active' => true]);
});

test('date transform formats DateTime instances on export', function () {
    $out = exportValue(
        ['local' => 'hire_date', 'odoo' => 'date_start', 'transform' => 'date'],
        ['hire_date' => new DateTime('2026-05-01 13:45:00')]
    );

    expect($out['date_start'])->toBe('2026-05-01');
});

test('datetime transform converts between odoo utc and the app timezone', function () {
    $localTz = config('app.timezone');

    $imported = importValue(
        ['local' => 'starts_at', 'odoo' => 'date_begin', 'transform' => 'datetime'],
        ['date_begin' => '2026-08-03 06:00:00']
    );
    $expected = app(TimezoneConverter::class)->convert('2026-08-03 06:00:00', 'UTC', $localTz);
    expect($imported['starts_at'])->toBe($expected);

    $exported = exportValue(
        ['local' => 'starts_at', 'odoo' => 'date_begin', 'transform' => 'datetime'],
        ['starts_at' => $expected]
    );
    expect($exported['date_begin'])->toBe('2026-08-03 06:00:00');
});

test('money transform converts between decimal and minor units', function () {
    expect(importValue(['local' => 'salary_minor', 'odoo' => 'wage', 'transform' => 'money'], ['wage' => 5000.75]))
        ->toMatchArray(['salary_minor' => 500075]);

    expect(exportValue(['local' => 'salary_minor', 'odoo' => 'wage', 'transform' => 'money'], ['salary_minor' => 500075]))
        ->toMatchArray(['wage' => 5000.75]);
});

test('enum transform maps values and falls back to the default', function () {
    $field = ['local' => 'status', 'odoo' => 'state', 'transform' => 'enum',
        'config' => ['mapping' => ['done' => 'approved'], 'default' => 'draft']];

    expect(importValue($field, ['state' => 'done']))->toMatchArray(['status' => 'approved']);
    expect(importValue($field, ['state' => 'unknown-state']))->toMatchArray(['status' => 'draft']);

    // Export uses the reverse mapping
    expect(exportValue($field, ['status' => 'approved']))->toMatchArray(['state' => 'done']);
});

test('translatable transform wraps odoo strings and unwraps local json', function () {
    expect(importValue(['local' => 'name', 'odoo' => 'name', 'transform' => 'translatable'], ['name' => 'Annual Leave']))
        ->toMatchArray(['name' => ['en' => 'Annual Leave']]);

    expect(exportValue(['local' => 'name', 'odoo' => 'name', 'transform' => 'translatable'], ['name' => ['en' => 'Annual Leave', 'ar' => 'إجازة سنوية']]))
        ->toMatchArray(['name' => 'Annual Leave']);
});

test('split_name extracts first and last name parts', function () {
    expect(importValue(
        ['local' => 'first_name', 'odoo' => 'name', 'transform' => 'split_name', 'config' => ['part' => 'first']],
        ['name' => 'Omar Khaled Hassan']
    ))->toMatchArray(['first_name' => 'Omar']);

    expect(importValue(
        ['local' => 'last_name', 'odoo' => 'name', 'transform' => 'split_name', 'config' => ['part' => 'last']],
        ['name' => 'Omar Khaled Hassan']
    ))->toMatchArray(['last_name' => 'Khaled Hassan']);

    // Single-word names have no last part
    expect(importValue(
        ['local' => 'last_name', 'odoo' => 'name', 'transform' => 'split_name', 'config' => ['part' => 'last']],
        ['name' => 'Cher']
    ))->toMatchArray(['last_name' => null]);
});

test('many2many transform emits the odoo replace command on export', function () {
    expect(exportValue(
        ['local' => 'tag_ids', 'odoo' => 'category_id', 'transform' => 'many2many'],
        ['tag_ids' => [4, 9]]
    ))->toMatchArray(['category_id' => [[6, 0, [4, 9]]]]);
});

test('year_from_date extracts the year on import and rebuilds a date on export', function () {
    expect(importValue(['local' => 'start_year', 'odoo' => 'date_start', 'transform' => 'year_from_date'], ['date_start' => '2021-11-01']))
        ->toMatchArray(['start_year' => 2021]);

    expect(exportValue(['local' => 'start_year', 'odoo' => 'date_start', 'transform' => 'year_from_date'], ['start_year' => 2021]))
        ->toMatchArray(['date_start' => '2021-01-01']);
});

test('json transform decodes strings on import and encodes arrays on export', function () {
    expect(importValue(['local' => 'meta', 'odoo' => 'meta', 'transform' => 'json'], ['meta' => '{"a":1}']))
        ->toMatchArray(['meta' => ['a' => 1]]);

    expect(exportValue(['local' => 'meta', 'odoo' => 'meta', 'transform' => 'json'], ['meta' => ['a' => 1]]))
        ->toMatchArray(['meta' => '{"a":1}']);
});

test('default-only field mappings inject their default on import and are skipped on export', function () {
    $field = ['local' => 'password', 'odoo' => null, 'default' => 'secret-default'];

    expect(importValue($field, []))->toMatchArray(['password' => 'secret-default']);
    expect(exportValue($field, ['password' => 'anything']))->toBe([]);
});

/**
 * RelationResolver caching behavior.
 */
test('relation resolver caches lookups within a request', function () {
    $resolver = app(RelationResolver::class);
    $user = OdooScenario::localUser(['odoo_id' => 505]);

    $connectionId = 1;
    expect($resolver->resolveToLocalId(505, User::class, 'res.users', $connectionId))->toBe($user->id);

    // Delete the row — the cached mapping must still answer
    User::where('id', $user->id)->forceDelete();
    expect($resolver->resolveToLocalId(505, User::class, 'res.users', $connectionId))->toBe($user->id);

    $resolver->clearCache();
    expect($resolver->resolveToLocalId(505, User::class, 'res.users', $connectionId))->toBeNull();
});

test('relation resolver bulk-resolves odoo ids', function () {
    $resolver = app(RelationResolver::class);
    $a = OdooScenario::localUser(['odoo_id' => 601]);
    $b = OdooScenario::localUser(['odoo_id' => 602]);

    $map = $resolver->bulkResolveToLocalIds([601, 602, 999], User::class, 'res.users', 1);

    expect($map[601])->toBe($a->id)
        ->and($map[602])->toBe($b->id)
        ->and($map[999])->toBeNull();
});
