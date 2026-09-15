<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\UserTimeContext;
use Illuminate\Support\Carbon;

class CreateKeuanganTool implements AiToolInterface
{
    public function __construct(private readonly UserTimeContext $timeContext) {}

    public function name(): string
    {
        return 'create_keuangan';
    }

    public function description(): string
    {
        return 'Mengusulkan pencatatan transaksi keuangan (pemasukan atau pengeluaran) baru milik pengguna.';
    }

    public function schema(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'jenis' => [
                        'type' => 'string',
                        'description' => 'Jenis transaksi: pengeluaran atau pemasukan',
                    ],
                    'kategori' => [
                        'type' => 'string',
                        'description' => 'Kategori transaksi (contoh: Makanan & Minuman, Transportasi, Kuliah, Belanja, Gaji)',
                    ],
                    'nominal' => [
                        'type' => 'number',
                        'description' => 'Jumlah uang dalam rupiah (tanpa titik atau koma)',
                    ],
                    'tanggal' => [
                        'type' => 'string',
                        'description' => 'Tanggal transaksi format YYYY-MM-DD (default hari ini)',
                    ],
                    'deskripsi' => [
                        'type' => 'string',
                        'description' => 'Keterangan transaksi (opsional)',
                    ],
                ],
                'required' => ['jenis', 'kategori', 'nominal'],
            ],
        ];
    }

    public function isMutating(): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $jenis = strtolower(trim((string) ($arguments['jenis'] ?? 'pengeluaran')));
        if (! in_array($jenis, ['pemasukan', 'pengeluaran'], true)) {
            $jenis = 'pengeluaran';
        }

        $kategori = trim((string) ($arguments['kategori'] ?? 'Lainnya'));
        $nominal = max(0, (float) ($arguments['nominal'] ?? 0));
        $tanggal = isset($arguments['tanggal'])
            ? $this->timeContext->parse($user, $arguments['tanggal'])->toDateString()
            : $this->timeContext->now($user)->toDateString();
        $deskripsi = isset($arguments['deskripsi']) ? trim((string) $arguments['deskripsi']) : null;

        $summary = sprintf(
            '%s: Rp %s untuk %s pada %s%s',
            ucfirst($jenis),
            number_format($nominal, 0, ',', '.'),
            $kategori,
            Carbon::parse($tanggal)->translatedFormat('d M Y'),
            $deskripsi ? " ({$deskripsi})" : ''
        );

        return [
            'status' => 'proposal_created',
            'tool_name' => $this->name(),
            'summary' => $summary,
            'payload' => [
                'jenis' => $jenis,
                'kategori' => $kategori,
                'nominal' => $nominal,
                'tanggal' => $tanggal,
                'deskripsi' => $deskripsi,
            ],
        ];
    }
}
