<?php

namespace App\Http\Requests\Endmin;

use Illuminate\Foundation\Http\FormRequest;

class SuspendAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
