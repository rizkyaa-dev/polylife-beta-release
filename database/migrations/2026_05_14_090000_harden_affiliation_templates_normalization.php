<?php

use App\Support\Affiliation\AffiliationNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliation_templates', 'normalized_name')) {
                $table->string('normalized_name', 160)->nullable()->after('affiliation_name');
            }

            if (! Schema::hasColumn('affiliation_templates', 'merged_into_id')) {
                $table->foreignId('merged_into_id')
                    ->nullable()
                    ->after('is_active')
                    ->constrained('affiliation_templates')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('affiliation_templates', 'merged_by')) {
                $table->foreignId('merged_by')
                    ->nullable()
                    ->after('merged_into_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('affiliation_templates', 'merged_at')) {
                $table->timestamp('merged_at')->nullable()->after('merged_by');
            }
        });

        $normalizer = new AffiliationNormalizer();

        DB::table('affiliation_templates')
            ->select('id', 'affiliation_name')
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($normalizer): void {
                foreach ($templates as $template) {
                    DB::table('affiliation_templates')
                        ->where('id', $template->id)
                        ->update([
                            'normalized_name' => $normalizer->nameKey((string) $template->affiliation_name),
                        ]);
                }
            });

        Schema::table('affiliation_templates', function (Blueprint $table): void {
            $table->index(['affiliation_type', 'normalized_name'], 'affiliation_templates_type_normalized_index');
            $table->index(['is_active', 'merged_into_id'], 'affiliation_templates_active_merged_index');
        });
    }

    public function down(): void
    {
        Schema::table('affiliation_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('affiliation_templates', 'normalized_name')) {
                $table->dropIndex('affiliation_templates_type_normalized_index');
            }

            if (Schema::hasColumn('affiliation_templates', 'merged_into_id')) {
                $table->dropIndex('affiliation_templates_active_merged_index');
            }
        });

        Schema::table('affiliation_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('affiliation_templates', 'merged_at')) {
                $table->dropColumn('merged_at');
            }

            if (Schema::hasColumn('affiliation_templates', 'merged_by')) {
                $table->dropConstrainedForeignId('merged_by');
            }

            if (Schema::hasColumn('affiliation_templates', 'merged_into_id')) {
                $table->dropConstrainedForeignId('merged_into_id');
            }

            if (Schema::hasColumn('affiliation_templates', 'normalized_name')) {
                $table->dropColumn('normalized_name');
            }
        });
    }
};
