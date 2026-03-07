<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketing\Models\ShortLink;

class ShortLinkController extends Controller
{
    /**
     * Redirect short link to target URL.
     */
    public function redirect(string $code)
    {
        $link = ShortLink::where('code', $code)->first();

        if (!$link) {
            return response()->view('marketing::short-link-error', [
                'message' => __('marketing::marketing.short_links.not_found'),
            ], 404);
        }

        if ($link->isExpired()) {
            return response()->view('marketing::short-link-error', [
                'message' => __('marketing::marketing.short_links.expired'),
            ], 410);
        }

        // Record the click
        $link->recordClick();

        // Redirect to target URL
        return redirect($link->target_url);
    }
}
