<?php

use App\Models\NationalHoliday;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('jadwal calendar renders national holiday reason with visible rose styling', function () {
    Cache::flush();
    $user = User::factory()->create();

    NationalHoliday::create([
        'date' => '2026-08-17',
        'name' => 'Proklamasi Kemerdekaan Republik Indonesia',
        'is_cuti_bersama' => false,
        'year' => 2026,
    ]);

    $response = $this->actingAs($user)->get(route('jadwal.index', [
        'tanggal' => '2026-08-17',
        'bulan' => '2026-08',
    ]));

    $response->assertOk();
    $response->assertSee('Proklamasi Kemerdekaan Republik Indonesia');
    $response->assertSee('text-rose-600 dark:text-rose-400 font-medium');
    $response->assertDontSee('class="mt-auto text-[11px] leading-tight text-gray-300"', false);
});

test('guest can view jadwal calendar with visible holiday reason', function () {
    $response = $this->get(route('guest.jadwal.index', [
        'tanggal' => '2026-08-17',
        'bulan' => '2026-08',
    ]));

    $response->assertOk();
    $response->assertDontSee('class="mt-auto text-[11px] leading-tight text-gray-300"', false);
});
