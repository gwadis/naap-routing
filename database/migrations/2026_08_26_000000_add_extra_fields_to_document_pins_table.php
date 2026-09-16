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
        Schema::table('document_pins', function (Blueprint $table) {
            if (!Schema::hasColumn('document_pins', 'recipient_id')) {
                $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('document_pins', 'pin_code')) {
                $table->string('pin_code')->nullable();
            }
            if (!Schema::hasColumn('document_pins', 'used_at')) {
                $table->timestamp('used_at')->nullable();
            }
            if (!Schema::hasColumn('document_pins', 'verification_status')) {
                $table->string('verification_status')->default('pending'); // pending, verified, expired
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_pins', function (Blueprint $table) {
            $table->dropForeign(['recipient_id']);
            $table->dropColumn(['recipient_id', 'pin_code', 'used_at', 'verification_status']);
        });
    }
};
