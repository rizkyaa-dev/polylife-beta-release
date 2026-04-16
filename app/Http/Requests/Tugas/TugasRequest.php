<?php

namespace App\Http\Requests\Tugas;

use Illuminate\Foundation\Http\FormRequest;

abstract class TugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nama_tugas' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'deadline' => ['required', 'date'],
            'status_selesai' => ['nullable', 'boolean'],
        ];
    }
}
