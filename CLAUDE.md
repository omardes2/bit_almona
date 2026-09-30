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
