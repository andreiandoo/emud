<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Only pages a customer can actually reach are listed. A sitemap that advertises drafts or
     * unpublished products earns crawl errors and teaches search engines to trust it less.
     */
    public function index(): Response
    {
        $urls = [
            ['loc' => route('storefront.home'), 'priority' => '1.0'],
            ['loc' => route('storefront.search'), 'priority' => '0.3'],
        ];

        foreach (Category::query()->where('is_active', true)->orderBy('full_path')->get() as $category) {
            $urls[] = [
                'loc' => route('storefront.category', $category),
                'lastmod' => $category->updated_at?->toAtomString(),
                'priority' => '0.8',
            ];
        }

        foreach (Product::query()->active()->orderBy('id')->get() as $product) {
            $urls[] = [
                'loc' => route('storefront.product', $product),
                'lastmod' => $product->updated_at?->toAtomString(),
                'priority' => '0.7',
            ];
        }

        foreach (Page::query()->published()->where('robots_index', true)->orderBy('id')->get() as $page) {
            $urls[] = [
                'loc' => route('storefront.page', $page->slug),
                'lastmod' => $page->updated_at?->toAtomString(),
                'priority' => '0.4',
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Nothing behind sign-in, nothing personal, and no basket or checkout state: those
            // pages are per-visitor and would only produce duplicate, useless crawl paths.
            'Disallow: /admin',
            'Disallow: /cont',
            'Disallow: /cos',
            'Disallow: /finalizare',
            'Disallow: /comanda',
            '',
            'Sitemap: '.route('storefront.sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
