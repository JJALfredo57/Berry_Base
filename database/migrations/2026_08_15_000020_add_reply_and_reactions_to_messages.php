<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('messages') && !Schema::hasColumn('messages', 'reply_to_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('image_path')->index();
            });
        }

        if (!Schema::hasTable('message_reactions')) {
            Schema::create('message_reactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('message_id')->index();
                $table->string('actor_role', 30)->index();
                $table->string('actor_id', 64)->nullable()->index();
                $table->string('guest_key', 80)->nullable()->index();
                $table->string('reaction', 40);
                $table->timestamps();
                $table->unique(['message_id', 'actor_role', 'actor_id', 'guest_key'], 'msg_react_actor_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'reply_to_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('reply_to_id');
            });
        }
    }
};
