<?php

namespace Modules\Marketing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\Marketing\Services\WhatsAppCredentialsResolver;

/**
 * Re-seeds a tenant's Meta WhatsApp access token after a sandbox token
 * expires (every ~24h) or after the auto-disconnect from a 190 error.
 *
 *   php artisan whatsapp:refresh-token --tenant=demo
 *
 * Production tenants on Embedded Signup get permanent System User tokens
 * and won't need this — it exists for sandbox / dev / break-glass use.
 */
class RefreshWhatsAppTokenCommand extends Command
{
    protected $signature = 'whatsapp:refresh-token
                            {--tenant= : Tenant slug to refresh (e.g. demo)}
                            {--token= : Fresh access token (will prompt if omitted)}';

    protected $description = 'Re-seed a tenant\'s Meta WhatsApp access token and flip status back to active';

    public function handle(WhatsAppCredentialsResolver $resolver, TenantSchemaSwitcher $switcher): int
    {
        $slug = $this->option('tenant') ?: $this->ask('Tenant slug');
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("No tenant found with slug '{$slug}'.");

            return self::FAILURE;
        }

        $existing = $tenant->getSetting('messaging.whatsapp.meta', []);
        if (empty($existing['phone_number_id']) || empty($existing['waba_id'])) {
            $this->error("Tenant '{$slug}' has no WhatsApp connection seeded — run the connect flow first.");

            return self::FAILURE;
        }

        $token = $this->option('token') ?: $this->secret('Paste fresh access token (will be encrypted)');
        if (! $token) {
            $this->error('No token provided — aborting.');

            return self::FAILURE;
        }

        $existing['access_token'] = Crypt::encryptString($token);
        $existing['status'] = 'active';
        $existing['last_error'] = null;
        $existing['refreshed_at'] = now()->toIso8601String();

        $tenant->setSetting('messaging.whatsapp.meta', $existing);
        $tenant->save();
        $resolver->flush($tenant);

        $this->info("✓ Token refreshed for tenant '{$slug}'.");
        $this->line("  phone_number_id: {$existing['phone_number_id']}");
        $this->line("  waba_id:         {$existing['waba_id']}");
        $this->line('');
        $this->line('Quick probe (token should be alive):');
        $this->line("  curl -s -H 'Authorization: Bearer <TOKEN>' \\");
        $this->line("    https://graph.facebook.com/v18.0/{$existing['phone_number_id']}");

        return self::SUCCESS;
    }
}
