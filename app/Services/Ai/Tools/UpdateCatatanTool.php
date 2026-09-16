<?php

namespace App\Services\Ai\Tools;

use App\Models\Catatan;
use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\OwnedWorkspaceRecordResolver;

final class UpdateCatatanTool implements AiToolInterface
{
    public function __construct(private readonly OwnedWorkspaceRecordResolver $resolver) {}

    public function name(): string
    {
        return 'update_catatan';
    }

    public function description(): string
    {
        return 'Mengusulkan edit pada catatan yang sudah ada. Bisa menambah isi, mengganti isi, atau mengganti judul tanpa membuat duplikat.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(), 'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'target' => ['type' => 'string', 'description' => 'Judul catatan yang sudah ada'],
                    'operation' => ['type' => 'string', 'enum' => ['append', 'replace_content', 'rename']],
                    'value' => ['type' => 'string', 'description' => 'Teks yang ditambahkan, isi pengganti, atau judul baru'],
                ],
                'required' => ['target', 'operation', 'value'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        /** @var Catatan $note */
        $note = $this->resolver->resolve(
            $user, Catatan::class, ['judul'], (string) ($arguments['target'] ?? ''), 'catatan',
            fn ($query) => $query->where('status_sampah', false)
        );
        $operation = (string) ($arguments['operation'] ?? '');
        $value = trim((string) ($arguments['value'] ?? ''));
        $title = $operation === 'rename' ? $value : $note->judul;
        $content = match ($operation) {
            'append' => rtrim($note->isi)."\n\n".$value,
            'replace_content' => $value,
            default => $note->isi,
        };

        return [
            'status' => 'proposal_created', 'tool_name' => $this->name(),
            'summary' => "Edit Catatan: {$note->judul}",
            'payload' => [
                'catatan_id' => $note->id,
                'judul' => $title,
                'isi' => $content,
                'tanggal' => $note->tanggal->toDateString(),
                'show_preview' => $note->show_preview,
                'expected_updated_at' => $note->updated_at->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
