<?php

namespace App\Http\Requests\Reminder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reminder_target' => ['required', Rule::in(['todolist', 'tugas', 'jadwal', 'kegiatan'])],
            'todolist_id' => 'nullable|exists:todolists,id',
            'tugas_id' => 'nullable|exists:tugas,id',
            'jadwal_id' => 'nullable|exists:jadwals,id',
            'kegiatan_id' => 'nullable|exists:kegiatans,id',
            'waktu_reminder' => 'required|date',
            'aktif' => 'boolean',
        ];
    }
}
