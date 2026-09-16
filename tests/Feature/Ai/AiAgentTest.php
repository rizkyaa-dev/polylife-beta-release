<?php

namespace Tests\Feature\Ai;

use App\Models\AiActionProposal;
use App\Models\AiChatMessage;
use App\Models\AiChatRunStep;
use App\Models\AiChatSession;
use App\Models\Catatan;
use App\Models\Jadwal;
use App\Models\Keuangan;
use App\Models\KeuanganBudget;
use App\Models\Matkul;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use App\Models\UserAiAssistant;
use App\Services\Ai\ActionProposalExecutor;
use App\Services\Ai\AiAgentOrchestrator;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Providers\GeminiLlmClient;
use App\Services\Ai\Providers\MockLlmClient;
use App\Services\Ai\Tools\GetFinancialSummaryTool;
use App\Services\Ai\Tools\GetPendingTasksTool;
use App\Services\Ai\Tools\GetUpcomingScheduleTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_access_ai_workspace_without_writing_during_a_get_request(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('ai.workspace'));

        $response->assertOk();
        $response->assertSee('PolyBot');
        $this->assertDatabaseMissing('user_ai_assistants', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_customize_ai_assistant_name_and_persona(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('ai.settings.update'), [
            'assistant_name' => 'Kuro',
            'personality_tone' => 'casual',
            'thinking_effort' => 'max',
            'custom_instructions' => 'Panggil saya Kapten',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('user_ai_assistants', [
            'user_id' => $user->id,
            'assistant_name' => 'Kuro',
            'personality_tone' => 'casual',
            'thinking_effort' => 'max',
            'custom_instructions' => 'Panggil saya Kapten',
        ]);
    }

    public function test_user_can_update_thinking_effort_without_overwriting_other_assistant_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        UserAiAssistant::create([
            'user_id' => $user->id,
            'assistant_name' => 'Kuro',
            'personality_tone' => 'casual',
            'thinking_effort' => ThinkingEffort::High,
        ]);

        $this->actingAs($user)
            ->patchJson(route('ai.settings.thinking.update'), ['thinking_effort' => 'low'])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'thinking_effort' => 'low',
                'label' => 'Low',
            ]);

        $this->assertDatabaseHas('user_ai_assistants', [
            'user_id' => $user->id,
            'assistant_name' => 'Kuro',
            'personality_tone' => 'casual',
            'thinking_effort' => 'low',
        ]);

        $this->patchJson(route('ai.settings.thinking.update'), ['thinking_effort' => 'ultra'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('user_ai_assistants', [
            'user_id' => $user->id,
            'thinking_effort' => 'low',
        ]);
    }

    public function test_read_tool_enforces_strict_tenant_isolation(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $userB = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        Jadwal::create([
            'user_id' => $userA->id,
            'title' => 'Kuliah User A',
            'jenis' => 'kuliah',
            'tanggal_mulai' => '2026-09-16',
            'tanggal_selesai' => '2026-09-16',
        ]);

        Jadwal::create([
            'user_id' => $userB->id,
            'title' => 'Kuliah Rahasia User B',
            'jenis' => 'kuliah',
            'tanggal_mulai' => '2026-09-16',
            'tanggal_selesai' => '2026-09-16',
        ]);

        $tool = app(GetUpcomingScheduleTool::class);
        $result = $tool->execute($userA, [
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-17',
        ]);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Kuliah User A', $result['schedules'][0]['title']);

        // Confirm User B schedule is nowhere in User A's results
        $titles = collect($result['schedules'])->pluck('title');
        $this->assertFalse($titles->contains('Kuliah Rahasia User B'));
    }

    public function test_pending_tasks_tool_uses_the_canonical_completion_column_and_tenant_scope(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Tugas::create([
            'user_id' => $user->id,
            'nama_tugas' => 'Review OWASP',
            'deadline' => '2026-09-16 07:00:00',
            'status_selesai' => false,
        ]);
        Tugas::create([
            'user_id' => $user->id,
            'nama_tugas' => 'Tugas selesai',
            'deadline' => '2026-09-15 07:00:00',
            'status_selesai' => true,
        ]);
        Tugas::create([
            'user_id' => $otherUser->id,
            'nama_tugas' => 'Tugas pengguna lain',
            'deadline' => '2026-09-16 06:00:00',
            'status_selesai' => false,
        ]);
        Todolist::create(['user_id' => $user->id, 'nama_item' => 'Baca modul', 'status' => false]);

        $result = app(GetPendingTasksTool::class)->execute($user, ['limit' => 10]);

        $this->assertSame(1, $result['pending_tugas_count']);
        $this->assertSame('Review OWASP', $result['tugas'][0]['nama_tugas']);
        $this->assertArrayHasKey('status_selesai', $result['tugas'][0]);
        $this->assertSame(1, $result['active_todos_count']);
        $this->assertSame('Baca modul', $result['todos'][0]['nama_item']);
    }

    public function test_schedule_tool_expands_course_container_into_real_weekly_occurrences(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $course = Matkul::create([
            'user_id' => $user->id,
            'kode' => 'IF204',
            'nama' => 'Struktur Data',
            'kelas' => 'A;A',
            'dosen' => 'Dosen Pengampu',
            'semester' => 3,
            'sks' => 3,
            'hari' => 'Senin;Rabu',
            'jam_mulai' => '08:00;10:00',
            'jam_selesai' => '09:40;11:40',
            'ruangan' => 'Lab 1;Ruang 204',
            'warna_label' => '#2563eb',
            'catatan' => '',
        ]);
        Jadwal::create([
            'user_id' => $user->id,
            'matkul_id_list' => (string) $course->id,
            'title' => null,
            'jenis' => 'kuliah',
            'tanggal_mulai' => '2026-08-27',
            'tanggal_selesai' => '2027-08-27',
        ]);

        $result = app(GetUpcomingScheduleTool::class)->execute($user, [
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(['2026-09-14', '2026-09-16'], collect($result['schedules'])->pluck('date')->all());
        $this->assertSame(['08:00', '10:00'], collect($result['schedules'])->pluck('start_time')->all());
        $this->assertSame(['Lab 1', 'Ruang 204'], collect($result['schedules'])->pluck('location')->all());
        $this->assertSame(['Struktur Data', 'Struktur Data'], collect($result['schedules'])->pluck('title')->all());
        $this->assertArrayNotHasKey('tanggal_mulai', $result['schedules'][0]);
    }

    public function test_schedule_tool_omits_empty_course_container_and_rejects_unbounded_range(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        Jadwal::create([
            'user_id' => $user->id,
            'title' => null,
            'jenis' => 'kuliah',
            'tanggal_mulai' => '2026-08-27',
            'tanggal_selesai' => '2027-08-27',
        ]);
        $tool = app(GetUpcomingScheduleTool::class);

        $emptyResult = $tool->execute($user, [
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
        ]);
        $invalidResult = $tool->execute($user, [
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-02',
        ]);

        $this->assertSame(0, $emptyResult['total']);
        $this->assertSame([], $emptyResult['schedules']);
        $this->assertSame('invalid_arguments', $invalidResult['status']);
    }

    public function test_mutating_tool_creates_proposal_without_modifying_database(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: null,
            toolCalls: [
                new LlmToolCall(
                    id: 'call_1',
                    name: 'create_keuangan',
                    arguments: [
                        'jenis' => 'pengeluaran',
                        'kategori' => 'Makan',
                        'nominal' => 25000,
                        'tanggal' => now()->toDateString(),
                        'deskripsi' => 'Nasi Padang',
                    ]
                ),
            ]
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $orchestrator = app(AiAgentOrchestrator::class);
        $result = $orchestrator->handle($user, 'Catat pengeluaran makan siang 25000');

        $this->assertCount(1, $result['proposals']);
        $this->assertEquals('create_keuangan', $result['proposals'][0]['tool_name']);
        $this->assertNotEmpty($result['proposals'][0]['signature']);

        // Database table MUST remain empty until user confirms!
        $this->assertDatabaseCount('keuangans', 0);
        $this->assertDatabaseHas('ai_action_proposals', [
            'user_id' => $user->id,
            'tool_name' => 'create_keuangan',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_confirm_action_proposal_and_commit_data(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: null,
            toolCalls: [
                new LlmToolCall(
                    id: 'call_1',
                    name: 'create_keuangan',
                    arguments: [
                        'jenis' => 'pengeluaran',
                        'kategori' => 'Transportasi',
                        'nominal' => 15000,
                        'tanggal' => now()->toDateString(),
                        'deskripsi' => 'Ojol',
                    ]
                ),
            ]
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $orchestrator = app(AiAgentOrchestrator::class);
        $chatResult = $orchestrator->handle($user, 'Catat ojol 15000');
        $proposal = $chatResult['proposals'][0];

        $response = $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'message' => 'Transaksi berhasil disimpan.',
            'receipt' => [
                'destination_label' => 'Buka Keuangan',
                'destination_url' => route('keuangan.index'),
            ],
        ]);

        // Database now has the keuangan record!
        $this->assertDatabaseHas('keuangans', [
            'user_id' => $user->id,
            'jenis' => 'pengeluaran',
            'nominal' => 15000,
        ]);

        $this->assertDatabaseHas('ai_action_proposals', [
            'action_id' => $proposal['action_id'],
            'status' => 'confirmed',
        ]);
    }

    public function test_note_request_creates_and_confirms_a_catatan_without_using_todo(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: null, toolCalls: [
            new LlmToolCall(id: 'note_1', name: 'create_catatan', arguments: [
                'judul' => 'Belajar KPL',
                'isi' => 'CIA triad, kriptografi, OWASP Top 10, Secure SDLC, secure coding',
                'tanggal' => '2026-09-15',
            ]),
        ]));
        $this->app->instance(LlmClientInterface::class, $client);

        $chatResult = app(AiAgentOrchestrator::class)->handle($user, 'Masukkan materi KPL ini ke catatan gue');
        $proposal = $chatResult['proposals'][0];

        $this->assertSame('create_catatan', $proposal['tool_name']);
        $this->assertDatabaseCount('catatans', 0);
        $this->assertDatabaseCount('todolists', 0);

        $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ])->assertOk()->assertJson([
            'status' => 'success',
            'message' => 'Catatan berhasil disimpan.',
            'receipt' => [
                'acknowledgement' => 'Sip, catatannya sudah tersimpan. Kalau mau, kita bisa lanjut merapikan atau menambahkan isinya.',
                'destination_label' => 'Buka Catatan',
                'destination_url' => route('catatan.index'),
            ],
        ]);

        $catatan = Catatan::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Belajar KPL', $catatan->judul);
        $this->assertSame('CIA triad, kriptografi, OWASP Top 10, Secure SDLC, secure coding', $catatan->isi);
        $this->assertSame('2026-09-15', $catatan->tanggal->toDateString());
        $this->assertTrue($catatan->show_preview);
        $this->assertDatabaseCount('todolists', 0);
    }

    public function test_tampered_signature_or_cross_user_confirmation_is_rejected(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $userB = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: null,
            toolCalls: [
                new LlmToolCall(
                    id: 'call_1',
                    name: 'create_keuangan',
                    arguments: [
                        'jenis' => 'pengeluaran',
                        'kategori' => 'Pendidikan',
                        'nominal' => 50000,
                        'tanggal' => now()->toDateString(),
                        'deskripsi' => 'Buku Kuliah',
                    ]
                ),
            ]
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $orchestrator = app(AiAgentOrchestrator::class);
        $chatResult = $orchestrator->handle($userA, 'catat pengeluaran buku');
        $proposal = $chatResult['proposals'][0];

        // 1. User B tries to confirm User A's proposal -> 422
        $response = $this->actingAs($userB)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('bukan milik akun Anda', $response->json('message'));

        // 2. User A provides a forged signature -> 422
        $response = $this->actingAs($userA)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => 'forged_fake_signature_hash',
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('tidak valid', $response->json('message'));
    }

    public function test_user_can_send_chat_message_via_endpoint(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: '**Halo!** Ada yang bisa saya bantu hari ini?',
            toolCalls: []
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $response = $this->actingAs($user)->postJson(route('ai.chat'), [
            'message' => 'Halo PolyBot',
        ]);

        $response->assertAccepted()->assertJsonPath('status', 'accepted');
        $completed = $this->getJson(route('ai.runs.show', $response->json('run_id')));
        $completed->assertOk();
        $completed->assertJson([
            'status' => 'success',
            'reply' => '**Halo!** Ada yang bisa saya bantu hari ini?',
            'reply_html' => "<p><strong>Halo!</strong> Ada yang bisa saya bantu hari ini?</p>\n",
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'role' => 'user',
            'content' => 'Halo PolyBot',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'role' => 'assistant',
            'content' => '**Halo!** Ada yang bisa saya bantu hari ini?',
        ]);
    }

    public function test_user_can_reject_action_proposal(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: null,
            toolCalls: [
                new LlmToolCall(
                    id: 'call_1',
                    name: 'create_keuangan',
                    arguments: [
                        'jenis' => 'pengeluaran',
                        'kategori' => 'Makan',
                        'nominal' => 20000,
                        'tanggal' => now()->toDateString(),
                        'deskripsi' => 'Batagor',
                    ]
                ),
            ]
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $orchestrator = app(AiAgentOrchestrator::class);
        $chatResult = $orchestrator->handle($user, 'Catat batagor 20rb');
        $proposal = $chatResult['proposals'][0];

        $response = $this->actingAs($user)->postJson(route('ai.action.reject'), [
            'action_id' => $proposal['action_id'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'message' => 'Proposal aksi telah dibatalkan.',
        ]);

        $this->assertDatabaseHas('ai_action_proposals', [
            'action_id' => $proposal['action_id'],
            'status' => 'rejected',
        ]);
        $this->assertDatabaseCount('keuangans', 0);
    }

    public function test_duplicate_or_already_processed_action_proposal_cannot_be_executed_twice(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: null,
            toolCalls: [
                new LlmToolCall(
                    id: 'call_double_submit',
                    name: 'create_keuangan',
                    arguments: [
                        'jenis' => 'pengeluaran',
                        'kategori' => 'Makan',
                        'nominal' => 30000,
                        'tanggal' => now()->toDateString(),
                        'deskripsi' => 'Makan malam',
                    ]
                ),
            ]
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $orchestrator = app(AiAgentOrchestrator::class);
        $chatResult = $orchestrator->handle($user, 'Catat makan malam 30rb');
        $proposal = $chatResult['proposals'][0];

        // First confirmation succeeds
        $response1 = $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ]);
        $response1->assertOk();
        $this->assertDatabaseCount('keuangans', 1);

        // Immediate second confirmation is blocked by PreventDuplicateWrite middleware with 429
        $response2 = $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ]);
        $response2->assertStatus(429);

        // After the 3-second idempotency window, second execution is rejected at database layer by ActionProposalExecutor with 422
        $this->travel(4)->seconds();
        $response3 = $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ]);
        $response3->assertStatus(422);
        $this->assertStringContainsString('Proposal sudah diproses atau telah kadaluarsa', $response3->json('message'));

        // Keuangan record must remain strictly 1 (no duplicate execution!)
        $this->assertDatabaseCount('keuangans', 1);
    }

    public function test_submitting_another_users_session_id_fails_validation_with_422(): void
    {
        $userA = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $userB = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        $sessionB = AiChatSession::create([
            'user_id' => $userB->id,
            'title' => 'Sesi Rahasia User B',
        ]);

        // User A attempts to send message with User B's session_id
        $response = $this->actingAs($userA)->postJson(route('ai.chat'), [
            'message' => 'Tes injeksi session',
            'session_id' => $sessionB->id,
        ]);

        // Must fail with 422 Unprocessable Entity (scoped validation) without throwing 500 ModelNotFoundException
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['session_id']);
    }

    public function test_gemini_client_sends_api_key_via_header_not_query_string(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Halo dari Gemini yang aman!'],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        $client = new GeminiLlmClient(apiKey: 'AIzaSySecretTestKey123', model: 'gemini-2.5-flash');
        $response = $client->chat([
            new LlmMessage(role: 'user', content: 'Halo'),
        ]);

        $this->assertEquals('Halo dari Gemini yang aman!', $response->content);

        // Ensure request had x-goog-api-key header and did NOT expose ?key= in the query string
        Http::assertSent(function ($request) {
            return $request->hasHeader('x-goog-api-key', 'AIzaSySecretTestKey123')
                && ! str_contains($request->url(), '?key=');
        });
    }

    public function test_gemini_preserves_function_call_ids_and_output_budget(): void
    {
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['functionCall' => [
                    'id' => 'gemini_call_123',
                    'name' => 'get_pending_tasks',
                    'args' => ['limit' => 3],
                ]]]],
                'finishReason' => 'STOP',
            ]],
        ])]);

        $response = (new GeminiLlmClient(apiKey: 'test'))->chat(
            [new LlmMessage(role: 'user', content: 'Lihat tugas')],
            [],
            null,
            new LlmRequestOptions(maxOutputTokens: 2048)
        );

        $this->assertSame('gemini_call_123', $response->toolCalls[0]->id);
        Http::assertSent(fn ($request): bool => $request['generationConfig']['maxOutputTokens'] === 2048);
    }

    public function test_gemini_returns_the_function_call_id_with_tool_results(): void
    {
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Selesai']]], 'finishReason' => 'STOP']],
        ])]);
        $call = new LlmToolCall('gemini_call_456', 'get_pending_tasks', ['limit' => 3]);

        (new GeminiLlmClient(apiKey: 'test'))->chat([
            new LlmMessage(role: 'assistant', content: null, toolCalls: [$call]),
            new LlmMessage(role: 'tool', content: null, toolResult: [
                'call_id' => $call->id,
                'tool_name' => $call->name,
                'result' => ['pending_tugas_count' => 0],
            ]),
        ]);

        Http::assertSent(fn ($request): bool => $request['contents'][0]['parts'][0]['functionCall']['id'] === 'gemini_call_456'
            && $request['contents'][1]['role'] === 'user'
            && $request['contents'][1]['parts'][0]['functionResponse']['id'] === 'gemini_call_456');
    }

    public function test_orchestrator_preserves_call_id_for_openai_tool_results(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);

        // First turn: LLM calls read tool with unique call ID
        // Second turn: LLM replies with summary
        $mockClient = new MockLlmClient;
        $mockClient->queueResponse(new LlmResponse(
            content: null,
            toolCalls: [
                new LlmToolCall(
                    id: 'call_xyz_unique_888',
                    name: 'get_pending_tasks',
                    arguments: ['limit' => 5]
                ),
            ]
        ));
        $mockClient->queueResponse(new LlmResponse(
            content: 'Kamu memiliki 0 tugas tertunda.',
            toolCalls: []
        ));
        $this->app->instance(LlmClientInterface::class, $mockClient);

        $orchestrator = app(AiAgentOrchestrator::class);
        $result = $orchestrator->handle($user, 'Apa tugasku?');

        $this->assertEquals('Kamu memiliki 0 tugas tertunda.', $result['reply']);

        // Verify session history has user message and assistant message
        $this->assertDatabaseHas('ai_chat_messages', [
            'role' => 'user',
            'content' => 'Apa tugasku?',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'role' => 'assistant',
            'content' => 'Kamu memiliki 0 tugas tertunda.',
        ]);
        $step = AiChatRunStep::query()->where('kind', 'tool_call')->firstOrFail();
        $this->assertIsArray($step->private_payload);
        $this->assertArrayHasKey('todos', $step->private_payload);
        $this->assertNotSame(
            json_encode($step->private_payload),
            DB::table('ai_chat_run_steps')->where('id', $step->id)->value('private_payload')
        );
    }

    public function test_financial_summary_uses_monthly_category_budgets(): void
    {
        $user = User::factory()->create();

        KeuanganBudget::create(['user_id' => $user->id, 'kategori' => 'Makan', 'nominal_limit' => 100000, 'bulan' => 9, 'tahun' => 2026]);
        KeuanganBudget::create(['user_id' => $user->id, 'kategori' => 'Transport', 'nominal_limit' => 100000, 'bulan' => 9, 'tahun' => 2026]);
        KeuanganBudget::create(['user_id' => $user->id, 'kategori' => 'Makan', 'nominal_limit' => 999999, 'bulan' => 8, 'tahun' => 2026]);
        Keuangan::create(['user_id' => $user->id, 'jenis' => 'pengeluaran', 'kategori' => 'Makan', 'nominal' => 150000, 'tanggal' => '2026-09-10']);
        Keuangan::create(['user_id' => $user->id, 'jenis' => 'pengeluaran', 'kategori' => 'Transport', 'nominal' => 50000, 'tanggal' => '2026-09-11']);

        $result = app(GetFinancialSummaryTool::class)->execute($user, ['month' => '2026-09']);

        $this->assertSame(200000.0, $result['budget_limit']);
        $this->assertSame(50000.0, $result['budget_remaining']);
        $this->assertSame(50000.0, $result['budget_overage']);
        $this->assertSame('Kritis / Defisit', $result['budget_status']);
    }

    public function test_financial_summary_rejects_an_invalid_month_without_throwing(): void
    {
        $result = app(GetFinancialSummaryTool::class)->execute(
            User::factory()->create(),
            ['month' => 'September nanti']
        );

        $this->assertSame('invalid_arguments', $result['status']);
        $this->assertStringContainsString('YYYY-MM', $result['message']);
    }

    public function test_invalid_mutation_arguments_do_not_create_a_proposal(): void
    {
        $user = User::factory()->create();
        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: null, toolCalls: [
            new LlmToolCall(id: 'invalid_todo', name: 'create_todolist', arguments: ['item' => '']),
        ]));
        $client->queueResponse(new LlmResponse(content: 'Nama to-do perlu diisi.'));
        $this->app->instance(LlmClientInterface::class, $client);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Tambahkan todo kosong');

        $this->assertSame([], $result['proposals']);
        $this->assertDatabaseCount('ai_action_proposals', 0);
        $this->assertDatabaseCount('todolists', 0);
    }

    public function test_executor_revalidates_stored_proposal_payload(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Invalid proposal']);
        $payload = ['nama_item' => '', 'status' => false];
        $actionId = 'act_invalid_payload';
        $signature = hash_hmac(
            'sha256',
            $actionId.'|'.$user->id.'|create_todolist|'.json_encode(ActionProposalExecutor::canonicalizePayload($payload)),
            (string) config('app.key')
        );
        AiActionProposal::create([
            'action_id' => $actionId,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'tool_name' => 'create_todolist',
            'summary' => 'Invalid',
            'payload_json' => $payload,
            'signature' => $signature,
            'status' => 'pending',
            'expires_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $actionId,
            'signature' => $signature,
        ]);

        $response->assertStatus(422)->assertJson(['status' => 'error', 'message' => 'Data proposal tidak valid.']);
        $this->assertDatabaseCount('todolists', 0);
        $this->assertDatabaseHas('ai_action_proposals', ['action_id' => $actionId, 'status' => 'pending']);
    }

    public function test_confirmed_schedule_uses_the_canonical_schedule_domain_values(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: null, toolCalls: [
            new LlmToolCall(id: 'schedule_1', name: 'create_jadwal', arguments: [
                'title' => 'UTS Algoritma',
                'type' => 'uts',
                'start_at' => '2026-09-20 09:00:00',
                'end_at' => '2026-09-20 11:00:00',
                'location' => 'Lab 2',
            ]),
        ]));
        $client->queueResponse(new LlmResponse(content: 'Proposal jadwal siap dikonfirmasi.'));
        $this->app->instance(LlmClientInterface::class, $client);

        $proposal = app(AiAgentOrchestrator::class)
            ->handle($user, 'Tambahkan UTS Algoritma')['proposals'][0];

        $this->actingAs($user)->postJson(route('ai.action.confirm'), [
            'action_id' => $proposal['action_id'],
            'signature' => $proposal['signature'],
        ])->assertOk();

        $this->assertDatabaseHas('jadwals', [
            'user_id' => $user->id,
            'title' => 'UTS Algoritma',
            'jenis' => 'uts',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_new_workspace_does_not_reopen_latest_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $session = AiChatSession::create(['user_id' => $user->id, 'title' => 'Sesi lama unik']);
        AiChatMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'Pesan lama rahasia']);

        $this->actingAs($user)->get(route('ai.workspace', ['new' => 1]))
            ->assertOk()
            ->assertDontSee('Pesan lama rahasia');
    }

    public function test_provider_failure_marks_user_message_as_failed(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $this->app->instance(LlmClientInterface::class, new class implements LlmClientInterface
        {
            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                throw new \RuntimeException('provider unavailable');
            }
        });

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Halo'])
            ->assertStatus(500)
            ->assertJsonPath('status', 'error')
            ->assertJsonMissingExact(['message' => 'provider unavailable'])
            ->assertJsonPath('message', 'Pesan belum dapat diproses. Silakan coba kembali sesaat lagi.');

        $this->assertDatabaseHas('ai_chat_messages', [
            'role' => 'user',
            'content' => 'Halo',
            'status' => 'failed',
            'error_code' => 'run_failed',
        ]);
        $this->assertDatabaseMissing('ai_chat_messages', ['role' => 'assistant']);
    }

    public function test_read_tool_failure_is_reported_to_the_model_without_failing_the_entire_run(): void
    {
        $user = User::factory()->create();
        $registry = app(AiToolRegistry::class);
        $registry->register(new class implements AiToolInterface
        {
            public function name(): string
            {
                return 'failing_read_tool';
            }

            public function description(): string
            {
                return 'Tool khusus pengujian kegagalan.';
            }

            public function schema(): array
            {
                return [
                    'name' => $this->name(),
                    'description' => $this->description(),
                    'parameters' => ['type' => 'object', 'properties' => []],
                ];
            }

            public function isMutating(): bool
            {
                return false;
            }

            public function execute(User $user, array $arguments): array
            {
                throw new \RuntimeException('Simulated tool failure');
            }
        });
        $this->app->instance(AiToolRegistry::class, $registry);

        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: null, toolCalls: [
            new LlmToolCall(id: 'failing_1', name: 'failing_read_tool', arguments: []),
        ]));
        $client->queueResponse(new LlmResponse(content: 'Data tugas belum bisa diperiksa, tetapi materi tetap bisa disusun.'));
        $this->app->instance(LlmClientInterface::class, $client);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Bikinin materi KPL');

        $this->assertSame('completed', $result['run']->status);
        $this->assertSame('Data tugas belum bisa diperiksa, tetapi materi tetap bisa disusun.', $result['reply']);
        $step = $result['run']->steps->firstWhere('tool_name', 'failing_read_tool');
        $this->assertSame('failed', $step->status);
        $this->assertSame('tool_execution_failed', $step->private_payload['status']);
    }

    public function test_general_question_can_be_answered_without_a_polylife_tool_call(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $client = new class implements LlmClientInterface
        {
            public ?string $systemInstruction = null;

            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;
                $this->systemInstruction = $systemInstruction;

                return new LlmResponse(content: 'Tergantung genre favoritmu. Untuk aksi, coba Fullmetal Alchemist: Brotherhood.');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Anime terbaik apa?');

        $this->assertSame(1, $client->calls);
        $this->assertSame([], $result['proposals']);
        $this->assertStringContainsString('Fullmetal Alchemist', $result['reply']);
        $this->assertStringContainsString('Jawab pertanyaan umum yang aman secara natural', $client->systemInstruction);
        $this->assertStringContainsString('data pribadi pengguna yang tersimpan, wajib panggil read tool', $client->systemInstruction);
        $this->assertStringContainsString('Jangan memilih tool hanya karena menemukan kata', $client->systemInstruction);
        $this->assertStringContainsString('hasil tool terbaru lebih dipercaya daripada jawaban lama', $client->systemInstruction);
        $this->assertStringContainsString('Setelah tool terakhir selesai, berikan jawaban substantif', $client->systemInstruction);
        $this->assertDatabaseMissing('ai_action_proposals', ['user_id' => $user->id]);
    }

    public function test_coding_request_is_planned_then_delegated_with_isolated_context(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        UserAiAssistant::create([
            'user_id' => $user->id,
            'assistant_name' => 'PolyBot',
            'personality_tone' => 'friendly_peer',
            'thinking_effort' => ThinkingEffort::Low,
        ]);
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            /** @var list<LlmRequestOptions|null> */
            public array $options = [];

            public array $requests = [];

            public array $tools = [];

            public array $systemInstructions = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;
                $this->options[] = $options;
                $this->requests[] = $messages;
                $this->tools[] = $tools;
                $this->systemInstructions[] = $systemInstruction;

                if ($this->calls === 1) {
                    return new LlmResponse(null, toolCalls: [new LlmToolCall('coding_1', 'delegate_code_generation', [
                        'language' => 'html',
                        'runtime' => 'browser',
                        'files' => ['index.html'],
                        'requirements' => ['Landing page coffee shop responsif'],
                        'acceptance_criteria' => ['Dapat dibuka langsung di browser'],
                        'visual_direction' => ['style' => 'warm editorial'],
                        'runnable' => true,
                    ])], finishReason: 'tool_calls');
                }

                return new LlmResponse(
                    "```html\n<main><h1>Coffee Shop</h1></main>\n```",
                    finishReason: 'stop'
                );
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Buatkan HTML standalone untuk coffee shop.');

        $this->assertSame(2, $client->calls);
        $this->assertSame(ThinkingEffort::Low, $client->options[0]?->thinkingEffort);
        $this->assertSame(ThinkingEffort::Low, $client->options[1]?->thinkingEffort);
        $this->assertSame(16384, $client->options[1]?->maxOutputTokens);
        $this->assertContains('delegate_code_generation', array_column($client->tools[0], 'name'));
        $this->assertSame([], $client->tools[1]);
        $this->assertCount(1, $client->requests[0]);
        $this->assertCount(1, $client->requests[1]);
        $this->assertSame('Buatkan HTML standalone untuk coffee shop.', $client->requests[0][0]->content);
        $this->assertStringContainsString('delegated_coding_request', $client->requests[1][0]->content);
        $this->assertStringContainsString('DELEGASI CODING WAJIB', $client->systemInstructions[0]);
        $this->assertStringContainsString('coding agent khusus', $client->systemInstructions[1]);
        $this->assertSame("```html\n<main><h1>Coffee Shop</h1></main>\n```", $result['reply']);
        $this->assertSame('completed', $result['run']->status);
        $this->assertTrue($result['run']->steps->contains('label', 'Memahami permintaan dan konteks'));
        $delegationStep = $result['run']->steps->firstWhere('tool_name', 'delegate_code_generation');
        $this->assertNotNull($delegationStep);
        $this->assertSame('tool_call', $delegationStep->kind);
        $this->assertSame('html', $delegationStep->public_metadata['language']);
    }

    public function test_orchestrator_uses_user_thinking_effort_and_persists_reasoning_privately(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        UserAiAssistant::create([
            'user_id' => $user->id,
            'assistant_name' => 'PolyBot',
            'personality_tone' => 'friendly_peer',
            'thinking_effort' => ThinkingEffort::Low,
        ]);
        $client = new class implements LlmClientInterface
        {
            public ?LlmRequestOptions $options = null;

            public array $requests = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->options = $options;
                $this->requests[] = $messages;

                return new LlmResponse(
                    content: 'Jawaban dengan penalaran singkat.',
                    reasoningContent: 'Reasoning internal yang tidak ditampilkan.'
                );
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        $firstResult = app(AiAgentOrchestrator::class)->handle($user, 'Bantu saya memilih prioritas.');
        app(AiAgentOrchestrator::class)->handle(
            $user,
            'Lanjutkan dari pertimbangan tadi.',
            $firstResult['session']->id
        );

        $this->assertSame(ThinkingEffort::Low, $client->options?->thinkingEffort);
        $this->assertSame(
            'Reasoning internal yang tidak ditampilkan.',
            $client->requests[1][1]->reasoningContent
        );
        $message = AiChatMessage::query()->where('role', 'assistant')->oldest('id')->firstOrFail();
        $this->assertSame('Reasoning internal yang tidak ditampilkan.', $message->reasoning_content);
        $this->assertNotSame(
            'Reasoning internal yang tidak ditampilkan.',
            DB::table('ai_chat_messages')->where('id', $message->id)->value('reasoning_content')
        );
    }

    public function test_agentic_loop_deduplicates_identical_mutating_tool_calls_within_one_turn(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: null, toolCalls: [
            new LlmToolCall(id: 'duplicate_1', name: 'create_todolist', arguments: ['item' => 'Baca jurnal']),
            new LlmToolCall(id: 'duplicate_2', name: 'create_todolist', arguments: ['item' => 'Baca jurnal']),
        ]));
        $client->queueResponse(new LlmResponse(content: 'Satu proposal to-do sudah disiapkan.'));
        $this->app->instance(LlmClientInterface::class, $client);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Tambahkan Baca jurnal ke to-do');

        $this->assertSame('Satu proposal to-do sudah disiapkan.', $result['reply']);
        $this->assertCount(1, $result['proposals']);
        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertDatabaseCount('todolists', 0);
    }

    public function test_agentic_loop_forces_final_synthesis_after_tool_round_budget_is_exhausted(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;

                if ($this->calls <= 4) {
                    return new LlmResponse(content: null, toolCalls: [
                        new LlmToolCall(
                            id: 'repeated_'.$this->calls,
                            name: 'create_todolist',
                            arguments: ['item' => 'Review jurnal']
                        ),
                    ]);
                }

                return new LlmResponse(content: 'Satu proposal Review jurnal sudah disiapkan untuk konfirmasi.');
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);

        $result = app(AiAgentOrchestrator::class)->handle($user, 'Tambahkan Review jurnal ke to-do');

        $this->assertSame(5, $client->calls);
        $this->assertSame('Satu proposal Review jurnal sudah disiapkan untuk konfirmasi.', $result['reply']);
        $this->assertCount(1, $result['proposals']);
        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertDatabaseCount('todolists', 0);
    }

    public function test_agentic_loop_rejects_an_abnormally_large_tool_call_batch(): void
    {
        config(['services.ai_max_tool_calls_per_response' => 8]);
        $user = User::factory()->create(['email_verified_at' => now(), 'account_status' => 'active']);
        $client = new MockLlmClient;
        $client->queueResponse(new LlmResponse(content: null, toolCalls: array_map(
            fn (int $index): LlmToolCall => new LlmToolCall(
                id: "overflow_{$index}",
                name: 'get_pending_tasks',
                arguments: []
            ),
            range(1, 9)
        )));
        $this->app->instance(LlmClientInterface::class, $client);

        try {
            app(AiAgentOrchestrator::class)->handle($user, 'Panggil terlalu banyak alat');
            $this->fail('Tool-call overflow should fail the run.');
        } catch (AiProviderException $exception) {
            $this->assertSame('provider_tool_call_limit_exceeded', $exception->errorCode);
        }

        $this->assertDatabaseHas('ai_chat_runs', [
            'status' => 'failed',
            'error_code' => 'provider_tool_call_limit_exceeded',
        ]);
    }

    public function test_tool_registry_is_json_schema_compatible_and_gemini_adapts_types(): void
    {
        $declarations = app(AiToolRegistry::class)->getDeclarations();
        $this->assertContains('create_catatan', array_column($declarations, 'name'));
        $types = [];
        array_walk_recursive($declarations, function ($value, $key) use (&$types): void {
            if ($key === 'type') {
                $types[] = $value;
            }
        });
        $this->assertNotEmpty($types);
        $this->assertSame($types, array_map('strtolower', $types));

        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]], 'finishReason' => 'STOP']],
        ])]);
        (new GeminiLlmClient(apiKey: 'test'))->chat(
            [new LlmMessage(role: 'user', content: 'test')],
            [$declarations[0]]
        );

        Http::assertSent(fn ($request): bool => $request['tools'][0]['functionDeclarations'][0]['parameters']['type'] === 'OBJECT');
    }
}
