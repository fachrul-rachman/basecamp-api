<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_request_id')->nullable()->constrained('department_requests')->nullOnDelete();
            $table->foreignUuid('responsible_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('operational_date')->nullable();
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->dateTime('available_at');
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('failure_at')->nullable();
            $table->string('execution_status')->default('pending');
            $table->string('compliance_status')->default('pending');
            $table->unsignedInteger('required_evidence_count')->default(0);
            $table->json('rule_snapshot')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestamps();

            // Enforces H-1 generator idempotency for date-based schedule
            // types. Quota slots keep operational_date null and are
            // idempotency-checked in application code instead (multiple
            // slots share the same period).
            $table->unique(['task_checklist_id', 'operational_date']);
            $table->index(['assignee_id', 'operational_date']);
            $table->index(['responsible_department_id', 'operational_date']);
            $table->index(['deadline_at', 'execution_status']);
            $table->index(['task_id', 'task_checklist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
