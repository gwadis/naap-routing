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
            if (!Schema::hasColumn('users', 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0)->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            }
            if (!Schema::hasColumn('users', 'needs_password_change')) {
                $table->boolean('needs_password_change')->default(false)->after('locked_until');
            }
            if (!Schema::hasColumn('users', 'login_otp')) {
                $table->string('login_otp')->nullable()->after('needs_password_change');
            }
            if (!Schema::hasColumn('users', 'login_otp_expires_at')) {
                $table->timestamp('login_otp_expires_at')->nullable()->after('login_otp');
            }
            if (!Schema::hasColumn('users', 'login_otp_sent_at')) {
                $table->timestamp('login_otp_sent_at')->nullable()->after('login_otp_expires_at');
            }
            if (!Schema::hasColumn('users', 'recovery_email')) {
                $table->string('recovery_email')->nullable()->after('login_otp_sent_at');
            }
        });

        Schema::create('login_failures', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('username')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('user');
            $table->string('action');
            $table->string('ip_address', 45);
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device')->nullable();
            $table->string('affected_record')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
        Schema::dropIfExists('login_failures');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'needs_password_change',
                'login_otp',
                'login_otp_expires_at',
                'login_otp_sent_at',
                'recovery_email',
            ]);
        });
    }
};
