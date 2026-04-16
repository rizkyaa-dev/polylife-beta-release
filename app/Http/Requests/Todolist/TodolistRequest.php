<?php

namespace App\Http\Requests\Todolist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TodolistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nama_item' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'boolean'],
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_date' => [
                Rule::requiredIf(fn () => $this->boolean('reminder_enabled')),
                'nullable',
                'date',
            ],
            'reminder_time' => [
                Rule::requiredIf(fn () => $this->boolean('reminder_enabled')),
                'nullable',
                'date_format:H:i',
            ],
        ];
    }
}
