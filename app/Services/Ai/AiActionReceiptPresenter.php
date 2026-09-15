<?php

namespace App\Services\Ai;

final class AiActionReceiptPresenter
{
    /**
     * @return array{message: string, destination_label: ?string, destination_url: ?string}
     */
    public function present(string $toolName): array
    {
        return match ($toolName) {
            'create_catatan' => $this->receipt('Catatan berhasil disimpan.', 'Buka Catatan', 'catatan.index'),
            'create_todolist' => $this->receipt('Item to-do berhasil disimpan.', 'Buka To-Do', 'todolist.index'),
            'create_keuangan' => $this->receipt('Transaksi berhasil disimpan.', 'Buka Keuangan', 'keuangan.index'),
            'create_jadwal' => $this->receipt('Jadwal berhasil disimpan.', 'Buka Jadwal', 'jadwal.index'),
            default => [
                'message' => 'Perubahan berhasil disimpan ke workspace.',
                'destination_label' => null,
                'destination_url' => null,
            ],
        };
    }

    /**
     * @return array{message: string, destination_label: string, destination_url: string}
     */
    private function receipt(string $message, string $label, string $routeName): array
    {
        return [
            'message' => $message,
            'destination_label' => $label,
            'destination_url' => route($routeName),
        ];
    }
}
