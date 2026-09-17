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
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('sla', 100)->default('Simple Transaction (3 Working Days)')->change();
            });
        } catch (\Throwable $e) {
            // Handled or already modified via AppServiceProvider
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('sla', ['Standard', 'Expedited', 'Critical'])->default('Standard')->change();
        });
    }
};
