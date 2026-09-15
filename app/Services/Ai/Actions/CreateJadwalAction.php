<?php

namespace App\Services\Ai\Actions;

use App\Actions\Jadwal\StoreJadwalAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;

class CreateJadwalAction implements AiWriteAction
{
    public function __construct(
        private readonly StoreJadwalAction $storeJadwal
    ) {}

    public function toolName(): string
    {
        return 'create_jadwal';
    }

    public function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'title' => ['required', 'string', 'max:180'],
            'jenis' => ['required', 'in:kuliah,libur,uts,uas,lomba,lainnya'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'location' => ['nullable', 'string', 'max:180'],
            'catatan_tambahan' => ['nullable', 'string'],
        ]);

        $validator->after(function (LaravelValidator $validator) use ($payload): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::parse($payload['tanggal_mulai'].' '.$payload['start_time']);
            $end = Carbon::parse($payload['tanggal_selesai'].' '.$payload['end_time']);

            if (! $end->greaterThan($start)) {
                $validator->errors()->add('end_time', 'Waktu selesai harus setelah waktu mulai.');
            }
        });

        return $validator->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);

        return ($this->storeJadwal)((int) $user->id, $validated, [], false, []);
    }
}
