<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('catatans', 'show_preview')) {
            return;
        }

        Schema::table('catatans', function (Blueprint $table) {
            $table->boolean('show_preview')->default(false)->after('preview_isi');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('catatans', 'show_preview')) {
            return;
        }

        Schema::table('catatans', function (Blueprint $table) {
            $table->dropColumn('show_preview');
        });
    }
};
