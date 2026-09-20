<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_surprise_delivery')) {
                $table->boolean('is_surprise_delivery')->default(false)->after('delivery_barangay');
            }
            if (!Schema::hasColumn('orders', 'recipient_name')) {
                $table->string('recipient_name', 120)->nullable()->after('is_surprise_delivery');
            }
            if (!Schema::hasColumn('orders', 'recipient_phone')) {
                $table->string('recipient_phone', 30)->nullable()->after('recipient_name');
            }
            if (!Schema::hasColumn('orders', 'recipient_address')) {
                $table->text('recipient_address')->nullable()->after('recipient_phone');
            }
            if (!Schema::hasColumn('orders', 'recipient_latitude')) {
                $table->decimal('recipient_latitude', 10, 7)->nullable()->after('recipient_address');
            }
            if (!Schema::hasColumn('orders', 'recipient_longitude')) {
                $table->decimal('recipient_longitude', 10, 7)->nullable()->after('recipient_latitude');
            }
            if (!Schema::hasColumn('orders', 'gift_message')) {
                $table->text('gift_message')->nullable()->after('recipient_longitude');
            }
            if (!Schema::hasColumn('orders', 'sender_display_name')) {
                $table->string('sender_display_name', 120)->nullable()->after('gift_message');
            }
            if (!Schema::hasColumn('orders', 'hide_sender_name')) {
                $table->boolean('hide_sender_name')->default(false)->after('sender_display_name');
            }
            if (!Schema::hasColumn('orders', 'surprise_contact_policy')) {
                $table->string('surprise_contact_policy', 40)->nullable()->after('hide_sender_name');
            }
            if (!Schema::hasColumn('orders', 'delivery_instructions')) {
                $table->text('delivery_instructions')->nullable()->after('surprise_contact_policy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'delivery_instructions',
                'surprise_contact_policy',
                'hide_sender_name',
                'sender_display_name',
                'gift_message',
                'recipient_longitude',
                'recipient_latitude',
                'recipient_address',
                'recipient_phone',
                'recipient_name',
                'is_surprise_delivery',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
