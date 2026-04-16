<?php

namespace App\Http\Requests\Matkul;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class MatkulRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $matkulId = $this->route('matkul')?->id;

        return [
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique('matkuls', 'kode')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($matkulId),
            ],
            'nama' => 'required|string|max:150',
            'kelas' => 'required|string|max:255',
            'dosen' => 'required|string|max:120',
            'semester' => 'required|integer|min:1|max:14',
            'sks' => 'required|integer|min:1|max:6',
            'hari' => 'required|string|max:255',
            'jam_mulai' => 'required|string|max:255',
            'jam_selesai' => 'required|string|max:255',
            'ruangan' => 'required|string|max:255',
            'warna_label' => 'required|string|max:20',
            'catatan' => 'required|string',
        ];
    }
}
