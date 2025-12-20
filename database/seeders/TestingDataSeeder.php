<?php

namespace Database\Seeders;

use App\Models\Catatan;
use App\Models\Ipk;
use App\Models\IpkCourse;
use App\Models\Jadwal;
use App\Models\Kegiatan;
use App\Models\Matkul;
use App\Models\Reminder;
use App\Models\Todolist;
use App\Models\Tugas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class TestingDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', '1@2.com')->first();

        if (!$user) {
            $user = User::create([
                'name'     => 'testing',
                'email'    => '1@2.com',
                'password' => Hash::make('1'),
            ]);
        }

        $matkuls = $this->seedMatkuls($user);
        $jadwals = $this->seedJadwals($user, $matkuls);
        $kegiatans = $this->seedKegiatans($jadwals);
        $tugas = $this->seedTugas($user, $matkuls);
        $this->seedCatatans($user);
        $ipks = $this->seedIpks($user);
        $this->seedIpkCourses($user, $matkuls, $ipks);
        $todolists = $this->seedTodolists($user);
        $this->seedReminders($user, $todolists, $tugas, $jadwals, $kegiatans);
    }

    private function seedMatkuls(User $user): Collection
    {
        $definitions = [
            [
                'kode'        => 'IF101',
                'nama'        => 'Algoritma dan Struktur Data',
                'kelas'       => 'A',
                'dosen'       => 'Dr. Siti Rahayu',
                'semester'    => 2,
                'sks'         => 3,
                'hari'        => 'Senin',
                'jam_mulai'   => '08:00',
                'jam_selesai' => '09:40',
                'ruangan'     => 'D201',
                'warna_label' => '#2563eb',
                'catatan'     => 'Fokus pada rekursi dan struktur linked list.',
            ],
            [
                'kode'        => 'IF201',
                'nama'        => 'Pemrograman Web Lanjut',
                'kelas'       => 'B',
                'dosen'       => 'Ir. Bayu Saputra',
                'semester'    => 4,
                'sks'         => 3,
                'hari'        => 'Rabu',
                'jam_mulai'   => '10:00',
                'jam_selesai' => '11:40',
                'ruangan'     => 'Lab 1',
                'warna_label' => '#16a34a',
                'catatan'     => 'Project full-stack dengan Laravel + Vue.',
            ],
            [
                'kode'        => 'IF301',
                'nama'        => 'Manajemen Proyek TIK',
                'kelas'       => 'A',
                'dosen'       => 'Dr. Johan Wirawan',
                'semester'    => 6,
                'sks'         => 2,
                'hari'        => 'Jumat',
                'jam_mulai'   => '13:00',
                'jam_selesai' => '14:40',
                'ruangan'     => 'C105',
                'warna_label' => '#f97316',
                'catatan'     => 'Menyusun proposal proyek akhir.',
            ],
        ];

        $matkuls = collect();

        foreach ($definitions as $definition) {
            $matkuls[$definition['kode']] = Matkul::updateOrCreate(
                ['user_id' => $user->id, 'kode' => $definition['kode']],
                array_merge($definition, ['user_id' => $user->id])
            );
        }

        return $matkuls;
    }

    private function seedJadwals(User $user, Collection $matkuls): Collection
    {
        $definitions = [
            [
                'key'                => 'minggu_integrasi',
                'jenis'              => 'kuliah',
                'tanggal_mulai'      => '2025-11-03',
                'tanggal_selesai'    => '2025-11-07',
                'semester'           => 4,
                'matkuls'            => ['IF101', 'IF201'],
                'catatan_tambahan'   => 'Minggu evaluasi progress proyek mini.',
            ],
            [
                'key'                => 'uts_if101',
                'jenis'              => 'uts',
                'tanggal_mulai'      => '2025-11-15',
                'tanggal_selesai'    => '2025-11-15',
                'semester'           => 2,
                'matkuls'            => ['IF101'],
                'catatan_tambahan'   => 'UTS tertulis, siapkan kartu ujian.',
            ],
            [
                'key'                => 'lomba_mobile',
                'jenis'              => 'lomba',
                'tanggal_mulai'      => '2025-11-28',
                'tanggal_selesai'    => '2025-11-30',
                'semester'           => 6,
                'matkuls'            => ['IF201', 'IF301'],
                'catatan_tambahan'   => 'Hackathon mobile apps 48 jam.',
            ],
        ];

        $matkulByCode = $matkuls->mapWithKeys(fn (Matkul $matkul) => [$matkul->kode => $matkul->id]);

        $jadwals = collect();

        foreach ($definitions as $definition) {
            $matkulIds = collect($definition['matkuls'])
                ->map(fn (string $code) => $matkulByCode[$code] ?? null)
                ->filter()
                ->values();

            $definition['matkul_id_list'] = $matkulIds->isEmpty()
                ? null
                : $matkulIds->implode(';') . ';';

            $key = $definition['key'];
            unset($definition['key'], $definition['matkuls']);

            $jadwals[$key] = Jadwal::updateOrCreate(
                [
                    'user_id'       => $user->id,
                    'jenis'         => $definition['jenis'],
                    'tanggal_mulai' => $definition['tanggal_mulai'],
                ],
                array_merge($definition, ['user_id' => $user->id])
            );
        }

        return $jadwals;
    }

    private function seedKegiatans(Collection $jadwals): Collection
    {
        $definitions = [
            [
                'key'              => 'diskusi_algoritma',
                'jadwal_key'       => 'minggu_integrasi',
                'nama_kegiatan'    => 'Diskusi Pendalaman Algoritma',
                'lokasi'           => 'Lab Riset 2',
                'waktu'            => '10:00',
                'tanggal_deadline' => '2025-11-06',
                'status'           => 'berlangsung',
            ],
            [
                'key'              => 'gladi_uts',
                'jadwal_key'       => 'uts_if101',
                'nama_kegiatan'    => 'Gladi Bersih UTS IF101',
                'lokasi'           => 'Auditorium Utama',
                'waktu'            => '14:00',
                'tanggal_deadline' => '2025-11-13',
                'status'           => 'belum_dimulai',
            ],
        ];

        $kegiatans = collect();

        foreach ($definitions as $definition) {
            $jadwal = $jadwals[$definition['jadwal_key']] ?? null;

            if (!$jadwal) {
                continue;
            }

            $key = $definition['key'];
            unset($definition['key'], $definition['jadwal_key']);

            $kegiatans[$key] = Kegiatan::updateOrCreate(
                [
                    'jadwal_id'    => $jadwal->id,
                    'nama_kegiatan'=> $definition['nama_kegiatan'],
                ],
                array_merge($definition, ['jadwal_id' => $jadwal->id])
            );
        }

        return $kegiatans;
    }

    private function seedTugas(User $user, Collection $matkuls): Collection
    {
        $definitions = [
            [
                'key'           => 'queue',
                'nama_tugas'    => 'Implementasi Queue Dinamis',
                'matkul_code'   => 'IF101',
                'deskripsi'     => 'Implementasi queue berbasis linked list beserta unit test.',
                'deadline'      => Carbon::parse('2025-11-10 23:59:00'),
                'status_selesai'=> false,
            ],
            [
                'key'           => 'ui_dashboard',
                'nama_tugas'    => 'Prototype UI Dashboard',
                'matkul_code'   => 'IF201',
                'deskripsi'     => 'Gunakan Tailwind + Chart.js untuk visualisasi data.',
                'deadline'      => Carbon::parse('2025-11-18 17:00:00'),
                'status_selesai'=> true,
            ],
            [
                'key'           => 'proposal_proyek',
                'nama_tugas'    => 'Draft Proposal Proyek Akhir',
                'matkul_code'   => 'IF301',
                'deskripsi'     => 'Minimal 10 halaman mencakup scope, timeline, dan risiko.',
                'deadline'      => Carbon::parse('2025-11-25 09:00:00'),
                'status_selesai'=> false,
            ],
        ];

        $matkulByCode = $matkuls->mapWithKeys(fn (Matkul $matkul) => [$matkul->kode => $matkul->id]);
        $tugas = collect();

        foreach ($definitions as $definition) {
            $matkulId = $matkulByCode[$definition['matkul_code']] ?? null;
            $key = $definition['key'];
            unset($definition['key'], $definition['matkul_code']);

            $tugas[$key] = Tugas::updateOrCreate(
                [
                    'user_id'    => $user->id,
                    'nama_tugas' => $definition['nama_tugas'],
                ],
                array_merge($definition, [
                    'user_id'   => $user->id,
                    'matkul_id' => $matkulId,
                ])
            );
        }

        return $tugas;
    }

    private function seedCatatans(User $user): void
    {
        $notes = [
            [
                'judul'         => 'Rangkuman Kuliah Minggu 3',
                'isi'           => 'Catatan mengenai materi graf dan algoritma pencarian.',
                'tanggal'       => '2025-11-05',
                'status_sampah' => false,
            ],
            [
                'judul'         => 'Ide Aplikasi Mobile',
                'isi'           => 'Reminder untuk mengerjakan fitur notifikasi adaptif.',
                'tanggal'       => '2025-11-12',
                'status_sampah' => false,
            ],
        ];

        foreach ($notes as $note) {
            Catatan::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'judul'   => $note['judul'],
                ],
                array_merge($note, ['user_id' => $user->id])
            );
        }
    }

    private function seedIpks(User $user): Collection
    {
        $records = [
            [
                'semester'      => 2,
                'academic_year' => '2024/2025',
                'ips_actual'    => 3.45,
                'ips_target'    => 3.60,
                'ipk_running'   => 3.45,
                'ipk_target'    => 3.70,
                'status'        => 'final',
                'remarks'       => 'Semester kedua selesai dengan IPS stabil.',
            ],
            [
                'semester'      => 4,
                'academic_year' => '2025/2026',
                'ips_actual'    => 3.55,
                'ips_target'    => 3.70,
                'ipk_running'   => 3.50,
                'ipk_target'    => 3.75,
                'status'        => 'in_progress',
                'remarks'       => 'Sedang mengejar peningkatan IPS untuk capstone.',
            ],
            [
                'semester'      => 6,
                'academic_year' => '2026/2027',
                'ips_actual'    => null,
                'ips_target'    => 3.80,
                'ipk_running'   => null,
                'ipk_target'    => 3.80,
                'status'        => 'planned',
                'remarks'       => 'Target akhir menuju kelulusan.',
            ],
        ];

        $ipks = collect();

        foreach ($records as $record) {
            $ipks[$record['semester']] = Ipk::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'semester'=> $record['semester'],
                ],
                array_merge($record, ['user_id' => $user->id])
            );
        }

        return $ipks;
    }

    private function seedIpkCourses(User $user, Collection $matkuls, Collection $ipks): void
    {
        $definitions = [
            [
                'semester' => 2,
                'courses' => [
                    [
                        'matkul_code'        => 'IF101',
                        'course_code'        => 'IF101',
                        'course_name'        => 'Algoritma dan Struktur Data',
                        'sks'                => 3,
                        'grade_point'        => 3.67,
                        'grade_letter'       => 'A-',
                        'target_grade_point' => 3.60,
                        'score_actual'       => 88.50,
                        'score_target'       => 85.00,
                        'is_retake'          => false,
                        'status'             => 'completed',
                        'notes'              => 'Nilai di atas target, pertahankan ritme belajar.',
                    ],
                    [
                        'matkul_code'        => null,
                        'course_code'        => 'IF150',
                        'course_name'        => 'Pengantar Basis Data',
                        'sks'                => 3,
                        'grade_point'        => 3.30,
                        'grade_letter'       => 'B+',
                        'target_grade_point' => 3.40,
                        'score_actual'       => 82.00,
                        'score_target'       => 83.00,
                        'is_retake'          => false,
                        'status'             => 'completed',
                        'notes'              => 'Masih bisa ditingkatkan lewat latihan soal agregasi.',
                    ],
                ],
            ],
            [
                'semester' => 4,
                'courses' => [
                    [
                        'matkul_code'        => 'IF201',
                        'course_code'        => 'IF201',
                        'course_name'        => 'Pemrograman Web Lanjut',
                        'sks'                => 3,
                        'grade_point'        => 3.00,
                        'grade_letter'       => 'B',
                        'target_grade_point' => 3.70,
                        'score_actual'       => 78.00,
                        'score_target'       => 92.00,
                        'is_retake'          => false,
                        'status'             => 'in_progress',
                        'notes'              => 'Naikkan nilai ujian akhir untuk capai target IPS.',
                    ],
                    [
                        'matkul_code'        => null,
                        'course_code'        => 'IF230',
                        'course_name'        => 'Interaksi Manusia dan Komputer',
                        'sks'                => 2,
                        'grade_point'        => null,
                        'grade_letter'       => null,
                        'target_grade_point' => 3.50,
                        'score_actual'       => null,
                        'score_target'       => 85.00,
                        'is_retake'          => false,
                        'status'             => 'planned',
                        'notes'              => 'Susun target prototipe high fidelity sebelum UTS.',
                    ],
                ],
            ],
            [
                'semester' => 6,
                'courses' => [
                    [
                        'matkul_code'        => 'IF301',
                        'course_code'        => 'IF301',
                        'course_name'        => 'Manajemen Proyek TIK',
                        'sks'                => 2,
                        'grade_point'        => null,
                        'grade_letter'       => null,
                        'target_grade_point' => 3.80,
                        'score_actual'       => null,
                        'score_target'       => 90.00,
                        'is_retake'          => false,
                        'status'             => 'planned',
                        'notes'              => 'Bidik nilai A untuk mengamankan IPK lulus.',
                    ],
                    [
                        'matkul_code'        => null,
                        'course_code'        => 'IF360',
                        'course_name'        => 'Studi Independen AI',
                        'sks'                => 3,
                        'grade_point'        => null,
                        'grade_letter'       => null,
                        'target_grade_point' => 3.90,
                        'score_actual'       => null,
                        'score_target'       => 92.00,
                        'is_retake'          => false,
                        'status'             => 'planned',
                        'notes'              => 'Eksperimen capstone machine learning untuk IPK target.',
                    ],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $ipk = $ipks->get($definition['semester']);

            if (!$ipk) {
                continue;
            }

            foreach ($definition['courses'] as $course) {
                $matkul = $matkuls->get($course['matkul_code'] ?? null);
                $payload = array_merge(
                    [
                        'ipk_id'              => $ipk->id,
                        'user_id'             => $user->id,
                        'matkul_id'           => $matkul?->id,
                        'semester_reference'  => $course['semester_reference'] ?? $ipk->semester,
                    ],
                    collect($course)->except('matkul_code', 'semester_reference')->toArray()
                );

                IpkCourse::updateOrCreate(
                    [
                        'user_id'     => $user->id,
                        'ipk_id'      => $ipk->id,
                        'course_code' => $course['course_code'],
                    ],
                    $payload
                );
            }
        }
    }

    private function seedTodolists(User $user): Collection
    {
        $items = [
            [
                'key'       => 'daily_check',
                'nama_item' => 'Cek kalender akademik',
                'status'    => false,
            ],
            [
                'key'       => 'sync_repo',
                'nama_item' => 'Sinkronkan repo proyek tim',
                'status'    => true,
            ],
            [
                'key'       => 'prepare_pitch',
                'nama_item' => 'Latihan pitch deck lomba',
                'status'    => false,
            ],
        ];

        $todolists = collect();

        foreach ($items as $item) {
            $key = $item['key'];
            unset($item['key']);

            $todolists[$key] = Todolist::updateOrCreate(
                [
                    'user_id'   => $user->id,
                    'nama_item' => $item['nama_item'],
                ],
                array_merge($item, ['user_id' => $user->id])
            );
        }

        return $todolists;
    }

    private function seedReminders(
        User $user,
        Collection $todolists,
        Collection $tugas,
        Collection $jadwals,
        Collection $kegiatans
    ): void {
        $definitions = [
            [
                'todolist_key' => 'daily_check',
                'waktu_reminder' => Carbon::parse('2025-11-09 07:00:00'),
                'aktif' => true,
            ],
            [
                'tugas_key' => 'queue',
                'waktu_reminder' => Carbon::parse('2025-11-09 20:00:00'),
                'aktif' => true,
            ],
            [
                'jadwal_key' => 'uts_if101',
                'kegiatan_key' => 'gladi_uts',
                'waktu_reminder' => Carbon::parse('2025-11-27 18:00:00'),
                'aktif' => true,
            ],
        ];

        foreach ($definitions as $definition) {
            $payload = [
                'user_id'      => $user->id,
                'todolist_id'  => optional($todolists->get($definition['todolist_key'] ?? null))->id,
                'tugas_id'     => optional($tugas->get($definition['tugas_key'] ?? null))->id,
                'jadwal_id'    => optional($jadwals->get($definition['jadwal_key'] ?? null))->id,
                'kegiatan_id'  => optional($kegiatans->get($definition['kegiatan_key'] ?? null))->id,
                'waktu_reminder'=> $definition['waktu_reminder'],
                'aktif'        => $definition['aktif'],
            ];

            Reminder::updateOrCreate(
                [
                    'user_id'       => $user->id,
                    'waktu_reminder'=> $definition['waktu_reminder'],
                ],
                $payload
            );
        }
    }
}
