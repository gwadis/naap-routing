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
        Schema::table('document_routings', function (Blueprint $table) {
            if (!Schema::hasColumn('document_routings', 'scanned_at')) {
                $table->timestamp('scanned_at')->nullable()->after('received_at');
            }
            if (!Schema::hasColumn('document_routings', 'last_vpaa_alarm_at')) {
                $table->timestamp('last_vpaa_alarm_at')->nullable()->after('scanned_at');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'access_pin')) {
                $table->string('access_pin', 4)->nullable()->after('qr_scanned_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            $table->dropColumn(['scanned_at', 'last_vpaa_alarm_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['access_pin']);
        });
    }
};
