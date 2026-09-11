<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDepartmentSlaRequest;
use App\Http\Requests\UpdateGlobalSlaRequest;
use App\Http\Requests\UpdateIsoSlaRequest;
use App\Http\Requests\UpdateManagerSlaRequest;
use App\Http\Resources\SlaSettingResource;
use App\Models\Department;
use App\Models\SlaSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SlaSettingService;
use Illuminate\Http\Request;

class SlaSettingController extends Controller
{
    public function __construct(
        private SlaSettingService $slaSettings,
        private AuditLogService $auditLog,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SlaSetting::class);

        return SlaSettingResource::collection(
            SlaSetting::query()->orderBy('scope_type')->get()
        );
    }

    public function updateGlobal(UpdateGlobalSlaRequest $request)
    {
        $setting = $this->slaSettings->set(SlaSetting::SCOPE_GLOBAL, null, $request->validated('minutes'));
        $this->auditLog->record($request->user(), 'sla_setting.global_updated', 'SlaSetting', $setting?->id, [
            'minutes' => $request->validated('minutes'),
        ]);

        return $setting
            ? new SlaSettingResource($setting)
            : response()->json(['data' => ['message' => 'Global Manager SLA removed.']]);
    }

    public function updateDepartment(UpdateDepartmentSlaRequest $request, Department $department)
    {
        $setting = $this->slaSettings->set(SlaSetting::SCOPE_DEPARTMENT, $department->id, $request->validated('minutes'));
        $this->auditLog->record($request->user(), 'sla_setting.department_updated', 'SlaSetting', $setting?->id, [
            'department_id' => $department->id,
            'minutes' => $request->validated('minutes'),
        ]);

        return $setting
            ? new SlaSettingResource($setting)
            : response()->json(['data' => ['message' => 'Department Manager SLA override removed.']]);
    }

    public function updateManager(UpdateManagerSlaRequest $request, User $manager)
    {
        $setting = $this->slaSettings->set(SlaSetting::SCOPE_MANAGER, $manager->id, $request->validated('minutes'));
        $this->auditLog->record($request->user(), 'sla_setting.manager_updated', 'SlaSetting', $setting?->id, [
            'manager_id' => $manager->id,
            'minutes' => $request->validated('minutes'),
        ]);

        return $setting
            ? new SlaSettingResource($setting)
            : response()->json(['data' => ['message' => 'Manager SLA override removed.']]);
    }

    public function updateIso(UpdateIsoSlaRequest $request)
    {
        $setting = $this->slaSettings->set(SlaSetting::SCOPE_ISO, null, $request->validated('minutes'));
        $this->auditLog->record($request->user(), 'sla_setting.iso_updated', 'SlaSetting', $setting?->id, [
            'minutes' => $request->validated('minutes'),
        ]);

        return new SlaSettingResource($setting);
    }
}
