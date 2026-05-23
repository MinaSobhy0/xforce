<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v2/app/version-check
 *
 * Driven by the platform-wide "App Version Policy" page (no tenant context
 * required — the mobile splash hits this before knowing which clinic).
 *
 * Query params:
 *   platform: ios | android (required)
 *   version:  semver e.g. 1.0.0 (required)
 *
 * Response:
 *   update_required:   below minimum_version AND force_update is on
 *   update_recommended: between minimum_version and latest_version
 *   minimum_version, latest_version, store_url, message
 */
class AppVersionController extends BaseApiController
{
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|in:ios,android',
            'version' => 'required|string|max:20',
        ]);

        $platform = $validated['platform'];
        $current = $this->normalize($validated['version']);

        $minimum = $this->normalize((string) PlatformSetting::get("mobile_version.{$platform}_minimum_version", ''));
        $latest = $this->normalize((string) PlatformSetting::get("mobile_version.{$platform}_latest_version", ''));
        $force = (bool) PlatformSetting::get("mobile_version.{$platform}_force_update", false);
        $storeUrl = PlatformSetting::get("mobile_version.{$platform}_store_url", '');
        $message = PlatformSetting::get("mobile_version.{$platform}_message", '');

        $updateRequired = $force
            && $minimum !== null
            && $this->lessThan($current, $minimum);

        $updateRecommended = ! $updateRequired
            && $latest !== null
            && $this->lessThan($current, $latest);

        return $this->success([
            'platform' => $platform,
            'current_version' => $validated['version'],
            'minimum_version' => $this->stringify($minimum),
            'latest_version' => $this->stringify($latest),
            'update_required' => $updateRequired,
            'update_recommended' => $updateRecommended,
            'store_url' => $storeUrl ?: null,
            'message' => $message ?: null,
        ]);
    }

    /**
     * Parse "1.2.3" -> [1,2,3]. Returns null when blank/unparseable.
     */
    protected function normalize(string $version): ?array
    {
        $version = trim($version);
        if ($version === '') {
            return null;
        }

        // Strip a leading 'v' and anything after a '-' (build/prerelease).
        $version = ltrim($version, 'vV');
        $version = preg_replace('/[-+].*$/', '', $version);

        $parts = array_map(
            static fn ($p) => (int) preg_replace('/\D/', '', (string) $p),
            explode('.', $version),
        );

        // Pad to 3 segments.
        while (count($parts) < 3) {
            $parts[] = 0;
        }

        return array_slice($parts, 0, 3);
    }

    protected function lessThan(?array $a, ?array $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        return $a < $b; // PHP compares arrays element-wise — exactly what we need.
    }

    protected function stringify(?array $parts): ?string
    {
        return $parts ? implode('.', $parts) : null;
    }
}
