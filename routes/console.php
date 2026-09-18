<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use App\Helpers\CakeshopHelper;
use App\Services\CustomerIdentityService;
use App\Services\BackupService;
use App\Services\RiderAssignmentService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:run {--force : Run even when automation is disabled or not yet due}', function (BackupService $backups) {
    $platform = DB::table('platform_settings')->first();
    $force = (bool) $this->option('force');

    if (!$platform) {
        $this->warn('No platform settings row found.');
        return 1;
    }

    if (!$force && empty($platform->backup_auto_enabled)) {
        $this->info('Backup automation is disabled.');
        return 0;
    }

    $frequency = in_array($platform->backup_frequency ?? 'daily', ['daily', 'weekly', 'monthly'], true)
        ? $platform->backup_frequency
        : 'daily';

    $lastRun = !empty($platform->backup_last_run_at) ? \Carbon\Carbon::parse($platform->backup_last_run_at) : null;
    $due = match ($frequency) {
        'weekly' => !$lastRun || $lastRun->lte(now()->subWeek()),
        'monthly' => !$lastRun || $lastRun->lte(now()->subMonth()),
        default => !$lastRun || $lastRun->lte(now()->subDay()),
    };

    if (!$force && !$due) {
        $this->info("Backup is not due yet ({$frequency}).");
        return 0;
    }

    try {
        $info = !empty($platform->backup_include_uploads)
            ? $backups->createFullBackup('auto')
            : $backups->createDatabaseBackup('auto');

        $deleted = $backups->pruneOldBackups((int) ($platform->backup_retention_count ?? 14));
        $message = "Created {$info['name']}" . ($deleted ? "; pruned {$deleted} old file(s)" : '');

        DB::table('platform_settings')->where('id', $platform->id)->update([
            'backup_last_run_at' => now(),
            'backup_last_status' => 'success',
            'backup_last_message' => $message,
            'updated_at' => now(),
        ]);

        CakeshopHelper::logActivity('system', 'superadmin', 'Automated Backup', $message);
        $this->info($message);

        return 0;
    } catch (\Throwable $e) {
        DB::table('platform_settings')->where('id', $platform->id)->update([
            'backup_last_run_at' => now(),
            'backup_last_status' => 'failed',
            'backup_last_message' => $e->getMessage(),
            'updated_at' => now(),
        ]);
        Log::error('Automated backup failed: ' . $e->getMessage(), ['exception' => $e]);
        $this->error('Automated backup failed: ' . $e->getMessage());

        return 1;
    }
})->purpose('Run the smart platform backup job');

Artisan::command('rider-assignments:expire', function (RiderAssignmentService $assignments) {
    $count = $assignments->expirePendingAssignments();
    $this->info("Expired {$count} pending rider assignment(s).");
    return 0;
})->purpose('Expire rider assignments that were not accepted in time');

Schedule::command('rider-assignments:expire')->everyMinute();

Artisan::command('cleanup:customer-data {--force : Actually delete customer accounts and customer activity data}', function () {
    $tables = [
        'order_addons',
        'order_tracking',
        'order_reviews',
        'custom_orders',
        'kitchen_tickets',
        'messages',
        'notifications',
        'customer_feedback',
        'product_daily_orders',
        'user_addresses',
        'password_resets',
        'sessions',
        'activity_logs',
        'orders',
    ];

    $counts = [];
    foreach ($tables as $table) {
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            $counts[$table] = DB::table($table)->count();
        }
    }

    $customerCount = \Illuminate\Support\Facades\Schema::hasTable('users')
        ? DB::table('users')->where('role', 'customer')->count()
        : 0;

    $this->table(['Data', 'Rows'], [
        ...collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all(),
        ['users(role=customer)', $customerCount],
    ]);

    if (!$this->option('force')) {
        $this->warn('Dry run only. Re-run with --force to delete these rows.');
        return 0;
    }

    DB::transaction(function () use ($tables) {
        foreach ($tables as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            DB::table('users')->where('role', 'customer')->delete();
        }
    });

    $this->info('Customer accounts and customer activity data have been deleted.');
    $this->warn('Website settings, sellers, shops, products, riders, delivery zones, and platform settings were not deleted.');

    return 0;
})->purpose('Delete customer accounts and customer activity data without deleting website setup data');

