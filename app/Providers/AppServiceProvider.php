<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce HTTPS if accessed via HTTPS, reverse proxy (e.g. Herd, Ngrok, Coolify, Cloudflare), or APP_URL is HTTPS
        if (
            str_starts_with(config('app.url'), 'https://') ||
            request()->isSecure() ||
            request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
        }

        // Auto-heal legacy database schemas in production if migrations have not run yet
        if (!app()->environment('testing')) {
            try {
                // 1. Auto-heal documents table
                if (\Illuminate\Support\Facades\Schema::hasTable('documents')) {
                    $docCols = \Illuminate\Support\Facades\Schema::getColumnListing('documents');
                    
                    if (in_array('sla', $docCols)) {
                        $slaType = \Illuminate\Support\Facades\Schema::getColumnType('documents', 'sla');
                        if ($slaType === 'enum') {
                            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `documents` MODIFY `sla` VARCHAR(100) NOT NULL DEFAULT 'Simple Transaction (3 Working Days)'");
                        }
                    }

                    \Illuminate\Support\Facades\Schema::table('documents', function (\Illuminate\Database\Schema\Blueprint $table) use ($docCols) {
                        if (!in_array('file_hash', $docCols)) {
                            $table->string('file_hash', 64)->nullable()->after('file_path');
                        }
                        if (!in_array('version', $docCols)) {
                            $table->integer('version')->default(1);
                        }
                        if (!in_array('tracking_number', $docCols)) {
                            $table->string('tracking_number')->nullable();
                        }
                        if (!in_array('is_confidential', $docCols)) {
                            $table->boolean('is_confidential')->default(false);
                        }
                        if (!in_array('category', $docCols)) {
                            $table->string('category')->nullable();
                        }
                        if (!in_array('tags', $docCols)) {
                            $table->string('tags')->nullable();
                        }
                        if (!in_array('access_pin', $docCols)) {
                            $table->string('access_pin', 255)->nullable();
                        }
                        if (!in_array('destination_offices', $docCols)) {
                            $table->json('destination_offices')->nullable();
                        }
                        if (!in_array('receiver_user_id', $docCols)) {
                            $table->unsignedBigInteger('receiver_user_id')->nullable();
                        }
                        if (!in_array('uploaded_at', $docCols)) {
                            $table->timestamp('uploaded_at')->nullable();
                        }
                        if (!in_array('processed_at', $docCols)) {
                            $table->timestamp('processed_at')->nullable();
                        }
                        if (!in_array('due_date', $docCols)) {
                            $table->timestamp('due_date')->nullable();
                        }
                        if (!in_array('qr_id', $docCols)) {
                            $table->string('qr_id')->nullable();
                        }
                        if (!in_array('routing_history', $docCols)) {
                            $table->json('routing_history')->nullable();
                        }
                        if (!in_array('qr_status', $docCols)) {
                            $table->string('qr_status')->nullable();
                        }
                        if (!in_array('uuid', $docCols)) {
                            $table->string('uuid', 36)->nullable();
                        }
                    });
                }

                // 2. Auto-heal document_routings table
                if (\Illuminate\Support\Facades\Schema::hasTable('document_routings')) {
                    $routCols = \Illuminate\Support\Facades\Schema::getColumnListing('document_routings');
                    \Illuminate\Support\Facades\Schema::table('document_routings', function (\Illuminate\Database\Schema\Blueprint $table) use ($routCols) {
                        if (!in_array('sender_user_id', $routCols)) {
                            $table->unsignedBigInteger('sender_user_id')->nullable();
                        }
                        if (!in_array('approval_type', $routCols)) {
                            $table->string('approval_type')->default('sequential');
                        }
                        if (!in_array('signature_required', $routCols)) {
                            $table->boolean('signature_required')->default(true);
                        }
                        if (!in_array('sort_order', $routCols)) {
                            $table->integer('sort_order')->default(1);
                        }
                        if (!in_array('action_label', $routCols)) {
                            $table->string('action_label')->nullable();
                        }
                        if (!in_array('sla_hours', $routCols)) {
                            $table->unsignedInteger('sla_hours')->nullable();
                        }
                        if (!in_array('sla_due_at', $routCols)) {
                            $table->timestamp('sla_due_at')->nullable();
                        }
                        if (!in_array('sla_status', $routCols)) {
                            $table->string('sla_status')->default('on_time');
                        }
                        if (!in_array('pending_at', $routCols)) {
                            $table->timestamp('pending_at')->nullable();
                        }
                    });
                }

                // 3. Auto-heal activity_logs table
                if (\Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
                    $actCols = \Illuminate\Support\Facades\Schema::getColumnListing('activity_logs');
                    \Illuminate\Support\Facades\Schema::table('activity_logs', function (\Illuminate\Database\Schema\Blueprint $table) use ($actCols) {
                        if (!in_array('browser', $actCols)) {
                            $table->string('browser')->nullable();
                        }
                        if (!in_array('os', $actCols)) {
                            $table->string('os')->nullable();
                        }
                    });
                }
            } catch (\Throwable $e) {
                // Silently ignore if DB connection or table is unavailable during early bootstrap
                \Illuminate\Support\Facades\Log::warning('AppServiceProvider auto-heal schema notice: ' . $e->getMessage());
            }
        }
    }
}
