<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\ImpersonateController;

Route::middleware(['web'])->prefix('auth')->name('auth.')->group(function () {
    // Auth web routes
});

// Impersonation route (no auth required - uses token)
Route::middleware(['web'])->group(function () {
    Route::get('/admin/impersonate', [ImpersonateController::class, 'login'])->name('impersonate.login');
});

// Same-panel impersonation exit — reads impersonator_id from session and
// restores that user's login. Kept off ImpersonateController because that
// controller handles the token-based cross-panel flow; the same-panel
// enter/exit is intentionally session-only (no DB token to burn).
// Accepts BOTH GET and POST: the panel's ->spa() mode (Livewire's
// wire:navigate) intercepts inline form submits, so the banner uses a
// native window.location.href navigation. There's no real CSRF risk on
// "stop being someone else and go back to yourself" for an
// already-authenticated admin, so allowing GET is deliberate.
Route::middleware(['web', 'auth'])->group(function () {
    Route::match(['GET', 'POST'], '/admin/impersonate/stop', function () {
        $impersonatorId = session('impersonator_id');
        if (! $impersonatorId) {
            return redirect('/admin');
        }

        $impersonator = \Modules\Auth\Models\User::find($impersonatorId);
        if (! $impersonator) {
            // Impersonator was deleted mid-session — safest to log out entirely.
            session()->forget(['impersonator_id', 'impersonator_started_at']);
            \Illuminate\Support\Facades\Auth::logout();

            return redirect('/login');
        }

        $impersonatedId = auth()->id();
        session()->forget(['impersonator_id', 'impersonator_started_at']);
        \Illuminate\Support\Facades\Auth::login($impersonator);

        // Refresh the AuthenticateSession password-hash sentinel so the
        // next request doesn't see a mismatch and force logout.
        $hash = $impersonator->getAuthPassword();
        session()->put('password_hash_web', $hash);
        session()->put('password_hash', $hash);

        activity()
            ->causedBy($impersonator)
            ->performedOn(\Modules\Auth\Models\User::find($impersonatedId))
            ->log('Stopped impersonating user');

        return redirect('/admin');
    })->name('impersonate.stop');
});
