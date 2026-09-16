<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiToolInterface;
use App\Services\Ai\Tools\ArchiveNoteTool;
use App\Services\Ai\Tools\CheckScheduleConflictsTool;
use App\Services\Ai\Tools\CompareCourseWorkloadTool;
use App\Services\Ai\Tools\CopyCourseToSemesterTool;
use App\Services\Ai\Tools\CreateCatatanTool;
use App\Services\Ai\Tools\CreateCourseTool;
use App\Services\Ai\Tools\CreateJadwalTool;
use App\Services\Ai\Tools\CreateKegiatanTool;
use App\Services\Ai\Tools\CreateKeuanganTool;
use App\Services\Ai\Tools\CreateReminderTool;
use App\Services\Ai\Tools\CreateTodolistTool;
use App\Services\Ai\Tools\CreateTugasTool;
use App\Services\Ai\Tools\DetectDuplicateRecordsTool;
use App\Services\Ai\Tools\DuplicateScheduleTool;
use App\Services\Ai\Tools\ExportWorkspaceSummaryTool;
use App\Services\Ai\Tools\FindFreeTimeSlotsTool;
use App\Services\Ai\Tools\GetAcademicSummaryTool;
use App\Services\Ai\Tools\GetAnnouncementsTool;
use App\Services\Ai\Tools\GetCourseOverviewTool;
use App\Services\Ai\Tools\GetCoursesTool;
use App\Services\Ai\Tools\GetFinancialSummaryTool;
use App\Services\Ai\Tools\GetGradeScaleTool;
use App\Services\Ai\Tools\GetPendingTasksTool;
use App\Services\Ai\Tools\GetRemindersTool;
use App\Services\Ai\Tools\GetUpcomingScheduleTool;
use App\Services\Ai\Tools\GetWorkspaceOverviewTool;
use App\Services\Ai\Tools\ManageTodolistTool;
use App\Services\Ai\Tools\ManageTugasTool;
use App\Services\Ai\Tools\MarkAnnouncementReadTool;
use App\Services\Ai\Tools\PrepareWeeklyReviewTool;
use App\Services\Ai\Tools\PreviewScheduleChangeTool;
use App\Services\Ai\Tools\RecordAcademicResultTool;
use App\Services\Ai\Tools\RestoreNoteTool;
use App\Services\Ai\Tools\SearchCatatanTool;
use App\Services\Ai\Tools\SearchCoursesTool;
use App\Services\Ai\Tools\SearchFinancialTransactionsTool;
use App\Services\Ai\Tools\SearchSchedulesTool;
use App\Services\Ai\Tools\SearchTasksTool;
use App\Services\Ai\Tools\SetFinanceBudgetTool;
use App\Services\Ai\Tools\SnoozeReminderTool;
use App\Services\Ai\Tools\SuggestBudgetTool;
use App\Services\Ai\Tools\SuggestFinancialAnomaliesTool;
use App\Services\Ai\Tools\SuggestStudyPlanTool;
use App\Services\Ai\Tools\SuggestTaskBreakdownTool;
use App\Services\Ai\Tools\UpdateCatatanTool;
use App\Services\Ai\Tools\UpdateCourseTool;
use App\Services\Ai\Tools\UpdateJadwalTool;
use App\Services\Ai\Tools\UpdateKegiatanTool;
use App\Services\Ai\Tools\UpdateKeuanganTool;
use App\Services\Ai\Tools\UpdateReminderTool;
use App\Services\Ai\Tools\UpdateTugasTool;

class AiToolRegistry
{
    /**
     * @var array<string, AiToolInterface>
     */
    private array $tools = [];

