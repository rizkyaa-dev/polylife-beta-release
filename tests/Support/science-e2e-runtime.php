<?php

use App\Jobs\ExecuteScienceComputation;
use App\Models\AiChatRun;
use App\Models\AiChatRunStep;
use App\Models\AiScienceExecution;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\Science\ScienceExecutionBroker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput;
use Tests\Support\ScienceE2eClient;
use Tests\Support\ScienceE2eSqliteConnector;
use Tests\Support\ScienceE2eSqlitePdo;

// Dedicated router/worker for an ephemeral DB. Never included in application routes.
$database = getenv('SCIENCE_E2E_DATABASE');
if (! $database || ! is_file($database) || realpath($database) !== realpath((string) getenv('DB_DATABASE'))
    || ! str_starts_with(basename(dirname($database)), 'polylife-science-e2e-')
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('APP_ENV') !== 'local') {
    throw new RuntimeException('Science E2E requires an explicitly isolated temporary SQLite database.');
}
$repository = dirname(__DIR__, 2);
if (PHP_SAPI === 'cli-server') {
    $pathname = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $asset = realpath($repository.'/public/'.$pathname);
    $publicRoot = realpath($repository.'/public').DIRECTORY_SEPARATOR;
    if ($asset && str_starts_with($asset, $publicRoot) && is_file($asset) && pathinfo($asset, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}
require $repository.'/vendor/autoload.php';
$application = require $repository.'/bootstrap/app.php';
$install = function ($application) use ($database): void {
    if (version_compare(PHP_VERSION, '8.4.0', '<')) {
        // Registered only after the temporary-database guard above has passed.
        $application->bind('db.connector.sqlite', fn () => new ScienceE2eSqliteConnector);
    }
    config(['services.ai_science_browser_enabled' => true, 'services.ai_queue_connection' => 'database',
        'view.compiled' => dirname($database).'/views',
        // Every HTTP/queue connection needs the lock policy, not only init's PDO.
        'database.connections.sqlite.busy_timeout' => 5000,
        'database.connections.sqlite.transaction_mode' => 'IMMEDIATE']);
    Vite::useHotFile(dirname($database).'/absent-vite.hot');
    $application->instance(LlmClientInterface::class, new ScienceE2eClient);
};
$application->afterBootstrapping(RegisterProviders::class, $install);
if (PHP_SAPI === 'cli-server') {
    $application->handleRequest(Request::capture());

    return;
}
$application->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? '';
if ($mode === 'init') {
    DB::statement('PRAGMA journal_mode=WAL');
    Artisan::call('migrate', ['--force' => true]);
    Schema::create('science_e2e_calls', function ($table): void {
        $table->id();
        $table->string('stage');
        $table->unsignedBigInteger('run_id');
        $table->string('effort')->nullable();
    });
    $user = User::factory()->create(['name' => 'Science E2E', 'email' => 'science-e2e@example.invalid',
        'password' => Hash::make('Fixture-password-42!'), 'account_status' => 'active', 'email_verified_at' => now()]);
    UserAiAssistant::create(['user_id' => $user->id, 'assistant_name' => 'Science Test',
        'personality_tone' => 'friendly_peer', 'thinking_effort' => 'low']);
    echo json_encode(['initialized' => true, 'immediate_transactions' => DB::connection()->getPdo() instanceof ScienceE2eSqlitePdo || version_compare(PHP_VERSION, '8.4.0', '>=')], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'worker') {
    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'ai-fast,ai-compute,ai-heavy',
        '--sleep' => 1, '--tries' => 1, '--timeout' => 510], new ConsoleOutput);
} elseif ($mode === 'inspect') {
    echo json_encode(['runs' => AiChatRun::get(['id', 'status', 'error_code', 'attempts', 'science_client', 'science_execution_id'])->toArray(),
        'queued_jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count(),
        'executions' => AiScienceExecution::get(['id', 'run_id', 'status', 'attempt', 'server_attempts'])->toArray(),
        'steps' => AiChatRunStep::where('tool_name', 'delegate_science_problem')->get()->map(fn ($step) => [
            'run_id' => $step->run_id, 'status' => $step->status, 'result' => $step->private_payload['result'] ?? null])->all(),
        'calls' => DB::table('science_e2e_calls')->get()->all()], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'crash-prepare') {
    $orchestrator = app(AiAgentOrchestrator::class);
    $turn = $orchestrator->enqueue(User::firstOrFail(), 'Hitung osilator teredam dalam SI sampai t=5 detik.',
        requestId: (string) Str::uuid(), scienceClient: true);
    $orchestrator->processRun($turn['run']->id);
    $ticket = AiScienceExecution::firstOrFail();
    // The owned browser deliberately declines; no numerical answer is accepted.
    $broker = app(ScienceExecutionBroker::class);
    $claim = $broker->claim($ticket, (string) Str::uuid());
    $broker->submit($ticket, ['attempt' => $claim['attempt'], 'token' => $claim['token'], 'failure' => 'unavailable']);
    echo json_encode(['ticket_id' => $ticket->id, 'run_id' => $turn['run']->id], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'crash-hold') {
    $job = new ExecuteScienceComputation((int) ($argv[2] ?? 0));
    app(ScienceExecutionBroker::class)->execute($job->executionId, onClaim: function ($attempt) use ($job): void {
        $ticket = AiScienceExecution::findOrFail($job->executionId);
        echo json_encode(['claimed' => true, 'attempt' => $attempt, 'claim_token' => $job->claimToken,
            'lease_expires_at' => $ticket->lease_expires_at->toIso8601String()], JSON_THROW_ON_ERROR).PHP_EOL;
        flush();
        // Test parent kills only this child after observing the committed claim.
        $deadline = hrtime(true) + 60_000_000_000;
        while (hrtime(true) < $deadline) {
            usleep(100_000);
        }
        throw new RuntimeException('Crash fixture parent failed to stop the claimed child.');
    }, claimToken: $job->claimToken);
} elseif ($mode === 'crash-recover') {
    $recovered = app(ScienceExecutionBroker::class)->recover();
    echo json_encode(['recovered' => $recovered], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'crash-compute') {
    (new ExecuteScienceComputation((int) ($argv[2] ?? 0)))->handle(app(ScienceExecutionBroker::class));
    echo json_encode(['executed' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'crash-stale-failure') {
    app(ScienceExecutionBroker::class)->fail((int) ($argv[2] ?? 0), claimToken: $argv[3] ?? null);
    echo json_encode(['delivered' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} elseif ($mode === 'crash-drain') {
    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'ai-fast,ai-compute,ai-heavy',
        '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 510]);
    echo json_encode(['drained' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} else {
    throw new InvalidArgumentException('Unknown science E2E mode.');
}
