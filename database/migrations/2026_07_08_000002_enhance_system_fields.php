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
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id')->nullable()->unique()->after('email');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'access_pin')) {
                try {
                    $table->string('access_pin', 255)->nullable()->change();
                } catch (\Throwable $e) {}
            } else {
                $table->string('access_pin', 255)->nullable()->after('qr_scanned_at');
            }

            if (!Schema::hasColumn('documents', 'file_hash')) {
                $table->string('file_hash')->nullable()->after('file_path');
            }

            if (!Schema::hasColumn('documents', 'version')) {
                $table->integer('version')->default(1)->after('file_hash');
            }

            if (!Schema::hasColumn('documents', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->unique()->after('version');
            }

            if (!Schema::hasColumn('documents', 'is_confidential')) {
                $table->boolean('is_confidential')->default(false)->after('tracking_number');
            }

            if (!Schema::hasColumn('documents', 'category')) {
                $table->string('category')->nullable()->after('is_confidential');
            }

            if (!Schema::hasColumn('documents', 'tags')) {
                $table->string('tags')->nullable()->after('category');
            }

            if (!Schema::hasColumn('documents', 'forwarded_at')) {
                $table->timestamp('forwarded_at')->nullable()->after('received_at');
            }

            if (!Schema::hasColumn('documents', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('forwarded_at');
            }

            if (!Schema::hasColumn('documents', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('documents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('completed_at');
            }

            if (!Schema::hasColumn('documents', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('archived_at');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'browser')) {
                $table->string('browser')->nullable()->after('ip');
            }
            if (!Schema::hasColumn('activity_logs', 'os')) {
                $table->string('os')->nullable()->after('browser');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_id')) {
                $table->dropColumn('employee_id');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            $columns = [
                'file_hash',
                'version',
                'tracking_number',
                'is_confidential',
                'category',
                'tags',
                'forwarded_at',
                'approved_at',
                'completed_at',
                'archived_at',
                'rejected_at'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('documents', 'access_pin')) {
                $table->string('access_pin', 4)->nullable()->change();
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'browser')) {
                $table->dropColumn('browser');
            }
            if (Schema::hasColumn('activity_logs', 'os')) {
                $table->dropColumn('os');
            }
        });
    }
};