    public function __construct(
        GetUpcomingScheduleTool $getUpcomingSchedule,
        GetPendingTasksTool $getPendingTasks,
        GetFinancialSummaryTool $getFinancialSummary,
        SearchCatatanTool $searchCatatan,
        GetCoursesTool $getCourses,
        GetAcademicSummaryTool $getAcademicSummary,
        GetAnnouncementsTool $getAnnouncements,
        GetRemindersTool $getReminders,
        GetGradeScaleTool $getGradeScale,
        SearchFinancialTransactionsTool $searchFinancialTransactions,
        SearchSchedulesTool $searchSchedules,
        SearchTasksTool $searchTasks,
        GetWorkspaceOverviewTool $getWorkspaceOverview,
        ExportWorkspaceSummaryTool $exportWorkspaceSummary,
        SuggestBudgetTool $suggestBudget,
        SuggestStudyPlanTool $suggestStudyPlan,
        CheckScheduleConflictsTool $checkScheduleConflicts,
        FindFreeTimeSlotsTool $findFreeTimeSlots,
        PreviewScheduleChangeTool $previewScheduleChange,
        SuggestTaskBreakdownTool $suggestTaskBreakdown,
        PrepareWeeklyReviewTool $prepareWeeklyReview,
        DetectDuplicateRecordsTool $detectDuplicateRecords,
        SuggestFinancialAnomaliesTool $suggestFinancialAnomalies,
        SearchCoursesTool $searchCourses,
        GetCourseOverviewTool $getCourseOverview,
        CompareCourseWorkloadTool $compareCourseWorkload,
        CreateJadwalTool $createJadwal,
        CreateKeuanganTool $createKeuangan,
        CreateTugasTool $createTugas,
        CreateReminderTool $createReminder,
        CreateCourseTool $createCourse,
        CreateKegiatanTool $createKegiatan,
        RecordAcademicResultTool $recordAcademicResult,
        ManageTodolistTool $manageTodolist,
        ManageTugasTool $manageTugas,
        UpdateCatatanTool $updateCatatan,
        SetFinanceBudgetTool $setFinanceBudget,
        UpdateJadwalTool $updateJadwal,
        UpdateKegiatanTool $updateKegiatan,
        UpdateReminderTool $updateReminder,
        UpdateKeuanganTool $updateKeuangan,
        UpdateCourseTool $updateCourse,
        UpdateTugasTool $updateTugas,
        MarkAnnouncementReadTool $markAnnouncementRead,
        DuplicateScheduleTool $duplicateSchedule,
        CopyCourseToSemesterTool $copyCourseToSemester,
        ArchiveNoteTool $archiveNote,
        RestoreNoteTool $restoreNote,
        SnoozeReminderTool $snoozeReminder,
        CreateTodolistTool $createTodolist,
        CreateCatatanTool $createCatatan
    ) {
        foreach ([
            $getUpcomingSchedule,
            $getPendingTasks,
            $getFinancialSummary,
            $searchCatatan,
            $getCourses,
            $getAcademicSummary,
            $getAnnouncements,
            $getReminders,
            $getGradeScale,
            $searchFinancialTransactions,
            $searchSchedules,
            $searchTasks,
            $getWorkspaceOverview,
            $exportWorkspaceSummary,
            $suggestBudget,
            $suggestStudyPlan,
            $checkScheduleConflicts,
            $findFreeTimeSlots,
            $previewScheduleChange,
            $suggestTaskBreakdown,
            $prepareWeeklyReview,
            $detectDuplicateRecords,
            $suggestFinancialAnomalies,
            $searchCourses,
            $getCourseOverview,
            $compareCourseWorkload,
            $createJadwal,
            $createKeuangan,
            $createTugas,
            $createReminder,
            $createCourse,
            $createKegiatan,
            $recordAcademicResult,
            $manageTodolist,
            $manageTugas,
            $updateCatatan,
            $setFinanceBudget,
            $updateJadwal,
            $updateKegiatan,
            $updateReminder,
            $updateKeuangan,
            $updateCourse,
            $updateTugas,
            $markAnnouncementRead,
            $duplicateSchedule,
            $copyCourseToSemester,
            $archiveNote,
            $restoreNote,
            $snoozeReminder,
            $createTodolist,
            $createCatatan,
        ] as $tool) {
            $this->register($tool);
        }
    }

    public function register(AiToolInterface $tool): self
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    public function find(string $name): ?AiToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @param  list<string>|null  $names
     * @return list<array<string, mixed>>
     */
    public function getDeclarations(?array $names = null): array
    {
        $tools = $names === null
            ? $this->tools
            : array_intersect_key($this->tools, array_flip($names));

        return array_values(array_map(
            fn (AiToolInterface $tool) => $tool->schema(),
            $tools
        ));
    }
}
