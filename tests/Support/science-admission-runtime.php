<?php

use App\Models\AiChatRun;
use App\Models\User;
use App\Services\Ai\AiConversationBranchService;
use App\Services\Ai\Exceptions\AiSystemCapacityException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Explicit local-only fixture. Never changes the application's database.
$database = getenv('SCIENCE_ADMISSION_DATABASE');
$directory = realpath((string) getenv('SCIENCE_ADMISSION_DIRECTORY'));
if (! is_string($database) || ! preg_match('/^polylife_science_test_[a-f0-9]{16}$/D', $database)
    || ! $directory || ! str_starts_with(basename($directory), 'polylife-science-admission-')
    || getenv('APP_ENV') !== 'local' || getenv('DB_CONNECTION') !== 'mysql') {
    throw new RuntimeException('Admission probe requires a guarded temporary local MySQL database.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->afterBootstrapping(RegisterProviders::class, function () use ($database): void {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database,
        'database.connections.mysql.url' => null, 'cache.default' => 'array',
        'services.ai_max_active_runs_global' => (int) getenv('SCIENCE_ADMISSION_LIMIT')]);
    if (! in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('Admission probe refuses a non-local database server.');
    }
});
$application->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? '';
if ($mode === 'init') {
    $config = config('database.connections.mysql');
    $server = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4',
        $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // No IF NOT EXISTS: an existing database is never adopted as our fixture.
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('Temporary admission schema migration failed.');
    }
    $count = (int) ($argv[2] ?? 2);
    if ($count < 2 || $count > 64) {
        throw new InvalidArgumentException('Admission fixture user count must be 2..64.');
    }
    $password = Hash::make('Admission-fixture-only-42!');
    $users = [];
    for ($i = 0; $i < $count; $i++) {
        $users[] = User::factory()->create(['name' => 'Admission fixture '.$i,
            'email' => 'admission-'.$i.'@example.invalid', 'password' => $password])->id;
    }
    echo json_encode(['initialized' => true, 'users' => $users,
        'server_version' => $server->query('SELECT VERSION()')->fetchColumn()], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'admit') {
    $userId = (int) ($argv[2] ?? 0);
    $isolation = getenv('SCIENCE_ADMISSION_ISOLATION');
    if (! in_array($isolation, ['READ COMMITTED', 'REPEATABLE READ'], true)) {
        throw new InvalidArgumentException('Unsupported admission fixture isolation.');
    }
    DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL '.$isolation);
    if (getenv('SCIENCE_ADMISSION_BARRIER') === '1') {
        DB::listen(function (QueryExecuted $query) use ($directory, $userId): void {
            if (! preg_match('/^select count\(\*\) as aggregate from [`"]?ai_chat_runs/i', $query->sql)
                || $query->bindings !== ['running']) {
                return;
            }
            file_put_contents($directory.'/ready-'.$userId, 'ready', LOCK_EX);
            $deadline = hrtime(true) + 5_000_000_000;
            while (count(glob($directory.'/ready-*')) < 2) {
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException('Admission reproduction barrier expired.');
                }
                usleep(10_000);
            }
        });
    }
    $started = hrtime(true);
    try {
        $turn = app(AiConversationBranchService::class)->beginTurn(User::findOrFail($userId),
            'Compute the coupled RC transient with explicitly declared SI units.');
        $outcome = ['status' => 'admitted', 'run_id' => $turn['run']->id];
    } catch (AiSystemCapacityException) {
        $outcome = ['status' => 'capacity_rejected'];
    } catch (Throwable $exception) {
        $outcome = ['status' => 'error', 'type' => get_class($exception), 'message' => $exception->getMessage()];
    }
    echo json_encode($outcome + ['duration_ms' => (hrtime(true) - $started) / 1e6], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'inspect') {
    echo json_encode(['running' => AiChatRun::where('status', 'running')->count(),
        'sessions' => DB::table('ai_chat_sessions')->count(), 'messages' => DB::table('ai_chat_messages')->count(),
        'capacity_query_plan' => DB::select('EXPLAIN SELECT id FROM ai_chat_runs WHERE status = ? LIMIT ?',
            ['running', (int) getenv('SCIENCE_ADMISSION_LIMIT')])], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'drop') {
    $config = config('database.connections.mysql');
    DB::disconnect('mysql');
    $server = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4',
        $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    echo json_encode(['dropped' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} else {
    throw new InvalidArgumentException('Unknown admission fixture mode.');
}
