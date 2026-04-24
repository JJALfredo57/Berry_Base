# Cake Shop Laravel — Setup Guide

## Requirements
- PHP 8.1+
- Composer
- MySQL (XAMPP)
- Laravel 11

---

## Steps

### 1. Create Laravel project
```
composer create-project laravel/laravel cakeshop
cd cakeshop
```

### 2. Copy all files from this zip into your cakeshop folder

### 3. Configure .env
```
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
- Set `DB_DATABASE=cakeshop_db`, `DB_USERNAME`, `DB_PASSWORD`
- Set Gmail SMTP (see instructions below)

### 4. Register Middleware (Laravel 10 only)
In `app/Http/Kernel.php`, add to `$routeMiddleware`:
```php
'auth.admin'    => \App\Http\Middleware\AuthAdmin::class,
'auth.customer' => \App\Http\Middleware\AuthCustomer::class,
```
For Laravel 11, `bootstrap/app.php` already handles this.

### 5. Register ViewServiceProvider
In `config/app.php`, add to `providers` array:
```php
App\Providers\ViewServiceProvider::class,
```

### 6. Run migrations
```
php artisan migrate:fresh
```
(No seed needed — use /setup page to create admin)

### 7. Create storage link
```
php artisan storage:link
```

### 8. Create upload folders
```
mkdir -p storage/app/public/uploads/products
mkdir -p storage/app/public/uploads/branding
mkdir -p storage/app/public/uploads/messages
```

### 9. Start the server
```
php artisan serve
```

### 10. Open browser
Go to: http://localhost:8000
→ You'll be redirected to /setup to create your admin account.

---

## Gmail SMTP Setup (for OTP emails)

1. Go to your Google Account → **Security**
2. Enable **2-Step Verification** if not yet enabled
3. Go to **App Passwords** → Create one for "Mail"
4. Copy the 16-character app password
5. In `.env`:
   ```
   MAIL_USERNAME=your_gmail@gmail.com
   MAIL_PASSWORD=xxxx xxxx xxxx xxxx   (your app password)
   MAIL_FROM_ADDRESS=your_gmail@gmail.com
   ```

---

## Demo Accounts
These are no longer seeded. Create your admin via `/setup`, then register customers via `/register`.

---

## What's Fixed/New in this version

### Bug Fixes
- ✅ Forgot password "Email not found" — fixed (case-insensitive lookup)
- ✅ Logo not showing after upload — fixed (proper storage path)
- ✅ Image upload paths — fixed
- ✅ Back button after logout — fixed (no-cache headers + session flush)

### New Features
- ✅ Real OTP via Gmail SMTP
- ✅ Auto-fill customer info in checkout (name, email, phone)
- ✅ Order tracking timeline (Shopee-style: Pending→Confirmed→Preparing→Out for Delivery→Delivered)
- ✅ Message badge/icon in navbar with unread count
- ✅ Customer profile page (edit info + change password)
- ✅ Admin profile page (in Settings → Profile & Password)
- ✅ First-time setup page (/setup)
- ✅ Beautiful redesigned UI with Bootstrap Icons
- ✅ Dropdown navigation menus
- ✅ Filter tabs in orders page
- ✅ GCash (PayMongo) integration retained
