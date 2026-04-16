<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catatans', function (Blueprint $table) {
            $table->string('preview_isi', 97)->default('')->after('isi');
            $table->index(['user_id', 'status_sampah', 'tanggal', 'id'], 'catatans_user_trash_tanggal_id_index');
            $table->index(['user_id', 'status_sampah', 'updated_at', 'id'], 'catatans_user_trash_updated_id_index');
        });

        $makePreview = static fn (?string $text): string => Str::limit(
            Str::squish(strip_tags((string) $text)),
            97,
            ''
        );

        DB::table('catatans')
            ->select(['id', 'isi'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($makePreview): void {
                foreach ($rows as $row) {
                    DB::table('catatans')
                        ->where('id', $row->id)
                        ->update([
                            'preview_isi' => $makePreview($row->isi),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('catatans', function (Blueprint $table) {
            $table->dropIndex('catatans_user_trash_tanggal_id_index');
            $table->dropIndex('catatans_user_trash_updated_id_index');
            $table->dropColumn('preview_isi');
        });
    }
};
