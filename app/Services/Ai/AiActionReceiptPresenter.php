<?php

namespace App\Services\Ai;

final class AiActionReceiptPresenter
{
    /**
     * @return array{message: string, acknowledgement: string, destination_label: ?string, destination_url: ?string}
     */
    public function present(string $toolName): array
    {
        return match ($toolName) {
            'create_catatan' => $this->receipt('Catatan berhasil disimpan.', 'Sip, catatannya sudah tersimpan. Kalau mau, kita bisa lanjut merapikan atau menambahkan isinya.', 'Buka Catatan', 'catatan.index'),
            'create_todolist' => $this->receipt('Item to-do berhasil disimpan.', 'Sip, to-do-nya sudah masuk. Tinggal lanjut saat kamu siap.', 'Buka To-Do', 'todolist.index'),
            'create_keuangan' => $this->receipt('Transaksi berhasil disimpan.', 'Sip, transaksinya sudah tercatat. Ringkasan keuanganmu ikut diperbarui.', 'Buka Keuangan', 'keuangan.index'),
            'create_jadwal' => $this->receipt('Jadwal berhasil disimpan.', 'Sip, jadwalnya sudah tersimpan. Semoga agendanya berjalan lancar.', 'Buka Jadwal', 'jadwal.index'),
            'create_tugas' => $this->receipt('Tugas berhasil disimpan.', 'Sip, tugasnya sudah tercatat. Kamu bisa lanjut mengatur prioritas atau remindernya.', 'Buka Tugas', 'tugas.index'),
            'manage_tugas' => $this->receipt('Tugas berhasil diperbarui.', 'Sip, perubahan tugasnya sudah tersimpan.', 'Buka Tugas', 'tugas.index'),
            'create_reminder' => $this->receipt('Reminder berhasil disimpan.', 'Sip, remindernya sudah siap. Nanti PolyLife akan mengingatkan sesuai waktunya.', 'Buka Reminder', 'reminder.index'),
            'manage_todolist' => $this->receipt('Item to-do berhasil diperbarui.', 'Sip, perubahan to-do-nya sudah tersimpan.', 'Buka To-Do', 'todolist.index'),
            'update_catatan' => $this->receipt('Catatan berhasil diperbarui.', 'Sip, perubahan catatannya sudah tersimpan.', 'Buka Catatan', 'catatan.index'),
            'set_finance_budget' => $this->receipt('Anggaran berhasil disimpan.', 'Sip, anggarannya sudah diperbarui. Kamu bisa cek pemakaiannya kapan saja.', 'Buka Anggaran', 'keuangan.anggaran'),
            'create_course' => $this->receipt('Mata kuliah berhasil disimpan.', 'Sip, mata kuliahnya sudah masuk ke semester kamu.', 'Buka Mata Kuliah', 'matkul.index'),
            'create_kegiatan' => $this->receipt('Kegiatan berhasil disimpan.', 'Sip, kegiatannya sudah ditambahkan ke jadwal.', 'Buka Jadwal', 'jadwal.index'),
            'record_academic_result' => $this->receipt('Hasil akademik berhasil disimpan.', 'Sip, hasil akademiknya sudah tercatat dan ringkasan IPK ikut diperbarui.', 'Buka IPK', 'ipk.index'),
            'update_jadwal', 'duplicate_schedule' => $this->receipt('Jadwal berhasil diperbarui.', 'Sip, perubahan jadwalnya sudah tersimpan.', 'Buka Jadwal', 'jadwal.index'),
            'update_kegiatan' => $this->receipt('Kegiatan berhasil diperbarui.', 'Sip, perubahan kegiatannya sudah tersimpan.', 'Buka Jadwal', 'jadwal.index'),
            'update_reminder' => $this->receipt('Reminder berhasil diperbarui.', 'Sip, perubahan remindernya sudah tersimpan.', 'Buka Reminder', 'reminder.index'),
            'snooze_reminder' => $this->receipt('Reminder berhasil ditunda.', 'Sip, remindernya sudah dijadwalkan ulang.', 'Buka Reminder', 'reminder.index'),
            'update_keuangan' => $this->receipt('Transaksi berhasil diperbarui.', 'Sip, koreksi transaksinya sudah tersimpan.', 'Buka Keuangan', 'keuangan.index'),
            'update_course', 'copy_course_to_semester' => $this->receipt('Mata kuliah berhasil diperbarui.', 'Sip, perubahan mata kuliahnya sudah tersimpan.', 'Buka Mata Kuliah', 'matkul.index'),
            'update_tugas' => $this->receipt('Tugas berhasil diperbarui.', 'Sip, detail tugasnya sudah diperbarui.', 'Buka Tugas', 'tugas.index'),
            'mark_announcement_read' => $this->receipt('Pengumuman ditandai sudah dibaca.', 'Sip, pengumumannya sudah ditandai dibaca.', 'Buka Pengumuman', 'pengumuman.index'),
            'archive_note' => $this->receipt('Catatan dipindahkan ke sampah.', 'Sip, catatannya sudah diarsipkan dan masih bisa dipulihkan.', 'Buka Catatan', 'catatan.index'),
            'restore_note' => $this->receipt('Catatan berhasil dipulihkan.', 'Sip, catatannya sudah kembali ke daftar aktif.', 'Buka Catatan', 'catatan.index'),
            default => [
                'message' => 'Perubahan berhasil disimpan ke workspace.',
                'acknowledgement' => 'Sip, perubahannya sudah tersimpan. Ada lagi yang ingin kamu bereskan?',
                'destination_label' => null,
                'destination_url' => null,
            ],
        };
    }

    /**
     * @return array{message: string, acknowledgement: string, destination_label: string, destination_url: string}
     */
    private function receipt(string $message, string $acknowledgement, string $label, string $routeName): array
    {
        return [
            'message' => $message,
            'acknowledgement' => $acknowledgement,
            'destination_label' => $label,
            'destination_url' => route($routeName),
        ];
    }
}
