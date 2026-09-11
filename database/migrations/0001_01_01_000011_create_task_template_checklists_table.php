<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_template_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->foreignUuid('target_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('schedule_type');
            $table->json('schedule_config');
            $table->unsignedInteger('evidence_min_count')->default(0);
            $table->boolean('allow_upload')->default(true);
            $table->boolean('allow_camera')->default(true);
            $table->boolean('works_on_holidays')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_checklists');
    }
};
