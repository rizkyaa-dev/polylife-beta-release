<?php

namespace Database\Seeders;

use App\Models\NationalHoliday;
use Illuminate\Database\Seeder;

class NationalHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            // === Hari Libur Nasional 2026 (SKB 3 Menteri No. 1497, 2, 5 Tahun 2025) ===

            // Januari
            ['date' => '2026-01-01', 'name' => 'Tahun Baru 2026 Masehi', 'is_cuti_bersama' => false],
            ['date' => '2026-01-16', 'name' => 'Isra Mi\'raj Nabi Muhammad S.A.W.', 'is_cuti_bersama' => false],

            // Februari
            ['date' => '2026-02-16', 'name' => 'Cuti Bersama Tahun Baru Imlek 2577 Kongzili', 'is_cuti_bersama' => true],
            ['date' => '2026-02-17', 'name' => 'Tahun Baru Imlek 2577 Kongzili', 'is_cuti_bersama' => false],

            // Maret
            ['date' => '2026-03-18', 'name' => 'Cuti Bersama Hari Suci Nyepi', 'is_cuti_bersama' => true],
            ['date' => '2026-03-19', 'name' => 'Hari Suci Nyepi Tahun Baru Saka 1948', 'is_cuti_bersama' => false],
            ['date' => '2026-03-20', 'name' => 'Cuti Bersama Hari Raya Idul Fitri 1447H', 'is_cuti_bersama' => true],
            ['date' => '2026-03-21', 'name' => 'Hari Raya Idul Fitri 1447H', 'is_cuti_bersama' => false],
            ['date' => '2026-03-22', 'name' => 'Hari Raya Idul Fitri 1447H', 'is_cuti_bersama' => false],
            ['date' => '2026-03-23', 'name' => 'Cuti Bersama Hari Raya Idul Fitri 1447H', 'is_cuti_bersama' => true],
            ['date' => '2026-03-24', 'name' => 'Cuti Bersama Hari Raya Idul Fitri 1447H', 'is_cuti_bersama' => true],

            // April
            ['date' => '2026-04-03', 'name' => 'Wafat Yesus Kristus', 'is_cuti_bersama' => false],
            ['date' => '2026-04-05', 'name' => 'Hari Raya Paskah', 'is_cuti_bersama' => false],

            // Mei
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'is_cuti_bersama' => false],
            ['date' => '2026-05-14', 'name' => 'Kenaikan Yesus Kristus', 'is_cuti_bersama' => false],
            ['date' => '2026-05-15', 'name' => 'Cuti Bersama Kenaikan Yesus Kristus', 'is_cuti_bersama' => true],
            ['date' => '2026-05-27', 'name' => 'Hari Raya Idul Adha 1447H', 'is_cuti_bersama' => false],
            ['date' => '2026-05-28', 'name' => 'Cuti Bersama Hari Raya Idul Adha 1447H', 'is_cuti_bersama' => true],
            ['date' => '2026-05-31', 'name' => 'Hari Raya Waisak 2570 BE', 'is_cuti_bersama' => false],

            // Juni
            ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila', 'is_cuti_bersama' => false],
            ['date' => '2026-06-16', 'name' => 'Tahun Baru Islam 1448 Hijriah', 'is_cuti_bersama' => false],

            // Agustus
            ['date' => '2026-08-17', 'name' => 'Proklamasi Kemerdekaan Republik Indonesia', 'is_cuti_bersama' => false],
            ['date' => '2026-08-25', 'name' => 'Maulid Nabi Muhammad S.A.W.', 'is_cuti_bersama' => false],

            // Desember
            ['date' => '2026-12-24', 'name' => 'Cuti Bersama Hari Raya Natal', 'is_cuti_bersama' => true],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'is_cuti_bersama' => false],
        ];

        foreach ($holidays as $holiday) {
            NationalHoliday::updateOrCreate(
                ['date' => $holiday['date'], 'name' => $holiday['name']],
                array_merge($holiday, ['year' => (int) substr($holiday['date'], 0, 4)])
            );
        }
    }
}
