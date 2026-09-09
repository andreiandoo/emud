<?php

namespace App\Http\Controllers;

use App\Directory\LeadTracker;
use App\Enums\ServiceLeadEventType;
use App\Models\ServiceShop;
use Illuminate\Http\RedirectResponse;

/**
 * Counts a click on a workshop's own link, then sends the visitor there.
 *
 * A redirect rather than a wire:click so the anchor behaves like an anchor — middle click,
 * copy link, open in a new tab all still work, and the destination is visible on hover.
 *
 * The destination is read from the workshop record and never from the request. A redirector
 * that forwards to a URL supplied by the caller is an open redirect, which is worth having in
 * mind every time one of these is written.
 */
class ServiceShopLinkController extends Controller
{
    public function __invoke(string $city, string $slug, string $type, LeadTracker $leads): RedirectResponse
    {
        $shop = ServiceShop::query()->published()->where('slug', $slug)->firstOrFail();

        $destination = match ($type) {
            'website' => $shop->website,
            'directions' => $shop->directionsUrl(),
            default => null,
        };

        abort_if($destination === null || $destination === '', 404);

        $leads->record($shop, $type === 'website' ? ServiceLeadEventType::WebsiteClick : ServiceLeadEventType::Directions);

        return redirect()->away($destination);
    }
}
