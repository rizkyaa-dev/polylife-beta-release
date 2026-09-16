<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use App\Services\Ai\Exceptions\AiActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AiWriteActionRegistry
{
    /** @var array<string, AiWriteAction> */
    private array $actions;

    public function __construct(
        CreateJadwalAction $createJadwal,
        CreateKeuanganAction $createKeuangan,
        CreateTodolistAction $createTodolist,
        CreateCatatanAction $createCatatan,
        CreateTugasAction $createTugas,
        CreateReminderAction $createReminder,
        ManageTodolistAction $manageTodolist,
        ManageTugasAction $manageTugas,
        UpdateCatatanAction $updateCatatan,
        SetFinanceBudgetAction $setFinanceBudget,
        CreateCourseAction $createCourse,
        CreateKegiatanAction $createKegiatan,
        RecordAcademicResultAction $recordAcademicResult,
        UpdateJadwalAction $updateJadwal,
        UpdateKegiatanAction $updateKegiatan,
        UpdateReminderAction $updateReminder,
        UpdateKeuanganAction $updateKeuangan,
        UpdateCourseAction $updateCourse,
        UpdateTugasAction $updateTugas,
        MarkAnnouncementReadAction $markAnnouncementRead,
        DuplicateScheduleAction $duplicateSchedule,
        CopyCourseToSemesterAction $copyCourseToSemester,
        ArchiveNoteAction $archiveNote,
        RestoreNoteAction $restoreNote,
        SnoozeReminderAction $snoozeReminder
    ) {
        $this->actions = [];

        foreach ([
            $createJadwal, $createKeuangan, $createTodolist, $createCatatan, $createTugas, $createReminder,
            $manageTodolist, $manageTugas, $updateCatatan, $setFinanceBudget,
            $createCourse, $createKegiatan, $recordAcademicResult,
            $updateJadwal, $updateKegiatan, $updateReminder, $updateKeuangan, $updateCourse, $updateTugas,
            $markAnnouncementRead, $duplicateSchedule, $copyCourseToSemester, $archiveNote, $restoreNote,
            $snoozeReminder,
        ] as $action) {
            $this->actions[$action->toolName()] = $action;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validatePayload(string $toolName, array $payload): array
    {
        return $this->actionFor($toolName)->validatePayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(string $toolName, User $user, array $payload): Model
    {
        try {
            return $this->actionFor($toolName)->execute($user, $payload);
        } catch (ModelNotFoundException|HttpExceptionInterface) {
            throw new AiActionException('Target proposal sudah tidak tersedia atau tidak dapat diakses. Buat proposal baru.');
        }
    }

    private function actionFor(string $toolName): AiWriteAction
    {
        $action = $this->actions[$toolName] ?? null;

        if (! $action) {
            throw new AiActionException("Tool '{$toolName}' tidak mendukung eksekusi otomatis.");
        }

        return $action;
    }
}
