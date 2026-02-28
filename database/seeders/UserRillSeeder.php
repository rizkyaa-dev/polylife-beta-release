<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRillSeeder extends Seeder
{
    public function run(): void
    {
        $universities = $this->realUniversityNames();
        $totalUniversities = count($universities);
        $password = Hash::make('1');
        $now = now();

        for ($i = 1; $i <= 100; $i++) {
            $campusIndex = ($i - 1) % $totalUniversities;
            $campusName = $universities[$campusIndex];
            $name = $this->generateRealisticName($i);
            $email = $this->generateRealisticEmail($name, $i);

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => $now,
                    'is_admin' => User::ADMIN_LEVEL_USER,
                    'role' => 'user',
                    'account_status' => 'active',
                    'affiliation_type' => 'university',
                    'affiliation_name' => $campusName,
                    'student_id_type' => 'nim',
                    'student_id_number' => $this->generateFictionalNim($i, $campusIndex + 1),
                    'affiliation_status' => 'verified',
                    'affiliation_verified_at' => $now,
                    'affiliation_verified_by' => null,
                    'banned_at' => null,
                    'banned_by' => null,
                    'ban_reason_code' => null,
                    'ban_reason_text' => null,
                ]
            );
        }

        for ($i = 1; $i <= 10; $i++) {
            $campusIndex = ($i - 1) % $totalUniversities;
            $campusName = $universities[$campusIndex];
            $sequence = 100 + $i;
            $name = $this->generateRealisticName($sequence);
            $email = $this->generateRealisticEmail($name, $sequence);

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => $now,
                    'is_admin' => User::ADMIN_LEVEL_ADMIN,
                    'role' => 'admin',
                    'account_status' => 'active',
                    'affiliation_type' => 'university',
                    'affiliation_name' => $campusName,
                    'student_id_type' => 'nim',
                    'student_id_number' => $this->generateFictionalNim($sequence, $campusIndex + 1),
                    'affiliation_status' => 'verified',
                    'affiliation_verified_at' => $now,
                    'affiliation_verified_by' => null,
                    'banned_at' => null,
                    'banned_by' => null,
                    'ban_reason_code' => null,
                    'ban_reason_text' => null,
                ]
            );
        }
    }

    private function generateRealisticName(int $sequence): string
    {
        $firstNames = [
            'Andi', 'Budi', 'Citra', 'Dewi', 'Eka', 'Farhan', 'Gita', 'Hadi', 'Indra', 'Jihan',
            'Kevin', 'Laras', 'Maya', 'Nanda', 'Oki', 'Putri', 'Raka', 'Salsa', 'Teguh', 'Umar',
            'Vina', 'Wahyu', 'Yusuf', 'Zahra', 'Aulia', 'Bagas', 'Chandra', 'Dinda', 'Fajar', 'Nabila',
        ];

        $lastNames = [
            'Pratama', 'Saputra', 'Wijaya', 'Nugraha', 'Kusuma', 'Ramadhan', 'Putri', 'Sari', 'Mahendra', 'Fadillah',
            'Permata', 'Hidayat', 'Maulana', 'Wulandari', 'Setiawan', 'Kirana', 'Rahmawati', 'Firmansyah', 'Ananda', 'Purnomo',
            'Utami', 'Hardiansyah', 'Octaviani', 'Kurniawan', 'Maulida', 'Susanto', 'Aditya', 'Anggraini', 'Hakim', 'Azzahra',
        ];

        $first = $firstNames[($sequence - 1) % count($firstNames)];
        $last = $lastNames[intdiv($sequence - 1, count($firstNames)) % count($lastNames)];

        return $first.' '.$last;
    }

    private function generateRealisticEmail(string $name, int $sequence): string
    {
        $domains = [
            'gmail.com',
            'yahoo.com',
            'outlook.com',
            'hotmail.com',
            'icloud.com',
            'mail.com',
        ];

        $slug = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();

        if ($slug === '') {
            $slug = 'pengguna';
        }

        $domain = $domains[($sequence - 1) % count($domains)];

        return sprintf('%s.%03d@%s', $slug, $sequence, $domain);
    }

    private function generateFictionalNim(int $sequence, int $campusCode): string
    {
        $entryYear = 19 + ($sequence % 7); // 19-25

        return sprintf('%02d%03d%05d', $entryYear, $campusCode, $sequence);
    }

    private function realUniversityNames(): array
    {
        return [
            'Universitas Indonesia',
            'Universitas Gadjah Mada',
            'Institut Teknologi Bandung',
            'Institut Teknologi Sepuluh Nopember',
            'Universitas Airlangga',
            'Universitas Diponegoro',
            'Universitas Brawijaya',
            'Universitas Padjadjaran',
            'Universitas Hasanuddin',
            'Universitas Sebelas Maret',
            'Universitas Negeri Yogyakarta',
            'Universitas Negeri Malang',
            'Universitas Sumatera Utara',
            'Universitas Andalas',
            'Universitas Udayana',
            'Universitas Negeri Semarang',
            'Universitas Jenderal Soedirman',
            'Universitas Pendidikan Indonesia',
            'Universitas Islam Indonesia',
            'Politeknik Negeri Semarang',
        ];
    }
}
