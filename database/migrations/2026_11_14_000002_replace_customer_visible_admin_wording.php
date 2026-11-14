<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('order_tracking') && Schema::hasColumn('order_tracking','notes')) DB::table('order_tracking')->where('notes','like','%Super Admin%')->update(['notes'=>DB::raw("REPLACE(notes, 'Super Admin', 'Berry Base')")]);
        if (Schema::hasTable('orders') && Schema::hasColumn('orders','cancel_admin_note')) DB::table('orders')->where('cancel_admin_note','like','%Super Admin%')->update(['cancel_admin_note'=>DB::raw("REPLACE(cancel_admin_note, 'Super Admin', 'Berry Base')")]);
        if (Schema::hasTable('notifications')) DB::table('notifications')->where('receiver_role','customer')->where('message','like','%Super Admin%')->update(['message'=>DB::raw("REPLACE(message, 'Super Admin', 'Berry Base')")]);
    }
    public function down(): void { /* Copy cleanup is intentionally non-destructive and not reverted. */ }
};
