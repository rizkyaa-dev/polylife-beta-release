<?php

declare(strict_types=1);

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.connections.mysql.database') !== 'polylife_quiz') {
    throw new RuntimeException('Demo setup is restricted to the isolated polylife_quiz database.');
}

$user = App\Models\User::query()->firstOrNew(['email' => 'quiz.docker@example.test']);
$user->forceFill([
    'name' => 'Penguji Docker PolyLife',
    'password' => Illuminate\Support\Facades\Hash::make('QuizDocker!2405097'),
    'email_verified_at' => now(),
    'is_admin' => 0,
    'role' => 'user',
    'account_status' => 'active',
])->save();

$database = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
echo "Database connection OK: {$database->name}; MySQL {$database->version}\n";
echo "Verified demo user ready: {$user->email}\n";
