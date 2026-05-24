<?php

namespace Modules\Marketing\Services\Meta;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\WhatsAppMediaCache;
use Modules\Marketing\Services\WhatsAppCredentialsResolver;

/**
 * Uploads media to Meta and returns a media_id usable in subsequent
 * /messages calls (type=image, image.id=..., etc).
 *
 * Caches by sha256 in the tenant's whatsapp_media_cache so re-sending
 * the same clinic logo or PDF invoice doesn't re-upload — second call
 * is a one-row DB hit instead of a multipart POST. Cache TTL matches
 * Meta's media_id lifetime (~30 days).
 */
class MediaUploader
{
    public function __construct(
        protected WhatsAppCredentialsResolver $resolver,
    ) {}

    /**
     * @return string Meta media_id
     */
    public function uploadFromPath(string $absolutePath, string $mime): string
    {
        if (! is_file($absolutePath)) {
            throw new \RuntimeException("Media file not found: {$absolutePath}");
        }

        $sha256 = hash_file('sha256', $absolutePath) ?: '';
        if ($sha256 === '') {
            throw new \RuntimeException("Failed to hash media file: {$absolutePath}");
        }

        $cached = WhatsAppMediaCache::query()
            ->where('sha256', $sha256)
            ->where('uploaded_at', '>', now()->subDays(28))
            ->first();

        if ($cached) {
            Log::debug('whatsapp.media_cache.hit', [
                'sha256' => $sha256,
                'media_id' => $cached->media_id,
            ]);

            return $cached->media_id;
        }

        $mediaId = $this->uploadToMeta($absolutePath, $mime);

        WhatsAppMediaCache::updateOrCreate(
            ['sha256' => $sha256],
            [
                'media_id' => $mediaId,
                'mime' => $mime,
                'size_bytes' => filesize($absolutePath) ?: null,
                'uploaded_at' => now(),
            ]
        );

        Log::info('whatsapp.media_cache.miss_uploaded', [
            'sha256' => $sha256,
            'media_id' => $mediaId,
            'mime' => $mime,
            'size_bytes' => filesize($absolutePath),
        ]);

        return $mediaId;
    }

    /**
     * @return string Meta media_id
     */
    public function uploadFromUrl(string $url, ?string $mime = null): string
    {
        $bytes = Http::withOptions(['stream' => false])->get($url);
        if (! $bytes->successful()) {
            throw new \RuntimeException("Failed to download source URL ({$bytes->status()}): {$url}");
        }

        $contentType = $mime ?: ($bytes->header('Content-Type') ?: 'application/octet-stream');

        $tmp = tempnam(sys_get_temp_dir(), 'wa-media-');
        file_put_contents($tmp, $bytes->body());

        try {
            return $this->uploadFromPath($tmp, $contentType);
        } finally {
            @unlink($tmp);
        }
    }

    protected function uploadToMeta(string $absolutePath, string $mime): string
    {
        $creds = $this->resolver->resolveFor(current_tenant());
        if (! $creds || ! ($creds['phone_number_id'] ?? null)) {
            throw new \RuntimeException('No WhatsApp credentials resolved for current tenant');
        }

        $apiVersion = $creds['api_version'] ?? 'v18.0';
        $endpoint = "https://graph.facebook.com/{$apiVersion}/{$creds['phone_number_id']}/media";

        /** @var PendingRequest $request */
        $request = Http::withToken($creds['access_token'])
            ->asMultipart()
            ->attach(
                'file',
                fopen($absolutePath, 'r'),
                basename($absolutePath),
                ['Content-Type' => $mime]
            );

        $response = $request->post($endpoint, [
            ['name' => 'messaging_product', 'contents' => 'whatsapp'],
            ['name' => 'type', 'contents' => $mime],
        ]);

        if (! $response->successful() || ! ($response->json('id'))) {
            throw new \RuntimeException(
                'Meta media upload failed: '.($response->json('error.message') ?: $response->body())
            );
        }

        return (string) $response->json('id');
    }
}
