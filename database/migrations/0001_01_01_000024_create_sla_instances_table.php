<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sla_type'); // manager | iso
            $table->unsignedInteger('effective_minutes');
            $table->dateTime('started_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('breached_at')->nullable();
            $table->string('status')->default('running'); // running | completed | breached
            $table->timestamps();

            $table->index(['finding_id', 'sla_type']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_instances');
    }
};
