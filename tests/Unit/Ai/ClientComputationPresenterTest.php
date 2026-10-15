<?php

namespace Tests\Unit\Ai;

use App\Models\AiChatRunStep;
use App\Services\Ai\Science\Client\ClientComputationPresenter;
use Tests\TestCase;

class ClientComputationPresenterTest extends TestCase
{
    private function step(int $id, array $payload, string $status = 'completed'): AiChatRunStep
    {
        return (new AiChatRunStep)->forceFill(['id' => $id, 'label' => 'Komputasi lokal selesai · belum terverifikasi',
            'status' => $status, 'public_metadata' => ['execution_mode' => 'client_script'], 'private_payload' => $payload]);
    }

    public function test_scripts_have_distinct_purposes_and_numbers_without_counting_reasoning(): void
    {
        $reasoning = (new AiChatRunStep)->forceFill(['id' => 1, 'label' => 'Memahami permintaan', 'public_metadata' => []]);
        $steps = collect([$reasoning,
            $this->step(2, ['task_label' => 'Menghitung volume tabung', 'status' => 'client_computed']),
            $this->step(3, ['task_label' => 'Menghitung tekanan keluaran', 'status' => 'client_computed']),
        ]);
        $labels = ClientComputationPresenter::labels($steps);
        $this->assertSame(2, ClientComputationPresenter::count($steps));
        $this->assertSame('Memahami permintaan', $labels[1]);
        $this->assertSame('Skrip 1 · Menghitung volume tabung · belum terverifikasi', $labels[2]);
        $this->assertSame('Skrip 2 · Menghitung tekanan keluaran · belum terverifikasi', $labels[3]);
    }

    public function test_legacy_partial_and_failed_scripts_do_not_claim_full_computation_success(): void
    {
        $labels = ClientComputationPresenter::labels([
            $this->step(1, ['model' => 'Menghitung ekspresi aritmetika', 'status' => 'client_computed']),
            $this->step(2, ['model' => 'Model reaktor lengkap', 'partial_scope' => ['completed_tasks' => ['Menghitung volume bed']], 'status' => 'unsupported']),
        ]);
        $this->assertStringContainsString('Menghitung ekspresi aritmetika', $labels[1]);
        $this->assertSame('Skrip 2 · Menghitung volume bed · tidak menghasilkan jawaban', $labels[2]);
        $this->assertStringNotContainsString('Model reaktor lengkap', $labels[2]);
    }

    public function test_running_and_confirmation_labels_preserve_execution_state(): void
    {
        $running = $this->step(1, [], 'running');
        $running->public_metadata = ['execution_mode' => 'client_script', 'computation_title' => 'Menghitung luas permukaan'];
        $waiting = $this->step(2, ['task_label' => 'Menghitung massa'], 'running');
        $waiting->label = 'Menunggu persetujuan komputasi lokal';
        $labels = ClientComputationPresenter::labels([$running, $waiting]);
        $this->assertSame('Skrip 1 · Menghitung luas permukaan · menjalankan', $labels[1]);
        $this->assertSame('Skrip 2 · Menghitung massa · menunggu persetujuan', $labels[2]);
    }

    public function test_titles_are_bounded_single_line_metadata_with_safe_legacy_fallback(): void
    {
        $title = ClientComputationPresenter::title(['task_label' => "  Menghitung\nvolume  ", 'model' => 'Broader model']);
        $this->assertSame('Menghitung volume', $title);
        $this->assertLessThanOrEqual(49, mb_strlen(ClientComputationPresenter::title(['model' => str_repeat('a', 400)])));
        $this->assertSame('Perhitungan lokal', ClientComputationPresenter::title([]));
    }

    public function test_short_activity_title_keeps_full_model_description_in_expanded_details(): void
    {
        $model = 'Exact evaluation of 12*871421 - 7846523/2*0.8 under standard operator precedence with multiplication and division at equal precedence.';
        $payload = ['execution_mode' => 'client_script', 'model' => $model,
            'client_program' => ['source' => 'function compute() {}', 'inputs' => [], 'hash' => 'test'], 'result' => null];
        $title = ClientComputationPresenter::title($payload);
        $this->assertLessThanOrEqual(49, mb_strlen($title));
        $this->assertStringNotContainsString('under standard', $title);
        $this->assertSame($model, ClientComputationPresenter::details($payload)['description']);
    }

    public function test_display_titles_have_at_most_three_words_without_an_ellipsis(): void
    {
        $this->assertSame('Menghitung ekspresi aritmetika', ClientComputationPresenter::title([
            'task_label' => 'Menghitung ekspresi aritmetika dengan presedensi standar',
        ]));
        $this->assertSame('Merumuskan neraca massa', ClientComputationPresenter::title([
            'task_label' => "Merumuskan\nneraca\tmassa reaktor kompleks",
        ]));
        $this->assertSame('Menghitung tekanan', ClientComputationPresenter::title(['task_label' => 'Menghitung tekanan']));
    }

    public function test_legacy_failed_execution_has_failure_status_and_actionable_details(): void
    {
        $payload = ['execution_mode' => 'client_script', 'status' => 'unsupported', 'execution' => ['client_failure' => 'syntax_error'],
            'client_program' => ['source' => 'function compute(', 'inputs' => [], 'hash' => 'test'], 'result' => null];
        $step = $this->step(1, $payload);
        $this->assertSame('failed', ClientComputationPresenter::status($step));
        $details = ClientComputationPresenter::details($payload);
        $this->assertSame('syntax_error', $details['failure_code']);
        $this->assertStringContainsString('sintaks', $details['failure_message']);
        $this->assertStringContainsString('gagal', ClientComputationPresenter::labels([$step])[1]);
    }
}
