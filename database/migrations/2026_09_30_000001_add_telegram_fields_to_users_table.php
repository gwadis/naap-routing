<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('phone');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->timestamp('telegram_connected_at')->nullable()->after('telegram_username');
            $table->string('telegram_connection_status')->default('disconnected')->after('telegram_connected_at');
            $table->string('telegram_connect_token', 64)->nullable()->index()->after('telegram_connection_status');
            $table->timestamp('telegram_connect_token_expires_at')->nullable()->after('telegram_connect_token');
            $table->boolean('telegram_notif_announcements')->default(true)->after('telegram_connect_token_expires_at');
            $table->boolean('telegram_notif_documents')->default(true)->after('telegram_notif_announcements');
            $table->boolean('telegram_notif_urgent')->default(true)->after('telegram_notif_documents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
                'telegram_connected_at',
                'telegram_connection_status',
                'telegram_connect_token',
                'telegram_connect_token_expires_at',
                'telegram_notif_announcements',
                'telegram_notif_documents',
                'telegram_notif_urgent',
            ]);
        });
    }
};
