<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\Response;

class PublicSitemapController extends Controller
{
    public function show(): Response
    {
        $origin = rtrim((string) config('app.frontend_url'), '/');
        if ($origin === '') {
            $origin = rtrim((string) config('app.url'), '/');
        }
        $paths = [
            '/',
            '/services',
            '/packages',
            '/marketing-packages',
            '/event-packages',
            '/printing-packaging',
            '/build-package',
            '/consultant',
            '/suppliers',
            '/portfolio',
            '/about',
            '/contact',
        ];

        foreach (Service::query()->active()->public()->orderBy('id')->get(['slug']) as $service) {
            if (filled($service->slug)) {
                $paths[] = '/services/'.$service->slug;
            }
        }

        foreach (Supplier::query()->publiclyVisible()->orderBy('id')->get(['slug']) as $supplier) {
            $paths[] = '/suppliers/'.$supplier->slug;
        }

        $products = SupplierProduct::query()
            ->published()
            ->whereHas('supplier', fn ($query) => $query->publiclyVisible())
            ->with('supplier:id,slug')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            if ($product->supplier?->slug) {
                $paths[] = '/suppliers/'.$product->supplier->slug.'/products/'.$product->slug;
            }
        }

        if (PortfolioItem::query()->published()->exists()) {
            $paths[] = '/portfolio';
            foreach (PortfolioItem::query()->published()->orderBy('id')->get(['slug']) as $item) {
                if (filled($item->slug)) {
                    $paths[] = '/portfolio/'.$item->slug;
                }
            }
        }

        $urls = collect($paths)->unique()->map(function (string $path) use ($origin): string {
            return '  <url><loc>'.e($origin.$path).'</loc><changefreq>weekly</changefreq></url>';
        })->implode("\n");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls."\n"
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
