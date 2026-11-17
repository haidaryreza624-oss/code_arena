<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contest_id')
                ->constrained('contests')
                ->cascadeOnDelete();

            $table->foreignId('participation_id')
                ->constrained('contest_participations')
                ->cascadeOnDelete();

            $table->foreignId('contest_problem_id')
                ->constrained('contest_problems')
                ->cascadeOnDelete();

            $table->string('language', 32);
            $table->string('source_path', 500);

            $table->enum('status', [
                'queued',
                'judging',
                'done',
                'error'
            ])->default('queued');
            $table->string('verdict', 16)->nullable();
            $table->unsignedInteger('max_time_ms')->nullable();

            $table->unsignedInteger('max_memory_kb')->nullable();

            $table->decimal('score', 8, 2)->nullable();

            $table->string('judge_job_id', 100)->nullable()->unique();

            $table->boolean('is_sample_run')->default(false);

            $table->dateTime('submitted_at');

            $table->dateTime('judged_at')->nullable();

            $table->unsignedTinyInteger('rejudge_count')->default(0);

            $table->dateTime('last_rejudged_at')->nullable();

            $table->foreignId('last_rejudged_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['contest_id', 'participation_id']);

            $table->index(['contest_id', 'contest_problem_id']);

            $table->index(['contest_id', 'status']);

            $table->index(['contest_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
