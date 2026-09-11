<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->foreignUuid('subject_id')->constrained('users')->restrictOnDelete();
            $table->date('period_month');
            $table->decimal('score', 5, 2);
            $table->integer('deduction_count');
            $table->dateTime('locked_at');
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'period_month']);
            $table->index(['subject_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_scores');
    }
};
