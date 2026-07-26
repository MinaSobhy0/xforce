<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backblaze B2 integration via the native B2 API (no SDK dependency).
 *
 * Configured entirely from Platform Settings: application key ID, application
 * key (stored encrypted) and bucket name. The 24h-valid authorization token
 * and resolved bucket id are cached for 12 hours.
 */
class BackblazeService
{
    protected const AUTH_URL = 'https://api.backblazeb2.com/b2api/v2/b2_authorize_account';

    public static function isConfigured(): bool
    {
        return (bool) PlatformSetting::get('backblaze_key_id')
            && (bool) PlatformSetting::getEncrypted('backblaze_app_key')
            && (bool) PlatformSetting::get('backblaze_bucket');
    }

    public static function isEnabled(): bool
    {
        return (bool) PlatformSetting::get('backblaze_enabled', false)
            && self::isConfigured();
    }

    /**
     * Remote object name for a backup's local path, e.g.
     * "XLinic-Backups/tenants/34/tenant_democlinic_....sql.gz".
     * Derived (not stored) so deletion works after the local record is gone.
     */
    public static function remotePathFor(string $localPath): string
    {
        $folder = trim((string) PlatformSetting::get('backblaze_folder', 'XLinic-Backups'), '/');
        $relative = preg_replace('#^backups/#', '', trim($localPath, '/'));

        return ($folder !== '' ? $folder . '/' : '') . $relative;
    }

    /**
     * Authorize against B2 and resolve the bucket id. Cached for 12 hours
     * (tokens are valid for 24). Returns [apiUrl, token, bucketId].
     */
    protected function auth(): array
    {
        $cached = Cache::get('backblaze_auth');
        if ($cached) {
            return $cached;
        }

        $keyId = PlatformSetting::get('backblaze_key_id');
        $appKey = PlatformSetting::getEncrypted('backblaze_app_key');
        $bucketName = PlatformSetting::get('backblaze_bucket');

        if (!$keyId || !$appKey || !$bucketName) {
            throw new \RuntimeException('Backblaze is not configured (key ID, application key and bucket are required).');
        }

        $response = Http::withBasicAuth($keyId, $appKey)->timeout(60)->get(self::AUTH_URL);
        $this->assertSuccessful($response, 'authorization');

        $apiUrl = $response->json('apiUrl');
        $token = $response->json('authorizationToken');
        $accountId = $response->json('accountId');
        $allowedBucketId = $response->json('allowed.bucketId');
        $allowedBucketName = $response->json('allowed.bucketName');

        if ($allowedBucketId) {
            // Key is restricted to a single bucket — it must be the configured one.
            if ($allowedBucketName && $allowedBucketName !== $bucketName) {
                throw new \RuntimeException("This application key is restricted to bucket \"{$allowedBucketName}\", but the configured bucket is \"{$bucketName}\".");
            }
            $bucketId = $allowedBucketId;
        } else {
            $buckets = Http::withHeaders(['Authorization' => $token])
                ->timeout(60)
                ->post($apiUrl . '/b2api/v2/b2_list_buckets', [
                    'accountId' => $accountId,
                    'bucketName' => $bucketName,
                ]);
            $this->assertSuccessful($buckets, 'bucket lookup');

            $bucketId = $buckets->json('buckets.0.bucketId');

            // Be forgiving: the admin may have pasted the bucket ID instead
            // of the bucket name — accept either.
            if (!$bucketId) {
                $byId = Http::withHeaders(['Authorization' => $token])
                    ->timeout(60)
                    ->post($apiUrl . '/b2api/v2/b2_list_buckets', [
                        'accountId' => $accountId,
                        'bucketId' => $bucketName,
                    ]);
                $bucketId = $byId->successful() ? $byId->json('buckets.0.bucketId') : null;
            }

            if (!$bucketId) {
                throw new \RuntimeException("Bucket \"{$bucketName}\" was not found in this Backblaze account.");
            }
        }

        $auth = ['apiUrl' => $apiUrl, 'token' => $token, 'bucketId' => $bucketId];
        Cache::put('backblaze_auth', $auth, now()->addHours(12));

        return $auth;
    }

    /** Drop the cached authorization (e.g. after credentials change). */
    public function forgetAuth(): void
    {
        Cache::forget('backblaze_auth');
    }

    /** B2 file-name header: percent-encode each segment, keep the slashes. */
    protected function encodeName(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }

    public function upload(string $localAbsolutePath, string $remotePath): void
    {
        if (!is_file($localAbsolutePath)) {
            throw new \RuntimeException("Local file not found: {$localAbsolutePath}");
        }

        $auth = $this->auth();

        $target = Http::withHeaders(['Authorization' => $auth['token']])
            ->timeout(60)
            ->post($auth['apiUrl'] . '/b2api/v2/b2_get_upload_url', [
                'bucketId' => $auth['bucketId'],
            ]);
        $this->assertSuccessful($target, 'get upload URL');

        $response = Http::withHeaders([
            'Authorization' => $target->json('authorizationToken'),
            'X-Bz-File-Name' => $this->encodeName($remotePath),
            'X-Bz-Content-Sha1' => sha1_file($localAbsolutePath),
        ])
            ->timeout(1800)
            ->withBody(file_get_contents($localAbsolutePath), 'b2/x-auto')
            ->post($target->json('uploadUrl'));

        $this->assertSuccessful($response, "upload {$remotePath}");
    }

    /**
     * Delete a backup's remote copy — every version of the file itself and,
     * for system backups (a folder), everything underneath it. Nothing found
     * is treated as success: the copy is already gone.
     */
    public function delete(string $remotePath): void
    {
        $auth = $this->auth();
        $remotePath = trim($remotePath, '/');
        $startFileName = $remotePath;
        $startFileId = null;

        do {
            $payload = [
                'bucketId' => $auth['bucketId'],
                'prefix' => $remotePath,
                'startFileName' => $startFileName,
                'maxFileCount' => 1000,
            ];
            if ($startFileId) {
                $payload['startFileId'] = $startFileId;
            }

            $list = Http::withHeaders(['Authorization' => $auth['token']])
                ->timeout(120)
                ->post($auth['apiUrl'] . '/b2api/v2/b2_list_file_versions', $payload);
            $this->assertSuccessful($list, "list versions of {$remotePath}");

            foreach ($list->json('files', []) as $file) {
                $name = $file['fileName'];
                if ($name !== $remotePath && !str_starts_with($name, $remotePath . '/')) {
                    continue;
                }

                $deleted = Http::withHeaders(['Authorization' => $auth['token']])
                    ->timeout(60)
                    ->post($auth['apiUrl'] . '/b2api/v2/b2_delete_file_version', [
                        'fileName' => $name,
                        'fileId' => $file['fileId'],
                    ]);
                $this->assertSuccessful($deleted, "delete version of {$name}");
            }

            $startFileName = $list->json('nextFileName');
            $startFileId = $list->json('nextFileId');
        } while ($startFileName !== null);
    }

    protected function assertSuccessful(Response $response, string $action): void
    {
        if (!$response->successful()) {
            // An expired/invalid cached token should not poison future runs.
            if ($response->status() === 401) {
                $this->forgetAuth();
            }

            $message = $response->json('message') ?? $response->body();
            Log::warning("Backblaze {$action} failed", ['status' => $response->status(), 'body' => $message]);
            throw new \RuntimeException("Backblaze {$action} failed: HTTP {$response->status()} {$message}");
        }
    }
}
