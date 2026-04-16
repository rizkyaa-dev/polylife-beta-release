<?php

namespace App\Http\Requests\Endmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'account_status' => ['required', Rule::in(['active', 'banned'])],
            'ban_reason_code' => ['required_if:account_status,banned', 'nullable', 'string', 'max:50'],
            'ban_reason_text' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
