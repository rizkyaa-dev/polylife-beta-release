<?php

namespace App\Http\Requests\Endmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');

                if ($this->input('affiliation_status') !== 'verified') {
                    return;
                }

                if (filled($user?->affiliation_template_id) || filled($user?->affiliation_name)) {
                    return;
                }

                $validator->errors()->add(
                    'affiliation_status',
                    'Lengkapi afiliasi user sebelum menandai sebagai terverifikasi.'
                );
            },
        ];
    }
}
