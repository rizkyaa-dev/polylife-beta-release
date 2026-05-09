<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'catatans',
        'todolists',
        'jadwals',
        'keuangans',
        'reminders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'sync_uuid')) {
                    $table->uuid('sync_uuid')->nullable()->after('id');
                }

                if (! Schema::hasColumn($tableName, 'server_version')) {
                    $table->unsignedBigInteger('server_version')->default(1)->after('sync_uuid');
                }

                if (! Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletes()->after('updated_at');
                }
            });

            DB::table($tableName)
                ->whereNull('sync_uuid')
                ->orderBy('id')
                ->lazyById()
                ->each(function ($row) use ($tableName): void {
                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->update(['sync_uuid' => (string) Str::uuid()]);
                });

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unique(['user_id', 'sync_uuid'], $tableName.'_user_sync_uuid_unique');
                $table->index(['user_id', 'updated_at', 'id'], $tableName.'_user_updated_id_sync_idx');
                $table->index(['user_id', 'deleted_at'], $tableName.'_user_deleted_sync_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasIndex($tableName, $tableName.'_user_sync_uuid_unique')) {
                    $table->dropUnique($tableName.'_user_sync_uuid_unique');
                }

                if (Schema::hasIndex($tableName, $tableName.'_user_updated_id_sync_idx')) {
                    $table->dropIndex($tableName.'_user_updated_id_sync_idx');
                }

                if (Schema::hasIndex($tableName, $tableName.'_user_deleted_sync_idx')) {
                    $table->dropIndex($tableName.'_user_deleted_sync_idx');
                }

                $table->dropColumn(['sync_uuid', 'server_version', 'deleted_at']);
            });
        }
    }
};
