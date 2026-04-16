<?php

namespace App\Http\Requests\Catatan;

use Illuminate\Foundation\Http\FormRequest;

abstract class CatatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:150'],
            'isi' => ['required', 'string'],
            'tanggal' => ['required', 'date'],
        ];
    }
}
