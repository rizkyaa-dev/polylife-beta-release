<?php

namespace App\Http\Requests;

use App\Models\Ipk;
use Illuminate\Validation\Rule;

class StoreIpkRequest extends IpkRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['semester'][] = Rule::unique('ipks', 'semester')->where(function ($query) {
            return $query->where('user_id', $this->user()?->id);
        });
        $rules['semester'][] = function (string $attribute, $value, $fail) {
            $userId = $this->user()?->id;
            if (! $userId) {
                return;
            }

            $lastSemester = Ipk::forUser($userId)->whereNotNull('semester')->max('semester');
            $expected = $lastSemester ? ($lastSemester + 1) : 1;

            if ((int) $value !== (int) $expected) {
                $fail("Semester harus berurutan. Semester berikutnya: {$expected}.");
            }
        };

        return $rules;
    }
}
