<?php

namespace App\Services\Ai;

use App\Models\AiActionProposal;
use App\Models\User;
use App\Services\Ai\Actions\AiWriteActionRegistry;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Support\Facades\DB;

class ActionProposalExecutor
{
    public function __construct(
        private readonly AiWriteActionRegistry $actions
    ) {}

    /**
     * Recursively sort array keys to guarantee deterministic serialization for HMAC signing.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function canonicalizePayload(array $payload): array
    {
        ksort($payload);
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::canonicalizePayload($value);
            }
        }

        return $payload;
    }

    /**
     * @return array{
     *     proposal: AiActionProposal,
     *     created_record: mixed
     * }
     */
    public function execute(User $user, string $actionId, string $signature): array
    {
        $proposal = null;
        $createdRecord = null;

        DB::transaction(function () use ($user, $actionId, $signature, &$proposal, &$createdRecord): void {
            $proposal = AiActionProposal::query()
                ->where('action_id', $actionId)
                ->lockForUpdate()
                ->first();

            if (! $proposal) {
                throw new AiActionException('Proposal tidak ditemukan atau sudah tidak tersedia.');
            }

            if ((int) $proposal->user_id !== (int) $user->id) {
                throw new AiActionException('Akses ditolak: Proposal ini bukan milik akun Anda.');
            }

            if (! $proposal->isPending()) {
                throw new AiActionException('Proposal sudah diproses atau telah kadaluarsa.');
            }

            $canonicalPayload = self::canonicalizePayload((array) $proposal->payload_json);

            $expectedSignature = hash_hmac(
                'sha256',
                $proposal->action_id.'|'.$proposal->user_id.'|'.$proposal->tool_name.'|'.json_encode($canonicalPayload),
                (string) config('app.key')
            );

            if (! hash_equals($expectedSignature, $signature)) {
                throw new AiActionException('Tanda tangan proposal tidak valid atau data telah diubah.');
            }

            $payload = (array) $proposal->payload_json;

            $createdRecord = $this->actions->execute($proposal->tool_name, $user, $payload);

            $proposal->update([
                'status' => 'confirmed',
                'executed_at' => now(),
            ]);
        });

        return [
            'proposal' => $proposal->fresh(),
            'created_record' => $createdRecord,
        ];
    }

    public function reject(User $user, string $actionId): AiActionProposal
    {
        $proposal = null;

        DB::transaction(function () use ($user, $actionId, &$proposal): void {
            $proposal = AiActionProposal::query()
                ->where('action_id', $actionId)
                ->lockForUpdate()
                ->first();

            if (! $proposal) {
                throw new AiActionException('Proposal tidak ditemukan atau sudah tidak tersedia.');
            }

            if ((int) $proposal->user_id !== (int) $user->id) {
                throw new AiActionException('Akses ditolak: Proposal ini bukan milik akun Anda.');
            }

            if (! $proposal->isPending()) {
                throw new AiActionException('Proposal sudah diproses atau telah kadaluarsa.');
            }

            $proposal->update([
                'status' => 'rejected',
            ]);
        });

        return $proposal->fresh();
    }
}
