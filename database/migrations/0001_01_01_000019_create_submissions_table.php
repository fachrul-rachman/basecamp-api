<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('work_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
