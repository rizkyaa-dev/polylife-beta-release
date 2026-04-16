<?php

namespace App\Http\Requests\NilaiMutu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

abstract class NilaiMutuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kampus' => ['nullable', 'string', 'max:150'],
            'program_studi' => ['nullable', 'string', 'max:150'],
            'kurikulum' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'grade_mode' => ['required', 'in:grades_plus_minus,grades_ab'],
            'grades_plus_minus' => ['nullable', 'array'],
            'grades_plus_minus.*.letter' => ['nullable', 'string', 'max:3'],
            'grades_plus_minus.*.min_score' => ['nullable', 'numeric'],
            'grades_plus_minus.*.max_score' => ['nullable', 'numeric'],
            'grades_plus_minus.*.grade_point' => ['nullable', 'numeric'],
            'grades_ab' => ['nullable', 'array'],
            'grades_ab.*.letter' => ['nullable', 'string', 'max:3'],
            'grades_ab.*.min_score' => ['nullable', 'numeric'],
            'grades_ab.*.max_score' => ['nullable', 'numeric'],
            'grades_ab.*.grade_point' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeGrades(array $rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                $letter = strtoupper(trim((string) ($row['letter'] ?? '')));
                $min = isset($row['min_score']) && $row['min_score'] !== '' ? (float) $row['min_score'] : null;
                $max = isset($row['max_score']) && $row['max_score'] !== '' ? (float) $row['max_score'] : null;
                $point = isset($row['grade_point']) && $row['grade_point'] !== '' ? (float) $row['grade_point'] : null;

                return [
                    'letter' => $letter,
                    'min_score' => $min,
                    'max_score' => $max,
                    'grade_point' => $point,
                ];
            })
            ->filter(function (array $row) {
                return $row['letter'] !== ''
                    && ($row['min_score'] !== null || $row['max_score'] !== null || $row['grade_point'] !== null);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedPayload(): array
    {
        $validated = $this->validated();
        $mode = $validated['grade_mode'];

        $validated['is_active'] = $this->boolean('is_active');
        $validated['grades_plus_minus'] = $mode === 'grades_plus_minus'
            ? $this->sanitizeGrades($this->input('grades_plus_minus', []))
            : [];
        $validated['grades_ab'] = $mode === 'grades_ab'
            ? $this->sanitizeGrades($this->input('grades_ab', []))
            : [];

        return $validated;
    }
}
