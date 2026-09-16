<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\AiAgentOrchestrator;
use Illuminate\Console\Command;

final class ChatWithAiAgent extends Command
{
    protected $signature = 'ai:chat
        {user : ID atau email pengguna workspace}
        {prompt* : Pesan yang dikirim ke AI}
        {--session= : ID sesi milik pengguna untuk melanjutkan percakapan}';

    protected $description = 'Kirim prompt ke AI workspace dari CLI melalui orchestrator produksi';

    public function handle(AiAgentOrchestrator $orchestrator): int
    {
        $identifier = (string) $this->argument('user');
        $user = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->where('email', $identifier)->first();

        if (! $user) {
            $this->error('Pengguna tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $user->isActiveAccount() || $user->isAdmin()) {
            $this->error('AI workspace hanya dapat dijalankan untuk akun pengguna aktif.');

            return self::FAILURE;
        }

        $prompt = trim(implode(' ', (array) $this->argument('prompt')));
        $sessionId = $this->option('session');
        $turn = $orchestrator->handle($user, $prompt, $sessionId !== null ? (int) $sessionId : null);
        $tools = $turn['run']->steps->where('kind', 'tool_call')->pluck('tool_name')->filter()->unique()->values();

        $this->newLine();
        $this->line((string) $turn['reply']);
        $this->newLine();
        $this->line('Session: '.$turn['session']->id);
        $this->line('Tools: '.($tools->isEmpty() ? '-' : $tools->implode(', ')));

        if ($turn['proposals'] !== []) {
            $this->warn('Perubahan belum dieksekusi. Konfirmasikan proposal melalui UI workspace.');
            $this->table(
                ['Action ID', 'Tool', 'Ringkasan'],
                collect($turn['proposals'])->map(fn (array $proposal): array => [
                    $proposal['action_id'], $proposal['tool_name'], $proposal['summary'],
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
