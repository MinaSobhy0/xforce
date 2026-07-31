<?php

/**
 * Cross-module smoke tests: auth boundaries, tenant header handling, and
 * config surface.
 */
test('health endpoint responds without auth', function () {
    $this->getJson('/api/v2/health')->assertOk()->assertJsonPath('status', 'healthy');
});

test('protected endpoints require the tenant header', function () {
    $this->getJson('/api/v2/time-off/balance')->assertStatus(400);
});

test('protected endpoints require authentication', function () {
    $this->getJson('/api/v2/time-off/balance', ['X-Tenant-Slug' => 'odootest'])
        ->assertStatus(401);
});

test('an unknown tenant slug is rejected', function () {
    $this->getJson('/api/v2/time-off/balance', ['X-Tenant-Slug' => 'nope'])
        ->assertStatus(404);
});

test('config exposes the payslip display flags the payslip endpoints obey', function () {
    [$user] = $this->createStaffUser();

    $data = $this->api('GET', 'config', [], $user)->assertOk()->json('data');

    expect($data)->toHaveKey('payslip_display')
        ->and($data['payslip_display'])->toHaveKeys([
            'show_gross_salary',
            'show_allowances_breakdown',
            'show_deductions_breakdown',
            'show_rule_breakdown',
        ]);
});
