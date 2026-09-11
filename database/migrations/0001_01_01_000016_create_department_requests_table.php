<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_checklist_id')->nullable()->constrained('task_checklists')->nullOnDelete();
            $table->foreignUuid('owner_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUuid('target_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUuid('target_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('response_due_at')->nullable();
            $table->foreignUuid('assigned_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['target_department_id', 'status']);
            $table->index(['owner_department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_requests');
    }
};
