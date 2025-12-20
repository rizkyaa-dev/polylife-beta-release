<?php

namespace App\Http\Controllers;

use App\Models\NilaiMutu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NilaiMutuController extends Controller
{
    public function index()
    {
        $nilaiMutus = NilaiMutu::where('user_id', Auth::id())
            ->orderByDesc('is_active')
            ->orderBy('kampus')
            ->get();

        return view('nilai-mutu.index', compact('nilaiMutus'));
    }

    public function create()
    {
        $nilaiMutu = new NilaiMutu([
            'is_active' => true,
        ]);

        return view('nilai-mutu.create', compact('nilaiMutu'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['user_id'] = Auth::id();

        NilaiMutu::create($data);

        return redirect()->route('nilai-mutu.index')->with('success', 'Profil nilai mutu berhasil disimpan.');
    }

    public function edit(NilaiMutu $nilaiMutu)
    {
        $this->authorizeAccess($nilaiMutu);

        return view('nilai-mutu.edit', compact('nilaiMutu'));
    }

    public function update(Request $request, NilaiMutu $nilaiMutu)
    {
        $this->authorizeAccess($nilaiMutu);

        $data = $this->validatedData($request);
        $nilaiMutu->update($data);

        return redirect()->route('nilai-mutu.index')->with('success', 'Profil nilai mutu diperbarui.');
    }

    public function destroy(NilaiMutu $nilaiMutu)
    {
        $this->authorizeAccess($nilaiMutu);
        $nilaiMutu->delete();

        return redirect()->route('nilai-mutu.index')->with('success', 'Profil nilai mutu dihapus.');
    }

    private function validatedData(Request $request): array
    {
        $baseRules = [
            'kampus' => 'nullable|string|max:150',
            'program_studi' => 'nullable|string|max:150',
            'kurikulum' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'grade_mode' => 'required|in:grades_plus_minus,grades_ab',
            'grades_plus_minus' => 'nullable|array',
            'grades_plus_minus.*.letter' => 'nullable|string|max:3',
            'grades_plus_minus.*.min_score' => 'nullable|numeric',
            'grades_plus_minus.*.max_score' => 'nullable|numeric',
            'grades_plus_minus.*.grade_point' => 'nullable|numeric',
            'grades_ab' => 'nullable|array',
            'grades_ab.*.letter' => 'nullable|string|max:3',
            'grades_ab.*.min_score' => 'nullable|numeric',
            'grades_ab.*.max_score' => 'nullable|numeric',
            'grades_ab.*.grade_point' => 'nullable|numeric',
        ];

        $validated = $request->validate($baseRules);

        $validated['is_active'] = $request->boolean('is_active');
        $mode = $validated['grade_mode'];
        $validated['grades_plus_minus'] = $mode === 'grades_plus_minus'
            ? $this->sanitizeGrades($request->input('grades_plus_minus', []))
            : [];
        $validated['grades_ab'] = $mode === 'grades_ab'
            ? $this->sanitizeGrades($request->input('grades_ab', []))
            : [];

        return $validated;
    }

    private function sanitizeGrades(array $rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                $letter = strtoupper(trim($row['letter'] ?? ''));
                $min = isset($row['min_score']) && $row['min_score'] !== '' ? (float) $row['min_score'] : null;
                $max = isset($row['max_score']) && $row['max_score'] !== '' ? (float) $row['max_score'] : null;
                $point = isset($row['grade_point']) && $row['grade_point'] !== '' ? (float) $row['grade_point'] : null;

                return [
                    'letter' => $letter,
                    'min_score' => $min,
                    'max_score' => $max,
                    'grade_point' => $point,
                ];
            })
            ->filter(function ($row) {
                return $row['letter'] !== '' && ($row['min_score'] !== null || $row['max_score'] !== null || $row['grade_point'] !== null);
            })
            ->values()
            ->all();
    }

    private function authorizeAccess(NilaiMutu $nilaiMutu): void
    {
        if ($nilaiMutu->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }
}
