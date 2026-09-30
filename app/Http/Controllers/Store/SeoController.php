<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $categories = Category::query()->storefront()->ordered()->get(['id', 'slug', 'updated_at']);

        $products = Product::query()
            ->storefront()
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at', 'category_id']);

        return response()
            ->view('store.sitemap', compact('categories', 'products'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /account',
            'Disallow: /cart',
            'Disallow: /search',
            'Disallow: /livewire',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
