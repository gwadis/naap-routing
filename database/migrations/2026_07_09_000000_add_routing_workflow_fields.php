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
            if (!Schema::hasColumn('document_routings', 'approval_type')) {
                $table->string('approval_type')->default('sequential')->after('status');
            }
            if (!Schema::hasColumn('document_routings', 'signature_required')) {
                $table->boolean('signature_required')->default(true)->after('approval_type');
            }
            if (!Schema::hasColumn('document_routings', 'forwarded_from_user_id')) {
                $table->unsignedBigInteger('forwarded_from_user_id')->nullable()->after('receiver_user_id');
                $table->foreign('forwarded_from_user_id')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('document_routings', 'forwarded_reason')) {
                $table->text('forwarded_reason')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            if (Schema::hasColumn('document_routings', 'forwarded_from_user_id')) {
                $table->dropForeign(['forwarded_from_user_id']);
                $table->dropColumn('forwarded_from_user_id');
            }
            $table->dropColumn(['approval_type', 'signature_required', 'forwarded_reason']);
        });
    }
};
