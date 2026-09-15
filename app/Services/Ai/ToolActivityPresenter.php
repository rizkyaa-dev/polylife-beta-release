<?php

namespace App\Services\Ai;

class ToolActivityPresenter
{
    public function label(string $toolName): string
    {
        return match ($toolName) {
            'get_upcoming_schedule' => 'Membaca jadwal kuliah',
            'get_financial_summary' => 'Memeriksa ringkasan keuangan',
            'get_pending_tasks' => 'Memeriksa tugas yang belum selesai',
            'create_jadwal' => 'Menyiapkan perubahan jadwal',
            'create_keuangan' => 'Menyiapkan catatan keuangan',
            'create_todolist' => 'Menyiapkan item to-do',
            'create_catatan' => 'Menyiapkan catatan',
            default => 'Menggunakan alat bantu',
        };
    }
}
