<?php

namespace App\Services\Ai\Design;

use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\AiDesignIntent;

/** The main model resolves semantics; this service only applies bounded design decisions. */
final class AiDesignIntentResolver
{
    public const VERSION = 'intent-design-v2-quality';

    public function __construct(private readonly AiDesignTokenCompiler $compiler) {}

    public function supports(AiCodingBrief $brief): bool
    {
        return in_array($brief->language, ['html', 'css'], true)
            || ($brief->language === 'php' && $brief->visualDirection !== [])
            || (($brief->designIntent !== null || $brief->visualDirection !== [])
                && in_array($brief->runtime, ['browser', 'mobile'], true));
    }

    /** @return array<string, mixed>|null */
    public function resolve(AiCodingBrief $brief, bool $revision): ?array
    {
        if (! $this->supports($brief)) {
            return null;
        }
        $intent = $brief->designIntent ?? AiDesignIntent::fromArray([]);
        $goal = $intent->value('goal');
        $composition = match ($goal) {
            'showcase' => 'Prioritaskan bukti karya; satu fokus pembuka kuat, proyek utama lebih menonjol daripada metadata. Jangan membuat semua section menjadi kartu identik.',
            'conversion' => 'Jelaskan nilai produk dan tindakan utama dengan urutan jelas; gunakan bukti yang benar-benar tersedia. Jangan mengarang urgensi, diskon atau testimonial.',
            'reading' => 'Utamakan alur baca, measure teks dan navigasi isi bila diperlukan. Hindari dekorasi yang memotong konsentrasi; heading mengikuti struktur informasi.',
            'productivity' => 'Utamakan tugas berulang, scan cepat, alignment data dan state yang jelas. Jangan memakai hero marketing atau whitespace besar pada area kerja.',
            'information' => 'Susun informasi menurut pertanyaan pengguna; gunakan heading deskriptif, grouping dan pola navigasi yang familiar.',
            default => 'Pilih satu jangkar konten, hierarki dan arah visual koheren dari tujuan brief. Unknown tidak berarti abu-abu atau generik; jangan mengarang persona atau menambahkan section default.',
        };
        $expression = match ($intent->value('expression')) {
            'expressive' => 'Identitas boleh kuat melalui komposisi, tipografi atau motif yang relevan; ekspresif bukan kewajiban animasi, gradient atau warna neon.',
            'restrained' => 'Gunakan penekanan terukur, dekorasi terbatas dan ritme tenang; hindari kontras hierarki yang terlalu datar.',
            default => 'Seimbangkan identitas dan keterbacaan; hindari semua elemen sama kuat atau semua area berbentuk kartu.',
        };

        return [
            'version' => self::VERSION,
            'provenance' => $brief->designIntent === null ? 'neutral fallback; audience unknown' : 'planner proposal, not verified user facts',
            'application' => $revision ? 'preserve source design; recommendations only for requested changes' : 'recommendations; explicit brief and visual direction take precedence',
            'intent' => $intent->toArray(),
            'decisions' => [$composition, $expression,
                $intent->value('session') === 'sustained'
                    ? 'Untuk penggunaan lama, batasi aksen luas dan gerak persisten, pertahankan label terbaca; jangan menganggap dark mode otomatis lebih nyaman.'
                    : 'Gunakan aksen untuk fokus tindakan/informasi penting, bukan memenuhi kuota warna.'],
            // In revisions, never invent replacement colors, dimensions or type scales.
            'tokens' => $revision ? null : $this->compiler->compile($intent),
            'review' => ['hierarki sesuai tugas', 'komposisi dan ritme tidak monoton', 'tipografi dan spacing konsisten',
                'reflow mobile dan teks panjang', 'keyboard, focus dan target kontrol', 'kontras computed, state dan background aktual'],
            'render_verification' => 'unverified; token checks and self-review are not a browser test',
        ];
    }
}
