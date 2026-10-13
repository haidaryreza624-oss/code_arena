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
        Schema::create('submission_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained('test_cases');
            $table->foreignId('submission_id')->constrained('submissions');
            $table->string('verdict');
            $table->integer('excution_time_ms');
            $table->integer('excution_memory_mb');
            $table->integer('score');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_test_results');
    }
};
