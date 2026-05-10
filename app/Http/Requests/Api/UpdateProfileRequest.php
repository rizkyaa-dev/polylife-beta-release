<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'display_name' => $this->nullableTrimmedString('display_name'),
            'bio' => $this->nullableTrimmedString('bio'),
            'phone' => $this->nullableTrimmedString('phone'),
            'location' => $this->nullableTrimmedString('location'),
            'timezone' => $this->nullableTrimmedString('timezone'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'location' => ['nullable', 'string', 'max:120'],
            'theme_preference' => ['required', Rule::in(['system', 'light', 'dark'])],
            'timezone' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', Rule::in(['id', 'en'])],
        ];
    }

    private function nullableTrimmedString(string $key): mixed
    {
        $value = $this->input($key);
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
