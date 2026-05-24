<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Tenant;

/**
 * Downloads inbound media from Meta's Graph API and stores it on the
 * tenant disk so it survives Meta's 5-minute signed-URL expiry.
 *
 * Two-step dance the Cloud API requires:
 *   1. GET /v18.0/{media_id} → returns a signed URL valid for 5 minutes
 *   2. GET that URL with Bearer token → returns the bytes
 */
class MediaDownloader
{
    /** WhatsApp Cloud API hard limit (25 MB across all media types). */
    protected const MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(
        protected WhatsAppCredentialsResolver $resolver,
    ) {}

    /**
     * @return array{path: string, mime: ?string, size: int, sha256: string}
     */
    public function downloadAndStore(Tenant $tenant, string $mediaId, string $wamid): array
    {
        $creds = $this->resolver->resolveFor($tenant);
        if (! $creds) {
            throw new \RuntimeException('No WhatsApp credentials resolved for tenant');
        }

        $apiVersion = $creds['api_version'] ?? 'v18.0';

        // Step 1: resolve the signed URL
        $metaResp = Http::withToken($creds['access_token'])
            ->get("https://graph.facebook.com/{$apiVersion}/{$mediaId}");

        if (! $metaResp->successful()) {
            throw new \RuntimeException("Meta media lookup failed: " . $metaResp->body());
        }

        $url = (string) ($metaResp->json('url') ?? '');
        $mime = $metaResp->json('mime_type');
        $sha256 = (string) ($metaResp->json('sha256') ?? '');
        $size = (int) ($metaResp->json('file_size') ?? 0);

        if ($url === '') {
            throw new \RuntimeException('Meta returned no signed URL for media');
        }
        if ($size > self::MAX_BYTES) {
            throw new \RuntimeException("Media exceeds {$size} bytes (limit 25MB)");
        }

        // Step 2: download bytes (must include Bearer; Meta CDN rejects anon)
        $bytes = Http::withToken($creds['access_token'])
            ->withOptions(['stream' => false])
            ->get($url);

        if (! $bytes->successful()) {
            throw new \RuntimeException('Meta media download failed: ' . $bytes->status());
        }

        $body = (string) $bytes->body();
        if ($sha256 === '') {
            $sha256 = hash('sha256', $body);
        }

        $ext = $this->extFromMime($mime);
        $path = sprintf(
            'whatsapp/inbound/%s/%s/%s%s',
            now()->format('Y'),
            now()->format('m'),
            $wamid,
            $ext ? ".{$ext}" : ''
        );

        Storage::disk('tenant')->put($path, $body);

        Log::info('whatsapp.inbound.media_stored', [
            'tenant_id' => $tenant->id,
            'wamid' => $wamid,
            'path' => $path,
            'size' => strlen($body),
            'mime' => $mime,
        ]);

        return [
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($body),
            'sha256' => $sha256,
        ];
    }

    protected function extFromMime(?string $mime): ?string
    {
        if (! $mime) {
            return null;
        }
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
        ];

        return $map[strtolower($mime)] ?? null;
    }
}
