<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<string, array<int, array{name: string, columns: array<int, string>}>>
     */
    private function indexes(): array
    {
        return [
            'keuangans' => [
                ['name' => 'keuangans_user_tanggal_id_idx', 'columns' => ['user_id', 'tanggal', 'id']],
                ['name' => 'keuangans_user_jenis_tanggal_idx', 'columns' => ['user_id', 'jenis', 'tanggal']],
            ],
            'jadwals' => [
                ['name' => 'jadwals_user_range_idx', 'columns' => ['user_id', 'tanggal_selesai', 'tanggal_mulai', 'id']],
            ],
            'reminders' => [
                ['name' => 'reminders_user_aktif_waktu_idx', 'columns' => ['user_id', 'aktif', 'waktu_reminder', 'id']],
            ],
            'tugas' => [
                ['name' => 'tugas_user_created_id_idx', 'columns' => ['user_id', 'created_at', 'id']],
            ],
            'users' => [
                ['name' => 'users_admin_status_idx', 'columns' => ['is_admin', 'account_status']],
                ['name' => 'users_affiliation_created_idx', 'columns' => ['affiliation_status', 'created_at']],
                ['name' => 'users_affiliation_email_idx', 'columns' => ['affiliation_status', 'email_verified_at']],
            ],
            'affiliation_broadcasts' => [
                ['name' => 'aff_broadcast_visible_idx', 'columns' => ['status', 'published_at', 'target_mode', 'id']],
                ['name' => 'aff_broadcast_author_status_idx', 'columns' => ['created_by', 'status', 'id']],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $index) {
                if (Schema::hasIndex($tableName, $index['name']) || Schema::hasIndex($tableName, $index['columns'])) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->index($index['columns'], $index['name']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_reverse($indexes) as $index) {
                if (! Schema::hasIndex($tableName, $index['name'])) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['name']);
                });
            }
        }
    }
};
