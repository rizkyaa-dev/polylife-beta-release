<?php

namespace App\Http\Requests\Endmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserVerificationDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'email_verified' => ['nullable', 'boolean'],
            'affiliation_type' => ['nullable', 'string', 'max:40'],
            'affiliation_name' => ['nullable', 'string', 'max:160'],
            'student_id_type' => ['nullable', 'string', 'max:32'],
            'student_id_number' => ['nullable', 'string', 'max:64'],
            'affiliation_status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'affiliation_verified_at' => ['nullable', 'date'],
            'affiliation_verified_by' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('is_admin', User::ADMIN_LEVEL_SUPER_ADMIN)
                        ->orWhere('role', 'super_admin');
                }),
            ],
        ];
    }
}
