<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_reopens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Enforced unique: at most one reopen ever per work item (see
            // docs/02-BUSINESS-RULES.md §6). Service layer still validates
            // this explicitly for a clean error message.
            $table->foreignUuid('work_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('deadline_at');
            $table->text('reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('result')->nullable(); // completed | missed

            $table->index('finding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_reopens');
    }
};
