<?php

namespace App\Models;

use App\Traits\HasPostgresBoolean;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TenantDomain extends Model
{
    use HasPostgresBoolean;

    /**
     * The database connection that should be used by the model.
     * TenantDomain lives in public schema, not tenant schema.
     */
    protected $connection = 'central';

    protected $table = 'public.tenant_domains';

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

        // Run certbot to generate and install SSL certificate using sudo
        $result = Process::timeout(120)->run([
            '/usr/bin/sudo', '/snap/bin/certbot',
            '--nginx',
            '-d', $this->domain,
            '--non-interactive',
            '--agree-tos',
            '--email', 'ssl@xforcehr.com',
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

        Log::info('SSL certificate provisioned and installed successfully', ['domain' => $this->domain]);

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
            '/usr/bin/sudo', '/snap/bin/certbot', 'renew',
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
     * Create a separate nginx server block for this domain.
     */
    protected function createNginxServerBlock(): void
    {
        $configPath = '/etc/nginx/sites-available/xlinic.conf';
        $config = file_get_contents($configPath);

        // Check if domain already has a server block
        if (str_contains($config, "server_name {$this->domain};")) {
            Log::info('Server block already exists for domain', ['domain' => $this->domain]);
            return;
        }

        // Create new server block for this domain
        $serverBlock = <<<NGINX

# Tenant domain: {$this->domain}
server {
    server_name {$this->domain};

    root /var/www/html/x_linic/public;
    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \\.php\$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }

    location ^~ /css/filament/ {
        alias /var/www/html/x_linic/public/css/filament/;
    }

    location ^~ /js/filament/ {
        alias /var/www/html/x_linic/public/js/filament/;
    }

    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/{$this->domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$this->domain}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}
NGINX;

        // Append server block to config
        $config .= $serverBlock;

        // Also add to HTTP redirect block
        $config = preg_replace(
            '/(server\s*\{\s*listen\s+80;\s*server_name\s+)([^;]+)(;\s*return\s+301)/',
            '$1$2 ' . $this->domain . '$3',
            $config,
            1
        );

        // Write config using sudo
        $tempFile = tempnam(sys_get_temp_dir(), 'nginx_');
        file_put_contents($tempFile, $config);

        Process::run(['/usr/bin/sudo', '/usr/bin/cp', $tempFile, $configPath]);
        unlink($tempFile);

        // Reload nginx
        $testResult = Process::run(['/usr/bin/sudo', '/usr/sbin/nginx', '-t']);
        if ($testResult->successful()) {
            Process::run(['/usr/bin/sudo', '/usr/bin/systemctl', 'reload', 'nginx']);
            Log::info('Nginx server block created for domain', ['domain' => $this->domain]);
        } else {
            Log::error('Nginx config test failed', ['error' => $testResult->errorOutput()]);
            throw new \Exception('Nginx configuration error: ' . $testResult->errorOutput());
        }
    }
}
