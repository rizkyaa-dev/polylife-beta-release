<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ipks', 'semester')) {
            Schema::table('ipks', function (Blueprint $table) {
                $table->unsignedTinyInteger('semester')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ipks', 'semester')) {
            Schema::table('ipks', function (Blueprint $table) {
                $table->unsignedTinyInteger('semester')->nullable(false)->change();
            });
        }
    }
};
