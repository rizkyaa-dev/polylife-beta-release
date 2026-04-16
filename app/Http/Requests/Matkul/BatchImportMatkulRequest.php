<?php

namespace App\Http\Requests\Matkul;

use Illuminate\Foundation\Http\FormRequest;

class BatchImportMatkulRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'raw_data' => 'required|string|min:10',
            'default_semester' => 'required|integer|min:1|max:14',
            'default_dosen' => 'required|string|max:120',
            'default_warna_label' => 'required|string|regex:/^#?[0-9a-fA-F]{3,6}$/',
            'catatan_prefix' => 'nullable|string|max:255',
        ];
    }
}