Artisan::command('orders:expire-unpaid-deposits {--hours=24 : Hours before unpaid deposit orders expire}', function () {
    $hours = max(1, (int) $this->option('hours'));
    $cutoff = now()->subHours($hours);

    $orders = DB::table('orders')
        ->where('status', 'Awaiting Deposit')
        ->where(fn ($q) => $q->whereNull('deposit_status')->orWhere('deposit_status', 'pending'))
        ->where('created_at', '<=', $cutoff)
        ->select('id', 'track_code', 'guest_phone')
        ->get();

    if ($orders->isEmpty()) {
        $this->info('No unpaid deposit orders to expire.');
        return 0;
    }

    DB::transaction(function () use ($orders, $hours) {
        foreach ($orders as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'Cancelled',
                'cancel_status' => 'accepted',
                'cancel_reason' => "Auto-expired: deposit was not paid within {$hours} hours.",
                'cancel_admin_note' => 'Automatically cancelled by abuse protection.',
                'updated_at' => now(),
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => 'Cancelled',
                'notes' => "Order auto-expired because the required deposit was not paid within {$hours} hours.",
                'created_at' => now(),
            ]);
        }
    });

    $this->info('Expired ' . $orders->count() . ' unpaid deposit order(s).');
    return 0;
})->purpose('Cancel unpaid Awaiting Deposit orders after the configured timeout');

