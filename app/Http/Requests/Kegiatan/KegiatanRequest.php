<?php

namespace App\Http\Requests\Kegiatan;

use Illuminate\Foundation\Http\FormRequest;

abstract class KegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'jadwal_id' => ['required', 'exists:jadwals,id'],
            'nama_kegiatan' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'tanggal_deadline' => ['required', 'date'],
            'waktu' => ['required', 'date_format:H:i'],
            'status' => ['required', 'string', 'max:50'],
        ];
    }
}
