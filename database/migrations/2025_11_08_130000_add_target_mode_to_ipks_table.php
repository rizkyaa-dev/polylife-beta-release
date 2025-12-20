<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ipks', 'target_mode')) {
            return;
        }

        Schema::table('ipks', function (Blueprint $table) {
            $table->enum('target_mode', ['ips', 'ipk'])
                ->default('ips')
                ->after('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ipks', 'target_mode')) {
            return;
        }

        Schema::table('ipks', function (Blueprint $table) {
            $table->dropColumn('target_mode');
        });
    }
};
