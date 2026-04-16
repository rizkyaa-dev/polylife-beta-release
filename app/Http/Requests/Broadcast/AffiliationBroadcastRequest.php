<?php

namespace App\Http\Requests\Broadcast;

use App\Services\BroadcastImageService;
use App\Models\AffiliationBroadcast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

abstract class AffiliationBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(bool $includeRemoveImage = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'image' => [
                'nullable',
                'file',
                'max:8192',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    if (! BroadcastImageService::supportsUpload($value)) {
                        $fail('Format gambar harus berupa '.BroadcastImageService::supportedFormatsLabel().'.');
                    }
                },
            ],
            'target_mode' => ['nullable', Rule::in([
                AffiliationBroadcast::TARGET_MODE_AFFILIATION,
                AffiliationBroadcast::TARGET_MODE_GLOBAL,
            ])],
            'targets' => ['nullable', 'array'],
            'targets.*' => ['string', 'max:220'],
            'send_push' => ['nullable', 'boolean'],
            'publish_now' => ['nullable', 'boolean'],
        ];

        if ($includeRemoveImage) {
            $rules['remove_image'] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
