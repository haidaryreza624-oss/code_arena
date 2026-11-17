<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contest_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contest_id')
                  ->constrained('contests')
                  ->cascadeOnDelete();

            $table->foreignId('participation_id')
                  ->constrained('contest_participations')
                  ->cascadeOnDelete();

            $table->text('body');
            $table->text('answer')->nullable();

            $table->foreignId('answered_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->dateTime('answered_at')->nullable();

            $table->enum('visibility', ['public', 'private'])
                  ->default('private');

            $table->timestamps();

            $table->index(['contest_id', 'answered_at']);

            $table->index(['contest_id', 'participation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_questions');
    }
};
