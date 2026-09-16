<?php

namespace App\Services\Ai\Tools;

use App\Models\NilaiMutu;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;

final class GetGradeScaleTool implements AiToolInterface
{
    public function name(): string
    {
        return 'get_grade_scale';
    }

    public function description(): string
    {
        return 'Mengambil aturan konversi nilai mutu aktif milik pengguna dan menentukan huruf/bobot untuk nilai angka.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => ['type' => 'object', 'properties' => [
                'score' => ['type' => 'number', 'description' => 'Nilai angka yang ingin dikonversi, opsional'],
            ]],
        ];
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(User $user, array $arguments): array
    {
        $scale = NilaiMutu::query()->where('user_id', $user->id)->where('is_active', true)->latest('id')->first();
        if (! $scale) {
            return ['configured' => false, 'message' => 'Aturan nilai mutu aktif belum diatur.'];
        }
        $grades = array_slice($scale->grades_plus_minus ?: $scale->grades_ab, 0, 30);
        $score = isset($arguments['score']) ? (float) $arguments['score'] : null;
        $match = $score === null ? null : collect($grades)->first(function (array $grade) use ($score): bool {
            $min = $grade['min_score'] ?? null;
            $max = $grade['max_score'] ?? null;

            return ($min === null || $score >= (float) $min) && ($max === null || $score <= (float) $max);
        });

        return [
            'configured' => true, 'kampus' => $scale->kampus, 'program_studi' => $scale->program_studi,
            'score' => $score, 'matched_grade' => $match, 'grades' => $grades,
        ];
    }
}
