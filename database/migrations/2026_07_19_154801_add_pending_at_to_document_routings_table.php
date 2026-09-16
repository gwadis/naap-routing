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
            if (!Schema::hasColumn('document_routings', 'pending_at')) {
                $table->timestamp('pending_at')->nullable()->after('received_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            if (Schema::hasColumn('document_routings', 'pending_at')) {
                $table->dropColumn('pending_at');
            }
        });
    }
};
