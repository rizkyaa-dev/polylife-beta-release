<?php

namespace Database\Factories;

use App\Models\Keuangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KeuanganFactory extends Factory
{
    protected $model = Keuangan::class;

    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'jenis'     => $this->faker->randomElement(['pemasukan', 'pengeluaran']),
            'nominal'   => $this->faker->randomFloat(2, 10000, 2000000),
            'deskripsi' => $this->faker->sentence(6),
            'tanggal'   => $this->faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
        ];
    }
}
