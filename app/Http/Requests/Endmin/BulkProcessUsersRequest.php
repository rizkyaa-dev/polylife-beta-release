<?php

namespace App\Http\Requests\Endmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkProcessUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'verify_email',
                'activate_accounts',
                'ban_accounts',
                'delete_accounts',
                'promote_to_admin',
                'demote_to_user',
            ])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
