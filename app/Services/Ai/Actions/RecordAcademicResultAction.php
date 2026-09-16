<?php

namespace App\Services\Ai\Actions;

use App\Models\Ipk;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RecordAcademicResultAction implements AiWriteAction
{
    public function toolName(): string
    {
        return 'record_academic_result';
    }

    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, [
            'semester' => ['required', 'integer', 'between:1,14'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'ips_actual' => ['required', 'numeric', 'between:0,4'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:final'], 'target_mode' => ['required', 'in:ips'],
        ])->validate();
    }

    public function execute(User $user, array $payload): Model
    {
        $validated = $this->validatePayload($payload);
        $latestSemester = Ipk::query()->forUser((int) $user->id)->whereNotNull('semester')->max('semester');
        $expectedSemester = $latestSemester ? $latestSemester + 1 : 1;
        if ($validated['semester'] !== $expectedSemester) {
            throw ValidationException::withMessages([
                'semester' => "Semester harus berurutan. Semester berikutnya: {$expectedSemester}.",
            ]);
        }

        $record = Ipk::query()->create(['user_id' => $user->id, ...$validated]);
        $runningTotal = 0.0;
        $count = 0;
        Ipk::query()->forUser((int) $user->id)->whereNotNull('semester')->orderBy('semester')->get()
            ->each(function (Ipk $entry) use (&$runningTotal, &$count): void {
                if ($entry->ips_actual === null) {
                    return;
                }
                $runningTotal += $entry->ips_actual;
                $count++;
                $entry->update(['ipk_running' => round($runningTotal / $count, 2)]);
            });

        return $record->fresh();
    }
}
