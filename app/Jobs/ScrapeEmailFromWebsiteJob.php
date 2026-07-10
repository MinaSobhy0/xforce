<?php

namespace App\Jobs;

use App\Models\PlatformEmailListMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Fetches one website's homepage + a couple of common contact-page
 * paths, extracts any email addresses found, and appends the FIRST
 * discovered address to the list as a new member (idempotent per
 * list + email).
 *
 * Throttled globally so we never hammer any single host and never
 * exceed a polite total request rate. At 5/sec, an 88-row CSV
 * finishes in ~20 seconds while playing nice with everyone.
 */
class ScrapeEmailFromWebsiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $backoff = [30];

    public $timeout = 30;

    public function __construct(
        public int $listId,
        public string $websiteUrl,
        public ?string $nameHint = null,
        public string $sourceRef = 'website_scrape',
    ) {
        $this->onConnection('central');
    }

    public function handle(): void
    {
        Redis::throttle('platform-email-scrape')
            ->allow(5)
            ->every(1)
            ->then(function (): void {
                $this->scrape();
            }, function () {
                $this->release(2);
            });
    }

    protected function scrape(): void
    {
        $url = $this->normalizeUrl($this->websiteUrl);
        if ($url === null) {
            return;
        }

        // Try the homepage first, then a couple of common contact paths.
        // Stop as soon as we find an email — most sites publish theirs
        // on the first page a scraper would look at.
        $paths = ['', '/contact', '/contact-us', '/about', '/about-us', '/impressum'];
        $emails = [];

        foreach ($paths as $suffix) {
            $target = rtrim($url, '/').$suffix;
            $found = $this->fetchAndExtract($target);
            if (! empty($found)) {
                $emails = $found;
                break;
            }
        }

        if (empty($emails)) {
            return; // silent — many clinic sites just don't publish emails
        }

        // Pick the highest-signal candidate: prefer `info@`, `contact@`,
        // `hello@`, or anything at the same domain as the website. Skip
        // obviously bad matches (image CDN, wordpress author, gravatar).
        $primary = $this->pickBestEmail($emails, $url);
        if ($primary === null) {
            return;
        }

        PlatformEmailListMember::firstOrCreate(
            ['list_id' => $this->listId, 'email' => $primary],
            [
                'name_hint' => $this->nameHint,
                'source_type' => $this->sourceRef,
                'source_id' => $url,
                'subscribed_at' => now(),
            ],
        );
    }

    protected function fetchAndExtract(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; XLinicBot/1.0; +https://x-linic.com/bot)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->connectTimeout(8)
                ->timeout(15)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);

            if (! $response->successful()) {
                return [];
            }

            $body = (string) $response->body();
            if ($body === '') {
                return [];
            }

            return $this->extractEmails($body);
        } catch (\Throwable $e) {
            Log::debug('Email scrape fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    protected function extractEmails(string $html): array
    {
        // 1. mailto: links first — highest signal
        preg_match_all('/mailto:([^"\'\s?&#>]+)/i', $html, $matches);
        $emails = $matches[1] ?? [];

        // 2. Plain-text emails in the body — but skip common junk domains
        //    (image CDNs, tracking pixels, WordPress noise).
        preg_match_all(
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            $html,
            $textMatches,
        );
        $emails = array_merge($emails, $textMatches[0] ?? []);

        // Normalize + dedupe
        $emails = collect($emails)
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->reject(fn ($e) => $this->isJunkEmail($e))
            ->unique()
            ->values()
            ->all();

        return $emails;
    }

    protected function isJunkEmail(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@'), 1));

        // Obvious CMS / CDN / example noise
        $junkDomains = [
            'example.com', 'example.org', 'example.net', 'domain.com',
            'wordpress.com', 'wpengine.com', 'gravatar.com',
            'sentry.io', 'sentry-next.wixpress.com',
            'gstatic.com', 'googleusercontent.com',
        ];
        foreach ($junkDomains as $junk) {
            if ($domain === $junk || str_ends_with($domain, '.'.$junk)) {
                return true;
            }
        }

        // Sentry / integrations markers frequently appear
        if (str_contains($email, 'sentry')) return true;
        if (str_contains($email, 'noreply')) return true; // don't email a noreply@
        if (str_contains($email, 'no-reply')) return true;
        if (str_contains($email, 'do-not-reply')) return true;

        return false;
    }

    protected function pickBestEmail(array $emails, string $siteUrl): ?string
    {
        if (empty($emails)) {
            return null;
        }

        $host = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host ?? '');

        $preferredPrefixes = ['info@', 'contact@', 'hello@', 'sales@', 'support@', 'admin@', 'reception@'];

        // Two-round preference:
        //   1. Preferred prefix @ same-domain — perfect match
        //   2. Any @ same-domain — good match
        //   3. Preferred prefix @ any domain — decent match
        //   4. Anything at all — last resort
        foreach ([true, false] as $requireSameDomain) {
            foreach ($preferredPrefixes as $prefix) {
                foreach ($emails as $e) {
                    if (! str_starts_with($e, $prefix)) continue;
                    $emailDomain = strtolower((string) substr(strrchr($e, '@'), 1));
                    if ($requireSameDomain && ! str_ends_with($emailDomain, $host)) continue;
                    return $e;
                }
            }
            if ($requireSameDomain) {
                foreach ($emails as $e) {
                    $emailDomain = strtolower((string) substr(strrchr($e, '@'), 1));
                    if (str_ends_with($emailDomain, $host)) return $e;
                }
            }
        }

        return $emails[0];
    }

    protected function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Defensive: even if column detection slipped and sent us at an
        // image or Google CDN, don't waste a fetch on it.
        if (preg_match('/\.(?:png|jpe?g|gif|svg|webp|avif|bmp|ico)(?:$|\?)/i', $url)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (['google.com', 'gstatic.com', 'googleusercontent.com', 'ggpht.com', 'googleapis.com'] as $root) {
            if ($host === $root || str_ends_with($host, '.'.$root)) {
                return null;
            }
        }
        return $url;
    }
}
