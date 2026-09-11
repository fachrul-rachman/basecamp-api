<?php

use App\Http\Controllers\AuditFindingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarConflictController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentRequestController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PicLeaveRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SlaSettingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkingCalendarController;
use App\Http\Controllers\WorkItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::apiResource('users', UserController::class);
        Route::get('users/{user}/work-calendar', [UserController::class, 'workCalendar']);
        Route::put('users/{user}/work-calendar', [UserController::class, 'updateWorkCalendar']);
        Route::put('users/{user}/sla', [SlaSettingController::class, 'updateManager']);

        Route::apiResource('departments', DepartmentController::class);
        Route::get('departments/{department}/members', [DepartmentController::class, 'members']);
        Route::put('departments/{department}/members', [DepartmentController::class, 'updateMembers']);
        Route::put('departments/{department}/sla', [SlaSettingController::class, 'updateDepartment']);

        Route::apiResource('working-calendars', WorkingCalendarController::class)
            ->parameters(['working-calendars' => 'workingCalendar']);
        Route::post('working-calendars/{workingCalendar}/exceptions', [WorkingCalendarController::class, 'storeException']);
        Route::delete('working-calendars/{workingCalendar}/exceptions/{exception}', [WorkingCalendarController::class, 'destroyException']);

        Route::apiResource('holidays', HolidayController::class)->except(['show']);

        Route::get('sla-settings', [SlaSettingController::class, 'index']);
        Route::put('sla-settings/global', [SlaSettingController::class, 'updateGlobal']);
        Route::put('sla-settings/iso', [SlaSettingController::class, 'updateIso']);

        Route::apiResource('templates', TaskTemplateController::class);
        Route::post('templates/{template}/reference-evidence', [TaskTemplateController::class, 'storeReferenceEvidence']);
        Route::delete('templates/{template}/reference-evidence/{evidence}', [TaskTemplateController::class, 'destroyReferenceEvidence']);

        Route::get('calendar-conflicts', [CalendarConflictController::class, 'index']);

        Route::apiResource('tasks', TaskController::class)->except(['destroy']);
        Route::post('tasks/{task}/reschedule', [TaskController::class, 'reschedule']);
        Route::post('tasks/{task}/cancel', [TaskController::class, 'cancel']);
        Route::get('tasks/{task}/checklists', [TaskController::class, 'checklists']);
        Route::post('tasks/{task}/checklists', [TaskController::class, 'storeChecklist']);
        Route::patch('tasks/{task}/checklists/{checklist}', [TaskController::class, 'updateChecklist']);
        Route::delete('tasks/{task}/checklists/{checklist}', [TaskController::class, 'destroyChecklist']);

        Route::get('department-requests', [DepartmentRequestController::class, 'index']);
        Route::get('department-requests/{departmentRequest}', [DepartmentRequestController::class, 'show']);
        Route::post('department-requests/{departmentRequest}/assign', [DepartmentRequestController::class, 'assign']);
        Route::post('department-requests/{departmentRequest}/reject', [DepartmentRequestController::class, 'reject']);
        Route::post('department-requests/{departmentRequest}/reassign', [DepartmentRequestController::class, 'reassign']);

        Route::get('work-items/today', [WorkItemController::class, 'today']);
        Route::get('work-items', [WorkItemController::class, 'index']);
        Route::get('work-items/{workItem}', [WorkItemController::class, 'show']);
        Route::post('work-items/{workItem}/submission', [WorkItemController::class, 'storeSubmission']);
        Route::patch('work-items/{workItem}/submission', [WorkItemController::class, 'storeSubmission']);
        Route::post('work-items/{workItem}/evidence', [WorkItemController::class, 'storeEvidence']);
        Route::delete('work-items/{workItem}/evidence/{evidence}', [WorkItemController::class, 'destroyEvidence']);
        Route::post('work-items/{workItem}/evidence/{evidence}/review', [WorkItemController::class, 'reviewEvidence']);
        Route::post('work-items/{workItem}/reassign', [WorkItemController::class, 'reassign']);

        Route::get('pic-leave-requests', [PicLeaveRequestController::class, 'index']);
        Route::post('pic-leave-requests', [PicLeaveRequestController::class, 'store']);
        Route::get('pic-leave-requests/{picLeaveRequest}', [PicLeaveRequestController::class, 'show']);
        Route::post('pic-leave-requests/{picLeaveRequest}/approve', [PicLeaveRequestController::class, 'approve']);
        Route::post('pic-leave-requests/{picLeaveRequest}/reject', [PicLeaveRequestController::class, 'reject']);

        Route::get('findings', [FindingController::class, 'index']);
        Route::get('findings/{finding}', [FindingController::class, 'show']);
        Route::post('findings/{finding}/explanation', [FindingController::class, 'explanation']);
        Route::post('findings/{finding}/reopen-work', [FindingController::class, 'reopenWork']);
        Route::post('findings/{finding}/iso-review', [FindingController::class, 'isoReview']);
        Route::get('findings/{finding}/timeline', [FindingController::class, 'timeline']);
        Route::get('findings/{finding}/sla', [FindingController::class, 'sla']);

        Route::get('audit-findings', [AuditFindingController::class, 'index']);
        Route::post('audit-findings', [AuditFindingController::class, 'store']);
        Route::get('audit-findings/{finding}', [AuditFindingController::class, 'show']);
        Route::post('audit-findings/{finding}/manager-response', [AuditFindingController::class, 'managerResponse']);
        Route::post('audit-findings/{finding}/review', [AuditFindingController::class, 'review']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('reports/company', [ReportController::class, 'company']);
        Route::get('reports/departments', [ReportController::class, 'departments']);
        Route::get('reports/departments/{department}', [ReportController::class, 'department']);
        Route::get('reports/managers/{manager}', [ReportController::class, 'manager']);
        Route::get('reports/managers/{manager}/score', [ReportController::class, 'managerScore']);
        Route::get('reports/pics/{pic}', [ReportController::class, 'pic']);
        Route::get('reports/pics/{pic}/score', [ReportController::class, 'picScore']);
        Route::get('reports/patterns', [ReportController::class, 'patterns']);
        Route::get('reports/iso', [ReportController::class, 'iso']);
    });
});
