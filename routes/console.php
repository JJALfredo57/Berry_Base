<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use App\Helpers\CakeshopHelper;
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

Schedule::command('backup:run')->hourly();
Schedule::command('orders:expire-unpaid-deposits')->hourly();
