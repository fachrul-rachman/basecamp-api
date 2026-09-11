<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('task_template_departments', function (Blueprint $table) {
            $table->uuid('task_template_id');
            $table->uuid('department_id');
            $table->timestamps();

            $table->primary(['task_template_id', 'department_id']);
            $table->foreign('task_template_id')->references('id')->on('task_templates')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_departments');
        Schema::dropIfExists('task_templates');
    }
};
