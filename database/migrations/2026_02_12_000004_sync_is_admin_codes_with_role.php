<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate legacy role strings into numeric is_admin codes.
        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['is_admin' => 1]);

        DB::table('users')
            ->where('role', 'admin')
            ->where('is_admin', '!=', 1)
            ->update(['is_admin' => 2]);

        DB::table('users')
            ->whereNotIn('is_admin', [0, 1, 2])
            ->update(['is_admin' => 0]);

        // Keep role string aligned for compatibility with existing UI/data.
        DB::table('users')
            ->where('is_admin', 1)
            ->update(['role' => 'super_admin']);

        DB::table('users')
            ->where('is_admin', 2)
            ->update(['role' => 'admin']);

        DB::table('users')
            ->where('is_admin', 0)
            ->update(['role' => 'user']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: cannot safely infer previous values after normalization.
    }
};
