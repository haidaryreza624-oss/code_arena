<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('problem_id')
                  ->constrained('problems')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('ordinal');

            $table->enum('kind', ['sample', 'hidden']);

            $table->string('input_path', 500);
            $table->string('output_path', 500);

            $table->char('input_sha256', 64);
            $table->char('output_sha256', 64);

            $table->unsignedInteger('input_size_bytes');
            $table->unsignedInteger('output_size_bytes');
            $table->timestamps();

            $table->unique(['problem_id', 'ordinal']);
            $table->index(['problem_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
