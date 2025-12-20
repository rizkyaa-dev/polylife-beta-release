<?php

namespace Database\Seeders;

use App\Models\Keuangan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KeuanganSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', '1@2.com')->first();

        if (!$user) {
            $user = User::create([
                'name'     => 'testing',
                'email'    => '1@2.com',
                'password' => Hash::make('1'),
            ]);
        }

        $entries = [
            [
                'jenis'     => 'pemasukan',
                'kategori'  => 'Beasiswa',
                'nominal'   => 1500000,
                'deskripsi' => 'Pencairan beasiswa prestasi tahap 1',
                'tanggal'   => '2025-11-02',
            ],
            [
                'jenis'     => 'pengeluaran',
                'kategori'  => 'Buku',
                'nominal'   => 275000,
                'deskripsi' => 'Buku Struktur Data edisi terbaru',
                'tanggal'   => '2025-11-05',
            ],
            [
                'jenis'     => 'pengeluaran',
                'kategori'  => 'Konsumsi',
                'nominal'   => 180000,
                'deskripsi' => 'Snack sesi kerja kelompok',
                'tanggal'   => '2025-11-20',
            ],
        ];

        foreach ($entries as $entry) {
            Keuangan::updateOrCreate(
                [
                    'user_id'  => $user->id,
                    'tanggal'  => $entry['tanggal'],
                    'deskripsi'=> $entry['deskripsi'],
                    'nominal'  => $entry['nominal'],
                ],
                array_merge($entry, ['user_id' => $user->id])
            );
        }
    }
}
