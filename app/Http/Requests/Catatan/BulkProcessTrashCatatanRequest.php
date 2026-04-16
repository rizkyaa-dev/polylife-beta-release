<?php

namespace App\Http\Requests\Catatan;

use Illuminate\Foundation\Http\FormRequest;

class BulkProcessTrashCatatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'catatan_ids' => ['required', 'array', 'min:1'],
            'catatan_ids.*' => ['integer', 'distinct'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
