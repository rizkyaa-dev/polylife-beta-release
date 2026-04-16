<?php

namespace App\Http\Requests\Jadwal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

abstract class JadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in(['kuliah', 'libur', 'uts', 'uas', 'lomba', 'lainnya'])],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'catatan_tambahan' => ['nullable', 'string', 'max:500'],
            'matkul_ids' => ['nullable', 'array'],
            'matkul_ids.*' => [
                'nullable',
                Rule::exists('matkuls', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'matkul_create' => ['nullable', 'boolean'],
            'matkul_nama' => ['required_if:matkul_create,1', 'nullable', 'string', 'max:120'],
            'matkul_kode' => ['nullable', 'string', 'max:20'],
            'matkul_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
        ];
    }

    public function jadwalData(): array
    {
        return collect($this->validated())->except([
            'matkul_ids',
            'matkul_create',
            'matkul_kode',
            'matkul_nama',
            'matkul_semester',
        ])->toArray();
    }

    public function selectedMatkulIds(): array
    {
        return $this->validated('matkul_ids', []);
    }

    public function shouldCreateMatkul(): bool
    {
        return (bool) $this->validated('matkul_create', false);
    }

    public function matkulFields(): array
    {
        return collect($this->validated())
            ->only(['matkul_kode', 'matkul_nama', 'matkul_semester'])
            ->toArray();
    }
}
