<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_checklists', function (Blueprint $table) {
            $table->foreignUuid('default_assignee_id')->nullable()->after('target_department_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('checklist_assignee_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assignee_id')->constrained('users')->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['task_checklist_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_assignee_overrides');
        Schema::table('task_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_assignee_id');
        });
    }
};
