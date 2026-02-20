<?php

namespace App\Models;

use App\Traits\HasPostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TenantDomain extends Model
{
    use HasUuids, HasPostgresBoolean;

    protected $fillable = [
        'tenant_id',
        'domain',
        'type',
        'is_primary',
        'is_verified',
        'ssl_status',
        'ssl_expires_at',
        'dns_verified_at',
        'verification_token',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'ssl_expires_at' => 'datetime',
        'dns_verified_at' => 'datetime',
    ];

    protected function getPostgresBooleanFields(): array
    {
        return ['is_primary', 'is_verified'];
    }

    // Server IP address for DNS verification
    public const SERVER_IP = '145.223.118.113';

    public const TYPES = [
        'subdomain' => 'Subdomain',
        'custom' => 'Custom Domain',
    ];

    public const SSL_STATUSES = [
        'pending' => 'Pending',
        'valid' => 'Valid',
        'expiring' => 'Expiring Soon',
        'expired' => 'Expired',
        'failed' => 'Failed',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    public function scopeVerified($query)
    {
        return $query->whereRaw('is_verified = true');
    }

    public function scopeWithSslIssues($query)
    {
        return $query->whereIn('ssl_status', ['expiring', 'expired', 'failed']);
    }

    /**
     * Ping the domain and check if it resolves to our server.
     *
     * @return array{success: bool, ip: ?string, message: string}
     */
    public function pingDomain(): array
    {
        try {
            // Get A record for the domain
            $records = dns_get_record($this->domain, DNS_A);

            if (empty($records)) {
                // Try CNAME resolution
                $cname = dns_get_record($this->domain, DNS_CNAME);
                if (!empty($cname)) {
                    // Resolve the CNAME target
                    $target = $cname[0]['target'] ?? null;
                    if ($target) {
                        $records = dns_get_record($target, DNS_A);
                    }
                }
            }

            if (empty($records)) {
                return [
                    'success' => false,
                    'ip' => null,
                    'message' => 'No DNS records found for this domain.',
                ];
            }

            $resolvedIp = $records[0]['ip'] ?? null;

            if ($resolvedIp === self::SERVER_IP) {
                return [
                    'success' => true,
                    'ip' => $resolvedIp,
                    'message' => "Domain resolves correctly to our server ({$resolvedIp}).",
                ];
            }

            return [
                'success' => false,
                'ip' => $resolvedIp,
                'message' => "Domain resolves to {$resolvedIp}, but our server is " . self::SERVER_IP . ".",
            ];
        } catch (\Exception $e) {
            Log::error('DNS ping failed', [
                'domain' => $this->domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'ip' => null,
                'message' => 'DNS lookup failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify DNS points to our server.
     */
    public function verifyDns(): bool
    {
        $ping = $this->pingDomain();

        if ($ping['success']) {
            $this->update([
                'is_verified' => true,
                'dns_verified_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Provision SSL certificate using Let's Encrypt (certbot).
     *
     * @throws \Exception
     */
    public function provisionSsl(): void
    {
        if (!$this->is_verified) {
            throw new \Exception('Domain must be verified before provisioning SSL.');
        }

        // Double-check DNS resolves correctly
        $ping = $this->pingDomain();
        if (!$ping['success']) {
            throw new \Exception($ping['message']);
        }

        Log::info('Provisioning SSL certificate', ['domain' => $this->domain]);

        // Run certbot to generate SSL certificate
        $result = Process::timeout(120)->run([
            'certbot', 'certonly',
            '--nginx',
            '-d', $this->domain,
            '--non-interactive',
            '--agree-tos',
            '--email', 'ssl@x-linic.com',
        ]);

        if (!$result->successful()) {
            $error = $result->errorOutput() ?: $result->output();
            Log::error('SSL provisioning failed', [
                'domain' => $this->domain,
                'error' => $error,
            ]);

            $this->update(['ssl_status' => 'failed']);
            throw new \Exception('SSL provisioning failed: ' . $error);
        }

        Log::info('SSL certificate provisioned successfully', ['domain' => $this->domain]);

        // Update nginx configuration to include this domain
        $this->updateNginxConfig();

        // Update SSL status
        $this->update([
            'ssl_status' => 'valid',
            'ssl_expires_at' => now()->addMonths(3),
        ]);
    }

    /**
     * Renew SSL certificate.
     */
    public function renewSsl(): void
    {
        Log::info('Renewing SSL certificate', ['domain' => $this->domain]);

        $result = Process::timeout(120)->run([
            'certbot', 'renew',
            '--cert-name', $this->domain,
            '--non-interactive',
        ]);

        if (!$result->successful()) {
            $error = $result->errorOutput() ?: $result->output();
            Log::error('SSL renewal failed', [
                'domain' => $this->domain,
                'error' => $error,
            ]);

            $this->update(['ssl_status' => 'failed']);
            throw new \Exception('SSL renewal failed: ' . $error);
        }

        $this->update([
            'ssl_status' => 'valid',
            'ssl_expires_at' => now()->addMonths(3),
        ]);
    }

    /**
     * Update nginx configuration to include this custom domain.
     */
    protected function updateNginxConfig(): void
    {
        $configPath = '/etc/nginx/sites-available/xlinic.conf';
        $config = file_get_contents($configPath);

        // Check if domain is already in config
        if (str_contains($config, $this->domain)) {
            return;
        }

        // Add domain to server_name directive
        $config = preg_replace(
            '/server_name\s+([^;]+);/',
            'server_name $1 ' . $this->domain . ';',
            $config,
            1
        );

        file_put_contents($configPath, $config);

        // Reload nginx
        Process::run(['nginx', '-t']);
        Process::run(['systemctl', 'reload', 'nginx']);

        Log::info('Nginx config updated for domain', ['domain' => $this->domain]);
    }
}
