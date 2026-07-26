<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Microsoft OneDrive integration via the Graph API (no SDK dependency).
 *
 * Auth is the OAuth2 authorization-code flow against the `common` endpoint so
 * both personal and work/school Microsoft accounts work. The refresh token is
 * stored encrypted in platform_settings; access tokens are cached ~50 minutes.
 * Personal accounts rotate refresh tokens on every refresh, so the rotated
 * token is persisted each time one comes back.
 */
class OneDriveService
{
    protected const AUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    protected const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    protected const GRAPH = 'https://graph.microsoft.com/v1.0';
    protected const SCOPES = 'offline_access Files.ReadWrite User.Read';

    /** Chunk size for upload sessions — must be a multiple of 320 KiB. */
    protected const CHUNK_SIZE = 10 * 1024 * 1024; // 10 MiB = 32 × 320 KiB

    /** Files at or below this size use a single-request upload. */
    protected const SIMPLE_UPLOAD_LIMIT = 4 * 1024 * 1024;

    public static function isEnabled(): bool
    {
        return (bool) PlatformSetting::get('onedrive_enabled', false)
            && self::isConnected();
    }

    public static function isConnected(): bool
    {
        return (bool) PlatformSetting::getEncrypted('onedrive_refresh_token');
    }

    /**
     * Remote path (relative to the OneDrive root) for a backup's local path,
     * e.g. "XLinic-Backups/tenants/34/tenant_democlinic_....sql.gz".
     * Derived (not stored) so deletion works after the local record is gone.
     */
    public static function remotePathFor(string $localPath): string
    {
        $folder = trim((string) PlatformSetting::get('onedrive_folder', 'XLinic-Backups'), '/');
        $relative = preg_replace('#^backups/#', '', trim($localPath, '/'));

        return $folder . '/' . $relative;
    }

    public function redirectUri(): string
    {
        return route('platform.onedrive.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => PlatformSetting::get('onedrive_client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorization code, store the refresh token and the
     * connected account's display name.
     */
    public function handleCallback(string $code): void
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => PlatformSetting::get('onedrive_client_id'),
            'client_secret' => PlatformSetting::getEncrypted('onedrive_client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPES,
        ]);

        if (!$response->successful() || !$response->json('refresh_token')) {
            throw new \RuntimeException('OneDrive token exchange failed: ' . ($response->json('error_description') ?? $response->body()));
        }

        PlatformSetting::setEncrypted('onedrive_refresh_token', $response->json('refresh_token'), 'backup');
        Cache::put('onedrive_access_token', $response->json('access_token'), now()->addMinutes(50));

        // Best-effort: remember who connected, for the settings screen.
        try {
            $drive = Http::withToken($response->json('access_token'))
                ->get(self::GRAPH . '/me/drive?$select=owner,driveType');
            $owner = $drive->json('owner.user.displayName') ?? 'Microsoft account';
            PlatformSetting::set('onedrive_account', $owner, 'backup');
        } catch (\Throwable $e) {
            PlatformSetting::set('onedrive_account', 'Microsoft account', 'backup');
        }
    }

    public function disconnect(): void
    {
        PlatformSetting::set('onedrive_refresh_token', '', 'backup');
        PlatformSetting::set('onedrive_account', '', 'backup');
        Cache::forget('onedrive_access_token');
    }

    protected function accessToken(): string
    {
        $cached = Cache::get('onedrive_access_token');
        if ($cached) {
            return $cached;
        }

        $refreshToken = PlatformSetting::getEncrypted('onedrive_refresh_token');
        if (!$refreshToken) {
            throw new \RuntimeException('OneDrive is not connected.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => PlatformSetting::get('onedrive_client_id'),
            'client_secret' => PlatformSetting::getEncrypted('onedrive_client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => self::SCOPES,
        ]);

        if (!$response->successful() || !$response->json('access_token')) {
            Log::error('OneDrive token refresh failed', ['body' => $response->body()]);
            throw new \RuntimeException('OneDrive token refresh failed: ' . ($response->json('error_description') ?? $response->status()));
        }

        // Personal accounts rotate the refresh token — keep the latest one.
        if ($response->json('refresh_token')) {
            PlatformSetting::setEncrypted('onedrive_refresh_token', $response->json('refresh_token'), 'backup');
        }

        $token = $response->json('access_token');
        Cache::put('onedrive_access_token', $token, now()->addMinutes(50));

        return $token;
    }

    /** Each path segment must be URL-encoded individually. */
    protected function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }

    public function upload(string $localAbsolutePath, string $remotePath): void
    {
        if (!is_file($localAbsolutePath)) {
            throw new \RuntimeException("Local file not found: {$localAbsolutePath}");
        }

        $size = filesize($localAbsolutePath);

        if ($size <= self::SIMPLE_UPLOAD_LIMIT) {
            $this->uploadSmall($localAbsolutePath, $remotePath);
        } else {
            $this->uploadChunked($localAbsolutePath, $remotePath, $size);
        }
    }

    protected function uploadSmall(string $localAbsolutePath, string $remotePath): void
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(300)
            ->withBody(file_get_contents($localAbsolutePath), 'application/octet-stream')
            ->put(self::GRAPH . '/me/drive/root:/' . $this->encodePath($remotePath) . ':/content');

        $this->assertSuccessful($response, "upload {$remotePath}");
    }

    protected function uploadChunked(string $localAbsolutePath, string $remotePath, int $size): void
    {
        $session = Http::withToken($this->accessToken())
            ->post(self::GRAPH . '/me/drive/root:/' . $this->encodePath($remotePath) . ':/createUploadSession', [
                'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
            ]);

        $this->assertSuccessful($session, "create upload session for {$remotePath}");

        $uploadUrl = $session->json('uploadUrl');
        $handle = fopen($localAbsolutePath, 'rb');

        try {
            $offset = 0;
            while ($offset < $size) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                $end = $offset + strlen($chunk) - 1;

                // The pre-authenticated uploadUrl must NOT get the bearer token.
                $response = Http::withHeaders([
                    'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                ])
                    ->timeout(600)
                    ->withBody($chunk, 'application/octet-stream')
                    ->put($uploadUrl);

                if (!$response->successful()) {
                    throw new \RuntimeException("OneDrive chunk upload failed at byte {$offset} for {$remotePath}: HTTP {$response->status()} {$response->body()}");
                }

                $offset = $end + 1;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Delete a file or folder (recursively) on OneDrive. A 404 is treated as
     * success — the copy is already gone.
     */
    public function delete(string $remotePath): void
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(120)
            ->delete(self::GRAPH . '/me/drive/root:/' . $this->encodePath($remotePath) . ':');

        if ($response->status() === 404) {
            return;
        }

        $this->assertSuccessful($response, "delete {$remotePath}");
    }

    protected function assertSuccessful(Response $response, string $action): void
    {
        if (!$response->successful()) {
            throw new \RuntimeException("OneDrive {$action} failed: HTTP {$response->status()} {$response->body()}");
        }
    }
}
