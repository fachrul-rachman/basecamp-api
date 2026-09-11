<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope_type'); // global | department | manager | iso
            $table->uuid('scope_id')->nullable(); // null for global and iso
            $table->unsignedInteger('minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Department/Manager scopes always carry a scope_id, so this index
            // enforces one active row per (scope_type, scope_id) there. Global
            // and ISO scopes have a null scope_id, which most databases treat
            // as distinct values (not covered by this constraint); those two
            // singleton rows are kept unique by SlaSettingService::set()
            // (updateOrCreate), not by the database.
            // ponytail: application-enforced singleton for null-scope rows;
            // add a partial unique index if this needs hard DB enforcement.
            $table->unique(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_settings');
    }
};
