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
        if (!Schema::hasColumn('activity_logs', 'user_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Backfill existing activity logs to associate with immutable user IDs
        try {
            $users = \App\Models\User::all(['id', 'name', 'username']);
            foreach ($users as $user) {
                \Illuminate\Support\Facades\DB::table('activity_logs')
                    ->whereNull('user_id')
                    ->where(function ($q) use ($user) {
                        $q->where('user', $user->name);
                        if (!empty($user->username)) {
                            $q->orWhere('user', $user->username);
                        }
                    })
                    ->update(['user_id' => $user->id]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ActivityLog backfill skipped during migration: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'user_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
