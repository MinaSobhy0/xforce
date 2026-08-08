<?php

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Auth-surface security properties:
 *   - tokens are bound to the tenant that issued them
 *   - password guessing is bounded by the per-account lockout
 *
 * Both were unenforced: personal_access_tokens is a shared public-schema
 * table while `users` is per-tenant-schema, and the mobile login did a
 * manual Hash::check that never touched failed_login_attempts.
 *
 * The lockout tests drop ThrottleRequests deliberately. The login route is
 * also throttled at 5/min per IP, which would produce a 429 of its own and
 * let these tests pass without the lockout existing at all — the throttle
 * is separately defeated by rotating X-Forwarded-For, which is why the
 * per-account lockout has to stand on its own.
 */

// ----------------------------------------------------------------------
//  Tenant binding
// ----------------------------------------------------------------------

test('a token stamped with a different tenant is rejected', function () {
    [$user] = $this->createStaffUser();

    $newToken = $user->createToken('cross-tenant-probe');
    // Any tenant id that is not the one this request resolves to. The
    // production shape of this attack is a real token from clinic A
    // replayed with clinic B's slug — same comparison, same rejection.
    $newToken->accessToken->forceFill(['tenant_id' => $this->tenant->id + 999])->save();

    $this->apiWithRawToken('GET', 'auth/me', $newToken->plainTextToken)
        ->assertStatus(401)
        ->assertJsonPath('error_code', 'TOKEN_TENANT_MISMATCH');
});

test('a token with no tenant stamp is rejected', function () {
    [$user] = $this->createStaffUser();

    // Exactly the shape of every token issued before the tenant binding
    // existed — it cannot be attributed to a tenant, so it is not trusted.
    $plain = $user->createToken('legacy-unstamped')->plainTextToken;

    $this->apiWithRawToken('GET', 'auth/me', $plain)
        ->assertStatus(401)
        ->assertJsonPath('error_code', 'TOKEN_TENANT_MISMATCH');
});

test('a correctly stamped token still works', function () {
    [$user] = $this->createStaffUser();

    $this->api('GET', 'auth/me', [], $user)->assertOk();
});

test('a login issues a token stamped with the current tenant', function () {
    [$user] = $this->createStaffUser();

    $token = $this->api('POST', 'auth/staff/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk()->json('data.token');

    $id = explode('|', $token)[0];

    expect((int) \App\Models\PersonalAccessToken::findOrFail($id)->tenant_id)
        ->toBe((int) $this->tenant->id);
});

// ----------------------------------------------------------------------
//  Password guessing
// ----------------------------------------------------------------------

test('repeated wrong passwords lock the account', function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    [$user] = $this->createStaffUser();

    $max = (int) config('security.login.max_attempts', 5);

    for ($i = 0; $i < $max; $i++) {
        $this->api('POST', 'auth/staff/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertStatus(401);
    }

    expect($user->fresh()->isLocked())->toBeTrue();

    // The correct password must not get through while locked, otherwise the
    // lockout only delays the attacker rather than stopping them.
    $this->api('POST', 'auth/staff/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertStatus(429);
});

test('a successful login clears the failed-attempt counter', function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    [$user] = $this->createStaffUser();

    $this->api('POST', 'auth/staff/login', [
        'email' => $user->email,
        'password' => 'wrong-once',
    ])->assertStatus(401);

    expect((int) $user->fresh()->failed_login_attempts)->toBe(1);

    $this->api('POST', 'auth/staff/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk();

    expect((int) $user->fresh()->failed_login_attempts)->toBe(0);
});

test('an unknown email does not fall over', function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    $this->api('POST', 'auth/staff/login', [
        'email' => 'nobody@mobiletest.local',
        'password' => 'whatever',
    ])->assertStatus(401);
});
