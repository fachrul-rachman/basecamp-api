<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_calendars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('working_calendar_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('working_calendar_id')->constrained()->cascadeOnDelete();
            // 0 = Sunday .. 6 = Saturday (PHP Carbon::dayOfWeek convention).
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_working_day')->default(true);
            $table->timestamps();

            $table->unique(['working_calendar_id', 'weekday']);
        });

        Schema::create('calendar_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('working_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_working')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['working_calendar_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_exceptions');
        Schema::dropIfExists('working_calendar_hours');
        Schema::dropIfExists('working_calendars');
    }
};
