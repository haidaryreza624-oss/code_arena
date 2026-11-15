<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('submission_results', function (Blueprint $table) {
            $table->bigInteger('id')
                    ->unsigned()
                    ->primary();
            $table->foreignId('submission_id')
                  ->constrained('submissions')
                  ->cascadeOnDelete();

            $table->foreignId('test_case_id')
                  ->constrained('test_cases')
                  ->restrictOnDelete();

            $table->string('verdict', 16);

            $table->unsignedInteger('time_ms')->nullable();

            $table->unsignedInteger('memory_kb')->nullable();

            $table->text('message')->nullable();

            $table->timestamps();

            $table->unique(['submission_id', 'test_case_id']);

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_results');
    }
};
