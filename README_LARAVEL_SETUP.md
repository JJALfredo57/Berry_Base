# Simple Cake Shop — Laravel Version

Converted from plain PHP to Laravel (compatible sa Laravel 10 & 11).
Same functionality at design — walang nabago sa features at UI.

---

## 📁 Folder Structure (Laravel)

```
laravel_cakeshop/
├── app/
│   ├── Helpers/
│   │   └── CakeshopHelper.php          ← getSettings(), logActivity(), exportSql()
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   └── ForgotPasswordController.php
│   │   │   ├── Admin/
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── MessageController.php
│   │   │   │   ├── SettingsController.php
│   │   │   │   └── LogController.php
│   │   │   └── Customer/
│   │   │       ├── DashboardController.php
│   │   │       ├── CatalogController.php
│   │   │       ├── CheckoutController.php
│   │   │       ├── OrderController.php
│   │   │       ├── MessageController.php
│   │   │       ├── AddressController.php
│   │   │       ├── ProfileController.php
│   │   │       └── PaymentController.php
│   │   └── Middleware/
│   │       ├── AuthAdmin.php            ← require_admin()
│   │       └── AuthCustomer.php         ← require_customer()
│   └── Providers/
│       └── ViewServiceProvider.php      ← shares $settings & $bgCss to all views
├── bootstrap/
│   └── app.php                          ← Laravel 11: middleware aliases registered here
├── config/
│   └── paymongo.php                     ← PayMongo keys
├── database/
│   ├── migrations/
│   │   └── 2025_01_01_000001_create_cakeshop_tables.php
│   └── seeders/
│       └── DatabaseSeeder.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php            ← main layout (header + navbar + footer)
│       ├── auth/
│       │   ├── login.blade.php
│       │   ├── register.blade.php
│       │   └── forgot_password.blade.php
│       ├── admin/
│       │   ├── dashboard.blade.php
│       │   ├── products.blade.php
│       │   ├── orders.blade.php
│       │   ├── messages.blade.php
│       │   ├── thread.blade.php
│       │   ├── settings.blade.php
│       │   └── logs.blade.php  (included inside settings tab)
│       └── customer/
│           ├── dashboard.blade.php
│           ├── catalog.blade.php
│           ├── checkout.blade.php
│           ├── orders.blade.php
│           ├── messages.blade.php
│           ├── thread.blade.php
│           ├── addresses.blade.php
│           ├── profile.blade.php
│           └── payment_return.blade.php
├── routes/
│   └── web.php
└── .env.example
```

---

## 🚀 Setup Steps

### 1. I-install ang Laravel (kung wala pa)
```bash
composer create-project laravel/laravel cakeshop
```

### 2. I-copy ang mga files

I-copy lahat ng files mula sa `laravel_cakeshop/` folder patungo sa iyong Laravel project root.

### 3. I-setup ang .env
```bash
cp .env.example .env
php artisan key:generate
```

I-edit ang `.env`:
```
DB_DATABASE=cakeshop_db
DB_USERNAME=root
DB_PASSWORD=
PAYMONGO_SECRET_KEY=sk_test_xxxxx
PAYMONGO_PUBLIC_KEY=pk_test_xxxxx
```

### 4. Irehistro ang Middleware at ViewServiceProvider

**Para sa Laravel 11** — Ang `bootstrap/app.php` ay kasama na, i-replace lang ang existing `bootstrap/app.php` sa iyong project.

**Para sa Laravel 10** — Sa `app/Http/Kernel.php`, ilagay sa `$routeMiddleware`:
```php
'auth.admin'    => \App\Http\Middleware\AuthAdmin::class,
'auth.customer' => \App\Http\Middleware\AuthCustomer::class,
```

### 5. Irehistro ang ViewServiceProvider

Sa `config/app.php`, sa loob ng `providers` array:
```php
App\Providers\ViewServiceProvider::class,
```

### 6. I-run ang migrations at seeder
```bash
php artisan migrate
php artisan db:seed
```

### 7. I-link ang storage
```bash
php artisan storage:link
```
Tapos i-copy ang uploads mula sa original project:
```
uploads/products/  → storage/app/public/uploads/products/
uploads/branding/  → storage/app/public/uploads/branding/
uploads/messages/  → storage/app/public/uploads/messages/
```

### 8. I-run ang server
```bash
php artisan serve
```

Buksan ang: http://localhost:8000

---

## 🔑 Demo Accounts
- Admin: `admin` / `Admin@123`
- Customer: `juan` / `Customer@123`

---

## ✅ Features (walang nabago)
- OTP Registration & Login
- Forgot Password via OTP
- Admin: Dashboard, Products CRUD, Orders Management, Messages/Chat, Site Settings, Backup/Restore
- Customer: Catalog, Checkout (Pickup/Delivery), Leaflet Map, Orders Tracking, Chat with Admin, Addresses, Profile
- PayMongo GCash Integration
- Activity Logs
- Dynamic site settings (logo, colors, background)
