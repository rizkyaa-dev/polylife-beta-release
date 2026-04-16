<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

abstract class KeuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'kategori' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['required', 'date'],
        ];
    }
}
