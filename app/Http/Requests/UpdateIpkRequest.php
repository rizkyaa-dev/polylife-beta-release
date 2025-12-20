<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateIpkRequest extends IpkRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['semester'][] = Rule::unique('ipks', 'semester')
            ->ignore($this->route('ipk')?->id)
            ->where(function ($query) {
                return $query->where('user_id', $this->user()?->id);
            });

        return $rules;
    }
}
