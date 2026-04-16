<?php

namespace App\Http\Requests\Endmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email_verified' => ['nullable', 'boolean'],
            'affiliation_status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
        ];
    }
}
