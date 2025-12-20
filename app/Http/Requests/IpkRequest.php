<?php

namespace App\Http\Requests;

use App\Models\Ipk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

abstract class IpkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'semester' => ['required', 'integer', 'min:1', 'max:14'],
            'academic_year' => ['nullable', 'regex:/^\\d{4}(\\/\\d{4})?$/'],
            'ips_actual' => ['required', 'numeric', 'min:0', 'max:4'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'academic_year' => $this->normalizeAcademicYear($this->input('academic_year')),
        ]);
    }

    public function payload(?Ipk $existing = null): array
    {
        $validated = $this->validated();

        $validated['target_mode'] = 'ips';
        $validated['status'] = 'final';
        $validated['ips_target'] = null;
        $validated['ipk_target'] = null;

        return $validated;
    }

    protected function normalizeAcademicYear(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $sanitized = trim($value);
        $sanitized = str_replace(['\\', '-', '.'], '/', $sanitized);

        if (! preg_match('/^\d{4}(\/\d{4})?$/', $sanitized)) {
            return $value;
        }

        return $sanitized;
    }
}
