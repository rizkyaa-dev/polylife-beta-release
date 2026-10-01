<?php

namespace Tests\Feature\Ai;

use App\Models\Todolist;
use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use App\Services\Ai\OwnedWorkspaceRecordResolver;
use App\Services\Ai\Tools\ManageTodolistTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnedWorkspaceRecordResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_duplicates_never_produce_a_mutation_proposal(): void
    {
        $user = User::factory()->create();
        foreach (['Beli buku', 'beli buku'] as $name) {
            Todolist::create(['user_id' => $user->id, 'nama_item' => $name, 'status' => false]);
        }
        try {
            app(ManageTodolistTool::class)->execute($user, ['target' => ' Beli buku ', 'operation' => 'complete']);
            $this->fail('Ambiguous names must be rejected before a proposal is created.');
        } catch (AiActionException $error) {
            $this->assertStringContainsString('ambigu', $error->getMessage());
            $this->assertSame(0, Todolist::where('status', true)->count());
        }
    }

    public function test_unique_exact_match_takes_precedence_over_partial_matches_and_other_owners(): void
    {
        $user = User::factory()->create();
        $exact = Todolist::create(['user_id' => $user->id, 'nama_item' => 'Beli buku', 'status' => false]);
        Todolist::create(['user_id' => $user->id, 'nama_item' => 'Beli buku fisika', 'status' => false]);
        Todolist::create(['user_id' => User::factory()->create()->id, 'nama_item' => 'Beli buku', 'status' => false]);
        $this->assertSame($exact->id, $this->resolve($user, 'Beli buku')->id);
    }

    public function test_owned_query_scope_is_respected_during_exact_disambiguation(): void
    {
        $user = User::factory()->create();
        $open = Todolist::create(['user_id' => $user->id, 'nama_item' => 'Beli buku', 'status' => false]);
        Todolist::create(['user_id' => $user->id, 'nama_item' => 'Beli buku', 'status' => true]);
        $query = Todolist::where('user_id', $user->id)->where('status', false);
        $resolver = app(OwnedWorkspaceRecordResolver::class);
        $this->assertSame($open->id, $resolver->resolveFromOwnedQuery($query, ['nama_item'], 'Beli buku', 'to-do')->id);
        $this->assertSame($open->id, $resolver->resolveFromOwnedQuery($query, ['nama_item'], 'Beli', 'to-do')->id);
    }

    public function test_partial_duplicates_still_reject_ambiguity(): void
    {
        $user = User::factory()->create();
        foreach (['Beli buku fisika', 'Beli buku kimia'] as $name) {
            Todolist::create(['user_id' => $user->id, 'nama_item' => $name, 'status' => false]);
        }
        $this->expectException(AiActionException::class);
        $this->resolve($user, 'Beli buku');
    }

    private function resolve(User $user, string $name): Todolist
    {
        return app(OwnedWorkspaceRecordResolver::class)->resolve($user, Todolist::class, ['nama_item'], $name, 'to-do');
    }
}
