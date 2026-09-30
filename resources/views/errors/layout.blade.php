{{-- Self-contained: no database, Vite or Livewire, so it renders even when those fail. --}}
@php($storeName = rescue(fn () => \App\Support\Store::name(), config('store.name'), false))
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ $storeName }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
        font-family:Tajawal,system-ui,-apple-system,"Segoe UI",sans-serif;background:#f9fafb;color:#111827;padding:24px;text-align:center}
        .card{background:#fff;border-radius:24px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:32px 24px;max-width:420px;width:100%}
        .code{font-size:56px;font-weight:800;color:#25743f;line-height:1;margin:0 0 8px}
        h1{font-size:22px;margin:0 0 8px}p{color:#4b5563;margin:0 0 24px;line-height:1.7}
        .btn{display:inline-block;background:#25743f;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px;margin:4px}
        .btn.secondary{background:#fff;color:#374151;border:1px solid #d1d5db}.brand{margin-top:20px;font-weight:700;color:#1e5d33}
        a:focus-visible{outline:2px solid #25743f;outline-offset:2px}
    </style>
</head>
<body>
    <main class="card">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="{{ url('/') }}">الصفحة الرئيسية</a>
        @yield('actions')
    </main>
    <div class="brand">{{ $storeName }}</div>
</body>
</html>
