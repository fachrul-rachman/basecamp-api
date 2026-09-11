<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type'); // automatic | iso_manual | sla
            $table->string('finding_type'); // late | failed | manager_sla_breach | ...
            $table->foreignUuid('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignUuid('task_checklist_id')->nullable()->constrained('task_checklists')->nullOnDelete();
            $table->foreignUuid('work_item_id')->nullable()->constrained('work_items')->nullOnDelete();
            $table->foreignUuid('source_evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->foreignUuid('target_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUuid('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status');
            $table->string('resolution_type')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->boolean('is_self_handled')->default(false);
            $table->dateTime('opened_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['target_department_id', 'status']);
            $table->index(['target_user_id', 'status']);
            $table->index(['finding_type', 'created_at']);
            $table->index(['source_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
