<?php

namespace App\Http\Requests\Catatan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTrashCatatanRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', Rule::in(['latest', 'oldest', 'title_asc', 'title_desc'])],
        ];
    }
}