Artisan::command('customer:link-orders-by-phone {phone : Customer phone number to match} {--user-id= : Specific customer user id to receive the orders} {--name= : Expected customer full name, used as a safety check} {--apply : Actually update matching records}', function () {
    /** @var CustomerIdentityService $identity */
    $identity = app(CustomerIdentityService::class);
    $phone = (string) $this->argument('phone');
    $normalizedPhone = $identity->normalizePhone($phone);
    $variants = $identity->phoneVariants($phone);
    $expectedName = trim((string) $this->option('name'));
    $selectedUserId = trim((string) $this->option('user-id'));
    $apply = (bool) $this->option('apply');

    if (!$normalizedPhone || !$variants) {
        $this->error('Invalid Philippine mobile number. Example: 09278460673');
        return 1;
    }

    foreach (['users', 'orders'] as $table) {
        if (!Schema::hasTable($table)) {
            $this->error("Missing required table: {$table}");
            return 1;
        }
    }

    $customerQuery = DB::table('users')
        ->where('role', 'customer')
        ->whereIn('phone', $variants)
        ->select('id', 'fullname', 'email', 'phone', 'role', 'created_at')
        ->orderByDesc('created_at');

    if ($selectedUserId !== '') {
        $customerQuery->where('id', $selectedUserId);
    }

    $customers = $customerQuery->get();

    if ($expectedName !== '') {
        $matchingByName = $customers->filter(fn ($user) => strcasecmp((string) $user->fullname, $expectedName) === 0)->values();
        if ($matchingByName->isNotEmpty()) {
            $customers = $matchingByName;
        }
    }

    if ($customers->isEmpty()) {
        $this->error('No customer account found with this phone number' . ($expectedName ? " and name {$expectedName}." : '.'));
        $this->line('Phone variants checked: ' . implode(', ', $variants));
        return 1;
    }

    if ($customers->count() > 1) {
        $this->error('More than one customer account matched. Re-run with --user-id=<id> to choose exactly one.');
        $this->table(['ID', 'Full Name', 'Email', 'Phone', 'Created'], $customers->map(fn ($user) => [
            $user->id,
            $user->fullname,
            $user->email,
            $user->phone,
            $user->created_at,
        ])->all());
        return 1;
    }

    $customer = $customers->first();
    $customerName = $expectedName !== '' ? $expectedName : (string) $customer->fullname;

    $orders = DB::table('orders')
        ->whereIn('guest_phone', $variants)
        ->select('id', 'guest_name', 'guest_phone', 'user_id', 'status', 'track_code', 'created_at')
        ->orderBy('created_at')
        ->get();

    $conflicts = $orders->filter(fn ($order) => filled($order->user_id) && (string) $order->user_id !== (string) $customer->id)->values();
    $linkable = $orders->reject(fn ($order) => filled($order->user_id) && (string) $order->user_id !== (string) $customer->id)->values();
    $orderIds = $linkable->pluck('id')->values()->all();

    $customOrderCount = 0;
    if (Schema::hasTable('custom_orders') && $orderIds) {
        $customOrderCount = DB::table('custom_orders')
            ->whereIn('order_id', $orderIds)
            ->count();
    }

    $reviewCount = 0;
    if (Schema::hasTable('order_reviews') && $orderIds) {
        $reviewCount = DB::table('order_reviews')
            ->whereIn('order_id', $orderIds)
            ->count();
    }

    $mobileNotificationCount = 0;
    if (Schema::hasTable('mobile_notifications') && $orderIds) {
        $mobileNotificationCount = DB::table('mobile_notifications')
            ->whereIn('order_id', $orderIds)
            ->count();
    }

    $this->info(($apply ? 'APPLY' : 'DRY RUN') . ' - link historical orders to customer');
    $this->line('Phone input: ' . $phone);
    $this->line('Normalized phone: ' . $normalizedPhone);
    $this->line('Variants checked: ' . implode(', ', $variants));
    $this->newLine();
    $this->table(['Customer ID', 'Full Name', 'Email', 'Phone'], [[
        $customer->id,
        $customer->fullname,
        $customer->email,
        $customer->phone,
    ]]);

    $this->line('Matching orders: ' . $orders->count());
    $this->line('Linkable orders: ' . $linkable->count());
    $this->line('Conflicting orders skipped: ' . $conflicts->count());
    $this->line('Related custom orders: ' . $customOrderCount);
    $this->line('Related reviews: ' . $reviewCount);
    $this->line('Related mobile notifications: ' . $mobileNotificationCount);

    if ($linkable->isNotEmpty()) {
        $this->newLine();
        $this->table(['Order ID', 'Current Name', 'Phone', 'Current User', 'Status', 'Track Code', 'Created'], $linkable->map(fn ($order) => [
            $order->id,
            $order->guest_name,
            $order->guest_phone,
            $order->user_id ?: '(guest)',
            $order->status,
            $order->track_code,
            $order->created_at,
        ])->all());
    }

    if ($conflicts->isNotEmpty()) {
        $this->newLine();
        $this->warn('Skipped orders already linked to a different account:');
        $this->table(['Order ID', 'Name', 'Phone', 'Existing User', 'Status', 'Track Code'], $conflicts->map(fn ($order) => [
            $order->id,
            $order->guest_name,
            $order->guest_phone,
            $order->user_id,
            $order->status,
            $order->track_code,
        ])->all());
    }

    if (!$apply) {
        $this->warn('Dry run only. Re-run with --apply to update these records.');
        return 0;
    }

    if (empty($orderIds)) {
        $this->info('No linkable orders to update.');
        return 0;
    }

    DB::transaction(function () use ($orderIds, $customer, $customerName, $normalizedPhone) {
        DB::table('orders')->whereIn('id', $orderIds)->update([
            'user_id' => (string) $customer->id,
            'guest_name' => $customerName,
            'guest_phone' => $normalizedPhone,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('custom_orders')) {
            DB::table('custom_orders')->whereIn('order_id', $orderIds)->update([
                'user_id' => (string) $customer->id,
                'guest_name' => $customerName,
                'guest_phone' => $normalizedPhone,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('order_reviews')) {
            $reviewUpdate = ['user_id' => (string) $customer->id];
            if (Schema::hasColumn('order_reviews', 'guest_name')) {
                $reviewUpdate['guest_name'] = $customerName;
            }
            if (Schema::hasColumn('order_reviews', 'updated_at')) {
                $reviewUpdate['updated_at'] = now();
            }
            DB::table('order_reviews')->whereIn('order_id', $orderIds)->update($reviewUpdate);
        }

        if (Schema::hasTable('mobile_notifications')) {
            DB::table('mobile_notifications')->whereIn('order_id', $orderIds)->update([
                'role' => 'customer',
                'user_id' => (string) $customer->id,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('activity_logs')) {
            DB::table('activity_logs')->insert([
                'user_id' => (string) $customer->id,
                'role' => 'customer',
                'action' => 'Link Historical Orders',
                'details' => 'Linked historical guest orders by phone ' . $normalizedPhone . ': ' . implode(', ', $orderIds),
                'ip_address' => null,
                'created_at' => now(),
            ]);
        }
    });

    $this->info('Updated ' . count($orderIds) . ' order(s). Antonio/customer account should now see them in My Orders.');
    $this->warn('Tracking codes were preserved.');
    return 0;
})->purpose('Safely link historical guest orders to a customer account by phone');

Artisan::command('customer:unverify {phone : Customer phone number to match} {--user-id= : Specific customer user id to unverify} {--name= : Expected customer full name, used as a safety check} {--reason=Temporarily unverified by admin request. : Reason saved on the verification record} {--apply : Actually update the latest approved verification}', function () {
    /** @var CustomerIdentityService $identity */
    $identity = app(CustomerIdentityService::class);
    $phone = (string) $this->argument('phone');
    $variants = $identity->phoneVariants($phone);
    $expectedName = trim((string) $this->option('name'));
    $selectedUserId = trim((string) $this->option('user-id'));
    $reason = trim((string) $this->option('reason')) ?: 'Temporarily unverified by admin request.';
    $apply = (bool) $this->option('apply');

    if (!$variants) {
        $this->error('Invalid Philippine mobile number. Example: 09278460673');
        return 1;
    }

    foreach (['users', 'customer_verifications'] as $table) {
        if (!Schema::hasTable($table)) {
            $this->error("Missing required table: {$table}");
            return 1;
        }
    }

    $customerQuery = DB::table('users')
        ->where('role', 'customer')
        ->whereIn('phone', $variants)
        ->select('id', 'fullname', 'email', 'phone', 'role', 'is_verified', 'created_at')
        ->orderByDesc('created_at');

    if ($selectedUserId !== '') {
        $customerQuery->where('id', $selectedUserId);
    }

    $customers = $customerQuery->get();

    if ($expectedName !== '') {
        $matchingByName = $customers->filter(fn ($user) => strcasecmp((string) $user->fullname, $expectedName) === 0)->values();
        if ($matchingByName->isNotEmpty()) {
            $customers = $matchingByName;
        }
    }

    if ($customers->isEmpty()) {
        $this->error('No customer account found with this phone number' . ($expectedName ? " and name {$expectedName}." : '.'));
        $this->line('Phone variants checked: ' . implode(', ', $variants));
        return 1;
    }

    if ($customers->count() > 1) {
        $this->error('More than one customer account matched. Re-run with --user-id=<id> to choose exactly one.');
        $this->table(['ID', 'Full Name', 'Email', 'Phone', 'Login Approved', 'Created'], $customers->map(fn ($user) => [
            $user->id,
            $user->fullname,
            $user->email,
            $user->phone,
            $user->is_verified ? 'yes' : 'no',
            $user->created_at,
        ])->all());
        return 1;
    }

    $customer = $customers->first();
    $latest = DB::table('customer_verifications')
        ->where('user_id', $customer->id)
        ->orderByDesc('id')
        ->first();

    $this->info(($apply ? 'APPLY' : 'DRY RUN') . ' - unverify customer valid ID status');
    $this->table(['Customer ID', 'Full Name', 'Email', 'Phone', 'Login Approved'], [[
        $customer->id,
        $customer->fullname,
        $customer->email,
        $customer->phone,
        $customer->is_verified ? 'yes' : 'no',
    ]]);

    if (!$latest) {
        $this->warn('This customer has no valid ID verification record yet.');
        return 0;
    }

    $this->table(['Verification ID', 'Current Status', 'Reviewed At', 'Current Reason'], [[
        $latest->id,
        $latest->status,
        $latest->reviewed_at ?? '',
        $latest->rejection_reason ?? '',
    ]]);

    if ($latest->status !== 'approved') {
        $this->warn('Latest verification is already not approved, so the customer is not verified.');
        return 0;
    }

    if (!$apply) {
        $this->warn('Dry run only. Re-run with --apply to update this verification to rejected.');
        return 0;
    }

    DB::transaction(function () use ($latest, $customer, $reason) {
        DB::table('customer_verifications')->where('id', $latest->id)->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
                'receiver_role' => 'customer',
                'receiver_user_id' => $customer->id,
                'title' => 'Verification Needs Review',
                'message' => 'Your valid ID verification was reset for review. Reason: ' . $reason,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $this->info('Customer valid ID verification was reset to rejected. Login approval was not changed.');
    return 0;
})->purpose('Safely reset a customer valid ID verification to unverified by phone');

Schedule::command('backup:run')->hourly();
Schedule::command('orders:expire-unpaid-deposits')->hourly();
