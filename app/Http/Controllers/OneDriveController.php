<?php

namespace App\Http\Controllers;

use App\Filament\SuperAdmin\Pages\PlatformSettings;
use App\Services\OneDriveService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OAuth2 flow for connecting the platform's OneDrive backup account.
 * Restricted to platform admins — mirrors BackupController's gate, since a
 * connected account receives every database dump.
 */
class OneDriveController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $state = Str::random(40);
        $request->session()->put('onedrive_oauth_state', $state);

        return redirect()->away(app(OneDriveService::class)->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $expectedState = $request->session()->pull('onedrive_oauth_state');

        if ($request->query('error')) {
            Notification::make()
                ->title('OneDrive connection failed')
                ->body($request->query('error_description', $request->query('error')))
                ->danger()
                ->send();

            return redirect(PlatformSettings::getUrl());
        }

        if (!$expectedState || !hash_equals($expectedState, (string) $request->query('state'))) {
            abort(403, 'Invalid OAuth state');
        }

        try {
            app(OneDriveService::class)->handleCallback((string) $request->query('code'));

            Notification::make()
                ->title('OneDrive connected')
                ->body('Backups will now be mirrored to OneDrive automatically.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('OneDrive connection failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return redirect(PlatformSettings::getUrl());
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user, 401);

        $allowed = method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'platform_admin']);

        abort_unless($allowed, 403, 'You do not have permission to manage OneDrive backups');
    }
}
