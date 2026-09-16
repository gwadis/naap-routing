<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds per-routing-step SLA tracking, sender tracking, and lifecycle timestamps.
     */
    public function up(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            // Who sent/routed the document to this step
            if (!Schema::hasColumn('document_routings', 'sender_user_id')) {
                $table->unsignedBigInteger('sender_user_id')->nullable()->after('receiver_user_id');
                $table->foreign('sender_user_id')->references('id')->on('users')->onDelete('set null');
            }

            // When the routing step was forwarded / released to the next step
            if (!Schema::hasColumn('document_routings', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('received_at');
            }

            // Human-readable action label for this step (e.g. "Received", "Approved", "Returned")
            if (!Schema::hasColumn('document_routings', 'action_label')) {
                $table->string('action_label')->nullable()->after('released_at');
            }

            // Per-step SLA tracking
            if (!Schema::hasColumn('document_routings', 'sla_hours')) {
                $table->unsignedInteger('sla_hours')->nullable()->after('action_label');
            }
            if (!Schema::hasColumn('document_routings', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('sla_hours');
            }
            if (!Schema::hasColumn('document_routings', 'sla_status')) {
                $table->string('sla_status')->default('on_time')->after('sla_due_at');
                // Values: 'on_time', 'near_due', 'overdue'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            $columns = ['released_at', 'action_label', 'sla_hours', 'sla_due_at', 'sla_status'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('document_routings', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('document_routings', 'sender_user_id')) {
                $table->dropForeign(['sender_user_id']);
                $table->dropColumn('sender_user_id');
            }
        });
    }
};
