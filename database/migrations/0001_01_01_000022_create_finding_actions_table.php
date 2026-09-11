<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->string('action_type'); // explanation | reopen | manager_response | iso_review | close | reopen_finding | mark_info
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('finding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_actions');
    }
};
