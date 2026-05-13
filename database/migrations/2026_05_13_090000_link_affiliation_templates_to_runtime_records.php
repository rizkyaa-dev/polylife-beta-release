<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'affiliation_template_id')) {
                $table->foreignId('affiliation_template_id')
                    ->nullable()
                    ->after('affiliation_name')
                    ->constrained('affiliation_templates')
                    ->nullOnDelete();
            }
        });

        Schema::table('admin_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_assignments', 'affiliation_template_id')) {
                $table->foreignId('affiliation_template_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('affiliation_templates')
                    ->nullOnDelete();
            }
        });

        Schema::table('affiliation_broadcast_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliation_broadcast_targets', 'affiliation_template_id')) {
                $table->foreignId('affiliation_template_id')
                    ->nullable()
                    ->after('broadcast_id')
                    ->constrained('affiliation_templates')
                    ->nullOnDelete();
            }
        });

        $this->backfillUsers();
        $this->backfillTable('admin_assignments');
        $this->backfillTable('affiliation_broadcast_targets');
    }

    public function down(): void
    {
        Schema::table('affiliation_broadcast_targets', function (Blueprint $table) {
            if (Schema::hasColumn('affiliation_broadcast_targets', 'affiliation_template_id')) {
                $table->dropConstrainedForeignId('affiliation_template_id');
            }
        });

        Schema::table('admin_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('admin_assignments', 'affiliation_template_id')) {
                $table->dropConstrainedForeignId('affiliation_template_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'affiliation_template_id')) {
                $table->dropConstrainedForeignId('affiliation_template_id');
            }
        });
    }

    private function backfillUsers(): void
    {
        DB::table('users')
            ->select('affiliation_type', 'affiliation_name')
            ->whereNotNull('affiliation_name')
            ->where('affiliation_name', '!=', '')
            ->distinct()
            ->orderBy('affiliation_name')
            ->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $templateId = $this->templateIdFor(
                        $this->nullableString($row->affiliation_type),
                        (string) $row->affiliation_name
                    );

                    DB::table('users')
                        ->where('affiliation_type', $row->affiliation_type)
                        ->where('affiliation_name', $row->affiliation_name)
                        ->whereNull('affiliation_template_id')
                        ->update(['affiliation_template_id' => $templateId]);
                }
            });
    }

    private function backfillTable(string $table): void
    {
        DB::table($table)
            ->select('affiliation_type', 'affiliation_name')
            ->whereNotNull('affiliation_name')
            ->where('affiliation_name', '!=', '')
            ->distinct()
            ->orderBy('affiliation_name')
            ->chunk(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $templateId = $this->findTemplateId(
                        $this->nullableString($row->affiliation_type),
                        (string) $row->affiliation_name
                    );

                    if (! $templateId) {
                        continue;
                    }

                    DB::table($table)
                        ->where('affiliation_type', $row->affiliation_type)
                        ->where('affiliation_name', $row->affiliation_name)
                        ->whereNull('affiliation_template_id')
                        ->update(['affiliation_template_id' => $templateId]);
                }
            });
    }

    private function templateIdFor(?string $type, string $name): int
    {
        $name = $this->normalizeName($name);
        $existingId = $this->findTemplateId($type, $name);

        if ($existingId) {
            return $existingId;
        }

        return (int) DB::table('affiliation_templates')->insertGetId([
            'affiliation_type' => $type,
            'affiliation_name' => $name,
            'aliases' => json_encode([]),
            'is_active' => true,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function findTemplateId(?string $type, string $name): ?int
    {
        $query = DB::table('affiliation_templates')
            ->where('affiliation_name', $this->normalizeName($name));

        $type === null
            ? $query->whereNull('affiliation_type')
            : $query->where('affiliation_type', $type);

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
};
