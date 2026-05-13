<?php

namespace App\Http\Requests\Endmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'email_verified' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'account_status' => ['required', Rule::in(['active', 'banned'])],
            'ban_reason_code' => ['required_if:account_status,banned', 'nullable', 'string', 'max:50'],
            'ban_reason_text' => ['nullable', 'string', 'max:1000'],
            'affiliation_template_id' => ['nullable', 'integer', Rule::exists('affiliation_templates', 'id')->where('is_active', true)],
            'affiliation_type' => ['nullable', 'string', 'max:40'],
            'affiliation_name' => ['nullable', 'string', 'max:160'],
            'student_id_type' => ['nullable', 'string', 'max:32'],
            'student_id_number' => ['nullable', 'string', 'max:64'],
            'affiliation_status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var User|null $user */
        $user = $this->route('user');

        if (! $user) {
            return;
        }

        $this->merge([
            'name' => $this->input('name', $user->name),
            'email_verified' => $this->has('email_verified') ? $this->boolean('email_verified') : (bool) $user->email_verified_at,
            'affiliation_template_id' => $this->input('affiliation_template_id', $user->affiliation_template_id),
            'affiliation_type' => $this->input('affiliation_type', $user->affiliation_type),
            'affiliation_name' => $this->input('affiliation_name', $user->affiliation_name),
            'student_id_type' => $this->input('student_id_type', $user->student_id_type),
            'student_id_number' => $this->input('student_id_number', $user->student_id_number),
            'affiliation_status' => $this->input('affiliation_status', $user->affiliation_status ?: 'pending'),
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('affiliation_status') !== 'verified') {
                    return;
                }

                if (! $this->filled('affiliation_template_id') && ! $this->filled('affiliation_name')) {
                    $validator->errors()->add(
                        'affiliation_template_id',
                        'Pilih template afiliasi atau isi nama afiliasi sebelum memverifikasi.'
                    );
                }
            },
        ];
    }
}
