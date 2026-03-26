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
            $table->boolean('is_blocked')->default(false)->after('lancore_synced_at');
            $table->string('block_reason')->nullable()->after('is_blocked');
            $table->timestamp('blocked_at')->nullable()->after('block_reason');
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete()->after('blocked_at');
            $table->timestamp('timed_out_until')->nullable()->after('blocked_by');
            $table->string('timeout_reason')->nullable()->after('timed_out_until');
            $table->foreignId('timed_out_by')->nullable()->constrained('users')->nullOnDelete()->after('timeout_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blocked_by']);
            $table->dropForeign(['timed_out_by']);
            $table->dropColumn([
                'is_blocked',
                'block_reason',
                'blocked_at',
                'blocked_by',
                'timed_out_until',
                'timeout_reason',
                'timed_out_by',
            ]);
        });
    }
};
