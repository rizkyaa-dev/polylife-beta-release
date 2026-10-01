<?php

namespace Tests\Feature\Ai;

use App\Models\Catatan;
use App\Models\KeuanganBudget;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Services\Ai\Actions\AiWriteActionRegistry;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiProposalFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_second_note_edit_cannot_be_overwritten_by_a_pending_proposal(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $note = Catatan::create(['user_id' => $user->id, 'judul' => 'Catatan', 'isi' => 'Awal', 'tanggal' => now()->toDateString(), 'show_preview' => true]);
        $proposal = app(AiToolRegistry::class)->find('update_catatan')->execute($user, ['target' => 'Catatan', 'operation' => 'replace_content', 'value' => 'AI']);
        $before = $note->updated_at;
        $note->update(['isi' => 'Manual']);
        $this->assertTrue($before->equalTo($note->fresh()->updated_at));
        try {
            app(AiWriteActionRegistry::class)->execute('update_catatan', $user, $proposal['payload']);
            $this->fail('Concurrent edit was overwritten.');
        } catch (AiActionException) {
            $this->assertSame('Manual', $note->fresh()->isi);
        }
    }

    public function test_task_and_todo_management_reject_concurrent_edits_and_allow_unchanged_records(): void
    {
        $user = User::factory()->create();
        foreach ([['manage_tugas', Tugas::class, 'nama_tugas'], ['manage_todolist', Todolist::class, 'nama_item']] as [$toolName, $model, $name]) {
            $record = $model::create(['user_id' => $user->id, $name => 'Pekerjaan', 'deadline' => now()->addDay(), 'status_selesai' => false, 'status' => false]);
            $tool = app(AiToolRegistry::class)->find($toolName);
            $proposal = $tool->execute($user, ['target' => 'Pekerjaan', 'operation' => 'rename', 'new_name' => 'Dari AI']);
            $record->update([$name => 'Edit manual']);
            try {
                app(AiWriteActionRegistry::class)->execute($toolName, $user, $proposal['payload']);
                $this->fail('Concurrent rename was overwritten.');
            } catch (AiActionException) {
                $this->assertSame('Edit manual', $record->fresh()->getAttribute($name));
            }
            $fresh = $tool->execute($user, ['target' => 'Edit manual', 'operation' => 'rename', 'new_name' => 'Dari AI']);
            app(AiWriteActionRegistry::class)->execute($toolName, $user, $fresh['payload']);
            $this->assertSame('Dari AI', $record->fresh()->getAttribute($name));
        }
    }

    public function test_legacy_proposals_without_a_snapshot_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(AiWriteActionRegistry::class)->execute('manage_tugas', User::factory()->create(), ['tugas_id' => 1, 'operation' => 'complete']);
    }

    public function test_budget_creation_and_edits_cannot_overwrite_changes_since_the_proposal(): void
    {
        $user = User::factory()->create();
        $tool = app(AiToolRegistry::class)->find('set_finance_budget');
        $arguments = ['kategori' => 'Makanan', 'nominal_limit' => 500000, 'month' => '2026-10'];
        $proposal = $tool->execute($user, $arguments);
        $budget = KeuanganBudget::create(['user_id' => $user->id, 'kategori' => 'Makanan', 'bulan' => 10, 'tahun' => 2026, 'nominal_limit' => 200000]);
        foreach ([false, true] as $edit) {
            if ($edit) {
                $proposal = $tool->execute($user, $arguments);
                $budget->update(['nominal_limit' => 300000]);
            }
            try {
                app(AiWriteActionRegistry::class)->execute('set_finance_budget', $user, $proposal['payload']);
                $this->fail('Concurrent budget write was overwritten.');
            } catch (AiActionException) {
                $this->assertEquals($edit ? 300000 : 200000, $budget->fresh()->nominal_limit);
            }
        }
        $proposal = $tool->execute($user, $arguments);
        app(AiWriteActionRegistry::class)->execute('set_finance_budget', $user, $proposal['payload']);
        $this->assertEquals(500000, $budget->fresh()->nominal_limit);
    }
}
