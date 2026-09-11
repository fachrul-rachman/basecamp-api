<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pic_leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pic_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->text('reason');
            $table->string('storage_key')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['pic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pic_leave_requests');
    }
};
