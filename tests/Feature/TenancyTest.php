<?php

test('tenant can be created and initialized', function () {
    $tenant = createTenant([
        'name' => 'Test Clinic',
        'slug' => 'test-clinic',
    ]);

    expect($tenant)->toBeInstanceOf(\Modules\Core\Models\Tenant::class)
        ->and($tenant->name)->toBe('Test Clinic')
        ->and($tenant->slug)->toBe('test-clinic');

    tenancy()->initialize($tenant);

    expect(tenant())->toBe($tenant)
        ->and(tenancy()->initialized)->toBeTrue();
});

test('tenant context is isolated', function () {
    $tenant1 = createTenant(['name' => 'Clinic 1']);
    $tenant2 = createTenant(['name' => 'Clinic 2']);

    tenancy()->initialize($tenant1);
    expect(tenant()->name)->toBe('Clinic 1');

    tenancy()->initialize($tenant2);
    expect(tenant()->name)->toBe('Clinic 2');

    tenancy()->end();
    expect(tenancy()->initialized)->toBeFalse();
});