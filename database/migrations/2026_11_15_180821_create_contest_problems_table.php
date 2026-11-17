<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')
                ->constrained('contests')
                ->cascadeOnDelete();
            $table->foreignId('problem_id')
                ->constrained('problems')
                ->restrictOnDelete();
            $table->string('label', 3);
            $table->unsignedSmallInteger('points')
                ->default(0);
            $table->unsignedSmallInteger('sort_order')
                ->default(0);
            $table->timestamps();
            $table->unique(['contest_id', 'problem_id']);
            $table->unique(['contest_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_problems');
    }
};
