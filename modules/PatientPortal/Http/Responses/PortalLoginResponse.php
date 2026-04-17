<?php

namespace Modules\PatientPortal\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class PortalLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        // Redirect to portal dashboard after login (don't use intended() to avoid loops)
        return redirect()->to(route('filament.portal.pages.portal-dashboard'));
    }
}
