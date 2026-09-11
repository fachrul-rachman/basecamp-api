<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->foreignUuid('owner_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('overall_deadline_at')->nullable();
            $table->string('status')->default('active');
            $table->json('schedule_snapshot')->nullable();
            $table->timestamps();

            $table->index('owner_department_id');
            $table->index('status');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
