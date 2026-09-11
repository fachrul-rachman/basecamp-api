<?php

use App\Models\Role;
use App\Models\TaskTemplate;
use App\Models\TemplateReferenceEvidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('admin can upload and remove reference evidence for a checklist', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $template = TaskTemplate::factory()->create();
    $checklist = makeTemplateChecklist($template);

    $upload = $this->actingAs($admin, 'sanctum')->post("/api/v1/templates/{$template->id}/reference-evidence", [
        'task_template_checklist_id' => $checklist->id,
        'file' => UploadedFile::fake()->image('reference.jpg'),
    ]);

    $upload->assertCreated();
    $evidenceId = $upload->json('data.id');
    Storage::disk('public')->assertExists(TemplateReferenceEvidence::find($evidenceId)->storage_key);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/templates/{$template->id}/reference-evidence/{$evidenceId}")
        ->assertOk();

    expect(TemplateReferenceEvidence::find($evidenceId))->toBeNull();
});

test('non-admin cannot upload reference evidence', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);
    $template = TaskTemplate::factory()->create();
    $checklist = makeTemplateChecklist($template);

    $response = $this->actingAs($manager, 'sanctum')->post("/api/v1/templates/{$template->id}/reference-evidence", [
        'task_template_checklist_id' => $checklist->id,
        'file' => UploadedFile::fake()->image('reference.jpg'),
    ]);

    $response->assertForbidden();
});

test('reference evidence must belong to a checklist under the given template', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $template = TaskTemplate::factory()->create();
    $otherTemplate = TaskTemplate::factory()->create();
    $otherChecklist = makeTemplateChecklist($otherTemplate, 'Unrelated');

    $response = $this->actingAs($admin, 'sanctum')->post("/api/v1/templates/{$template->id}/reference-evidence", [
        'task_template_checklist_id' => $otherChecklist->id,
        'file' => UploadedFile::fake()->image('reference.jpg'),
    ]);

    $response->assertStatus(422);
});
