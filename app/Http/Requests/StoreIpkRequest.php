<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreIpkRequest extends IpkRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['semester'][] = Rule::unique('ipks', 'semester')->where(function ($query) {
            return $query->where('user_id', $this->user()?->id);
        });

        return $rules;
    }
}
