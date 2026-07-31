<?php

use Modules\Auth\Models\User;
use Modules\OdooIntegration\Enums\SyncStatus;
use Tests\Odoo\Support\OdooScenario;

/**
 * Import sync: Odoo res.users → local users.
 */
test('imports new odoo users as local users', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $this->odoo->seed('res.users', ['name' => 'Sara Ahmed', 'login' => 'sara@clinic.test', 'active' => true]);
    $this->odoo->seed('res.users', ['name' => 'Omar Khaled Hassan', 'login' => 'omar@clinic.test', 'active' => true]);

    $log = runOdooSync($mapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_processed)->toBe(2)
        ->and($log->records_created)->toBe(2)
        ->and($log->records_failed)->toBe(0);

    $sara = User::where('email', 'sara@clinic.test')->first();
    expect($sara)->not->toBeNull()
        ->and($sara->first_name)->toBe('Sara')
        ->and($sara->last_name)->toBe('Ahmed')
        ->and($sara->username)->toBe('sara@clinic.test')
        ->and($sara->odoo_id)->not->toBeNull()
        ->and($sara->odoo_synced_at)->not->toBeNull()
        ->and($sara->password)->not->toBeNull();

    // split_name keeps everything after the first space as last name
    $omar = User::where('email', 'omar@clinic.test')->first();
    expect($omar->first_name)->toBe('Omar')
        ->and($omar->last_name)->toBe('Khaled Hassan');
});

test('normalizes legacy filter_conditions into a positional odoo domain', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $this->odoo->seed('res.users', ['name' => 'Active One', 'login' => 'active@clinic.test', 'active' => true]);
    $this->odoo->seed('res.users', ['name' => 'Archived One', 'login' => 'archived@clinic.test', 'active' => false]);

    runOdooSync($mapping);

    // The stored legacy leaf {"0","1","2",field,operator,value} must reach
    // the API as a clean positional tuple.
    expect($this->odoo->lastDomain('res.users'))->toBe([['active', '=', true]]);

    expect(User::where('email', 'archived@clinic.test')->exists())->toBeFalse()
        ->and(User::where('email', 'active@clinic.test')->exists())->toBeTrue();
});

test('delta sync only imports users that do not exist locally yet', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $this->odoo->seed('res.users', ['name' => 'First User', 'login' => 'first@clinic.test', 'active' => true]);
    runOdooSync($mapping);

    $this->odoo->seed('res.users', ['name' => 'Second User', 'login' => 'second@clinic.test', 'active' => true]);
    $log = runOdooSync($mapping, 'delta');

    expect($log->records_created)->toBe(1)
        ->and($log->records_skipped)->toBe(1)
        ->and(User::whereNotNull('odoo_id')->count())->toBe(2);
});

test('full sync overrides local changes with odoo data', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $odooId = $this->odoo->seed('res.users', ['name' => 'Sara Ahmed', 'login' => 'sara@clinic.test', 'active' => true]);
    runOdooSync($mapping);

    $user = User::where('odoo_id', $odooId)->first();
    $user->update(['first_name' => 'Renamed', 'last_name' => 'Locally']);

    $log = runOdooSync($mapping);

    expect($log->records_updated)->toBe(1);
    $user->refresh();
    expect($user->first_name)->toBe('Sara')
        ->and($user->last_name)->toBe('Ahmed');
});

test('links an existing local user by email key instead of duplicating', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    $local = OdooScenario::localUser(['email' => 'sara@clinic.test']);
    expect($local->odoo_id)->toBeNull();

    $odooId = $this->odoo->seed('res.users', ['name' => 'Sara Ahmed', 'login' => 'sara@clinic.test', 'active' => true]);
    runOdooSync($mapping);

    expect(User::where('email', 'sara@clinic.test')->count())->toBe(1);

    $local->refresh();
    expect($local->odoo_id)->toBe($odooId)
        ->and($local->odoo_synced_at)->not->toBeNull();
});

test('records a failure without aborting the batch when a record cannot be saved', function () {
    $connection = OdooScenario::connection();
    $mapping = OdooScenario::users($connection);

    // login=null → email NOT NULL violation for this record only
    $this->odoo->seed('res.users', ['name' => 'Broken User', 'login' => null, 'active' => true]);
    $this->odoo->seed('res.users', ['name' => 'Good User', 'login' => 'good@clinic.test', 'active' => true]);

    $log = runOdooSync($mapping);

    expect($log->status)->toBe(SyncStatus::COMPLETED)
        ->and($log->records_failed)->toBe(1)
        ->and($log->records_created)->toBe(1)
        ->and($log->errors)->not->toBeEmpty();

    expect(User::where('email', 'good@clinic.test')->exists())->toBeTrue();
});
