<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index('work_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_assignments');
    }
};
