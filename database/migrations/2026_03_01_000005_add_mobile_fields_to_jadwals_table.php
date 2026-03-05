<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table): void {
            $table->string('title', 180)->nullable()->after('catatan_tambahan');
            $table->string('location', 180)->nullable()->after('title');
            $table->time('start_time')->nullable()->after('location');
            $table->time('end_time')->nullable()->after('start_time');
            $table->boolean('is_completed')->default(false)->after('end_time');

            $table->index(['user_id', 'is_completed']);
            $table->index(['user_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwals', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_completed']);
            $table->dropIndex(['user_id', 'start_time']);

            $table->dropColumn([
                'title',
                'location',
                'start_time',
                'end_time',
                'is_completed',
            ]);
        });
    }
};
