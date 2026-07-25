<?php

namespace Database\Seeders;

use App\Models\Keuangan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KeuanganStatistikSeeder extends Seeder
{
    private const USER_EMAIL = '1@2.c';

    private const MARKER = 'seed:keuangan-statistik';

    public function run(): void
    {
        $year = $this->targetYear();
        $user = $this->resolveUser();
        $records = $this->buildYearlyRecords($user->id, $year);

        DB::transaction(function () use ($user, $year, $records): void {
            Keuangan::withTrashed()
                ->where('user_id', $user->id)
                ->whereYear('tanggal', $year)
                ->where('deskripsi', 'like', '%'.self::MARKER.'%')
                ->forceDelete();

            Keuangan::query()->insert($records);
        });

        $this->command?->info(sprintf(
            'Seeded %d transaksi keuangan %d untuk %s.',
            count($records),
            $year,
            self::USER_EMAIL
        ));
    }

    private function targetYear(): int
    {
        $configured = env('KEUANGAN_STATISTIK_SEED_YEAR');

        return is_numeric($configured) ? (int) $configured : (int) now()->year;
    }

    private function resolveUser(): User
    {
        return User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'name' => 'User',
                'password' => Hash::make('1'),
                'email_verified_at' => now(),
                'is_admin' => User::ADMIN_LEVEL_USER,
                'role' => 'user',
                'account_status' => 'active',
                'affiliation_status' => 'pending',
                'banned_at' => null,
                'banned_by' => null,
                'ban_reason_code' => null,
                'ban_reason_text' => null,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildYearlyRecords(int $userId, int $year): array
    {
        $records = [];

        $monthlyAllowance = [
            1 => 2500000, 2 => 2500000, 3 => 2600000, 4 => 2600000,
            5 => 2500000, 6 => 2700000, 7 => 2600000, 8 => 2600000,
            9 => 2650000, 10 => 2550000, 11 => 2600000, 12 => 2800000,
        ];

        $monthlyFood = [
            1 => 1260000, 2 => 1185000, 3 => 1240000, 4 => 1320000,
            5 => 1215000, 6 => 1280000, 7 => 1250000, 8 => 1300000,
            9 => 1270000, 10 => 1235000, 11 => 1265000, 12 => 1370000,
        ];

        $transport = [
            1 => 260000, 2 => 240000, 3 => 280000, 4 => 310000,
            5 => 270000, 6 => 285000, 7 => 250000, 8 => 300000,
            9 => 290000, 10 => 275000, 11 => 285000, 12 => 330000,
        ];

        $seasonalIncome = [
            2 => [
                ['Dana UKT dari orang tua', 2500000, 8],
            ],
            3 => [
                ['Beasiswa prestasi triwulan', 750000, 15],
            ],
            6 => [
                ['Honor freelance desain poster', 850000, 19],
                ['Beasiswa prestasi triwulan', 750000, 24],
            ],
            9 => [
                ['Honor asisten praktikum', 900000, 12],
                ['Beasiswa prestasi triwulan', 750000, 25],
            ],
            12 => [
                ['Bonus proyek akhir semester', 1200000, 18],
                ['Beasiswa prestasi triwulan', 750000, 26],
            ],
        ];

        $seasonalExpense = [
            1 => [
                ['Akademik', 'Beli buku referensi awal semester', 420000, 12],
            ],
            2 => [
                ['Akademik', 'Pembayaran UKT semester genap', 2500000, 10],
            ],
            4 => [
                ['Transportasi', 'Tiket mudik Lebaran', 680000, 3],
                ['Hadiah', 'Parcel keluarga kecil', 350000, 6],
            ],
            6 => [
                ['Akademik', 'Cetak laporan dan jilid', 260000, 21],
            ],
            8 => [
                ['Akademik', 'Pembayaran UKT semester ganjil', 2500000, 9],
                ['Akademik', 'Perlengkapan praktikum', 520000, 17],
            ],
            9 => [
                ['Elektronik', 'Servis laptop untuk kuliah', 750000, 20],
            ],
            12 => [
                ['Hiburan', 'Liburan akhir semester hemat', 850000, 22],
                ['Hadiah', 'Kado keluarga akhir tahun', 420000, 27],
            ],
        ];

        foreach (range(1, 12) as $month) {
            $records[] = $this->record($userId, 'pemasukan', 'Kiriman Orang Tua', $monthlyAllowance[$month], 'Kiriman bulanan untuk biaya hidup', $year, $month, 1);

            foreach ($seasonalIncome[$month] ?? [] as [$category, $amount, $day]) {
                $records[] = $this->record($userId, 'pemasukan', $category, $amount, $category, $year, $month, $day);
            }

            $records[] = $this->record($userId, 'pengeluaran', 'Kos', 900000, 'Sewa kos bulanan', $year, $month, 2);
            $records[] = $this->record($userId, 'pengeluaran', 'Internet & Pulsa', 145000, 'Paket data dan iuran internet', $year, $month, 5);
            $records[] = $this->record($userId, 'pengeluaran', 'Laundry', 120000 + (($month % 3) * 15000), 'Laundry pakaian rutin', $year, $month, 11);
            $records[] = $this->record($userId, 'pengeluaran', 'Transportasi', $transport[$month], 'Transport kampus, organisasi, dan pulang kos', $year, $month, 16);
            $records[] = $this->record($userId, 'pengeluaran', 'Kesehatan', 70000 + (($month % 4) * 20000), 'Vitamin dan kebutuhan kesehatan ringan', $year, $month, 18);
            $records[] = $this->record($userId, 'pengeluaran', 'Hiburan', 180000 + (($month % 5) * 35000), 'Nonton, kopi, atau nongkrong hemat', $year, $month, 24);
            $records[] = $this->record($userId, 'pengeluaran', 'Organisasi', 90000 + (($month % 2) * 50000), 'Iuran dan kegiatan organisasi', $year, $month, 26);

            foreach ($this->splitWeekly($monthlyFood[$month]) as $index => $amount) {
                $records[] = $this->record(
                    $userId,
                    'pengeluaran',
                    'Makan & Minum',
                    $amount,
                    'Belanja makan mingguan',
                    $year,
                    $month,
                    4 + ($index * 7)
                );
            }

            foreach ($seasonalExpense[$month] ?? [] as [$category, $description, $amount, $day]) {
                $records[] = $this->record($userId, 'pengeluaran', $category, $amount, $description, $year, $month, $day);
            }
        }

        return $records;
    }

    /**
     * @return list<int>
     */
    private function splitWeekly(int $monthlyAmount): array
    {
        $weights = [0.24, 0.26, 0.25, 0.25];
        $parts = [];
        $allocated = 0;

        foreach ($weights as $index => $weight) {
            if ($index === array_key_last($weights)) {
                $parts[] = $monthlyAmount - $allocated;
                break;
            }

            $amount = (int) round($monthlyAmount * $weight / 1000) * 1000;
            $parts[] = $amount;
            $allocated += $amount;
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        int $userId,
        string $type,
        string $category,
        int $amount,
        string $description,
        int $year,
        int $month,
        int $day
    ): array {
        $date = Carbon::create($year, $month, 1)->addDays($day - 1);
        $now = now();

        return [
            'user_id' => $userId,
            'sync_uuid' => (string) str()->uuid(),
            'server_version' => 1,
            'jenis' => $type,
            'kategori' => $category,
            'nominal' => $amount,
            'deskripsi' => sprintf('%s | %s', self::MARKER, $description),
            'tanggal' => $date->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }
}
