<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Simple informational pages. Contact details come from store settings;
 * the legal pages are placeholders until the approved text is supplied.
 */
class PageController extends Controller
{
    public function about(): View
    {
        return view('store.pages.about');
    }

    public function contact(): View
    {
        return view('store.pages.contact');
    }

    public function privacy(): View
    {
        return view('store.pages.legal', ['title' => 'سياسة الخصوصية']);
    }

    public function terms(): View
    {
        return view('store.pages.legal', ['title' => 'الشروط والأحكام']);
    }
}
