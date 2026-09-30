# بيت المونة — ملاحظات للمطورين

- Arabic-only, RTL, mobile-first storefront for a Hebron supermarket. UI text is Arabic; code/comments English.
- Auth: single `users` table, login by **phone + password** (`App\Actions\Auth\AttemptLogin`). `users.type` = customer|admin;
  profile data lives in `customers` / `admins`. Phones are normalised to `05XXXXXXXX` (`App\Support\PhoneNumber`).
- Route protection: `auth` + `customer` for the account area, `auth` + `admin[:role,...]` for `/admin`.
- Prices: never trust the client. Always use `App\Services\Pricing\ProductPriceResolver`. Order lines are
  snapshots (`OrderItem::snapshotFrom`). Order status changes go through `Order::transitionTo()`.
- Nothing store-specific is hard-coded: categories, delivery zones and store settings (`StoreSetting::get()`) live in the DB.
- Livewire components are class-based (`app/Livewire`, views in `resources/views/livewire`).
- Run `vendor/bin/pint` and `php artisan test` before committing. Tests use in-memory SQLite; migrations must stay MySQL + SQLite compatible.
- Admin catalog (Phase 2): `app/Livewire/Admin/{Products,Categories,Offers,Banners}` (Index + Form each). Every catalog
  component uses `AuthorizesCatalog` (gate `manage-catalog` = super_admin|manager) — re-checked on every Livewire request.
- Images: always go through `App\Services\Media\ImageStorage` (random ULID names, `{dir}/thumbs/{name}.webp` thumbnails,
  GD re-encode) and validate with `App\Support\ImageRules`. Swap `ImageProcessor` binding to change the engine.
- Offer/banner state: `HasSchedule` trait (`scheduleStatus()`, `withScheduleStatus()`), never `is_active` alone.
- Products are soft deleted (`DeleteProduct` also disables their offers); categories with children/products can't be deleted.
- Admin model changes are logged to `audit_logs` via the `Auditable` trait.
- Storefront (Phase 3): controllers in `app/Http/Controllers/Store` render `resources/views/store/*` with
  `<x-layouts::app>` (same `layouts/app.blade.php` Livewire uses). Products shown to customers must use
  `Product::storefront()` (not hidden, not trashed, active category); prices via `$product->price()` (memoised resolver).
- Cart: `App\Services\Cart\CartService` only — guest carts by session token (`carts.session_id`), users by `user_id`,
  merged on the `Login` event. Quantities are integer thousandths via `QuantityRules` (min/step/stock).
  The browser only ever sends a product id + quantity (`add-to-cart` event handled by `Livewire\Store\CartDrawer`).
- `StorefrontCache` caches plain arrays (the cache refuses to unserialize objects) and is flushed on
  Category/Banner save/delete and on reorder.
