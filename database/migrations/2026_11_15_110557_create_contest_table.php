<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 255);
            $table->enum('status', [
                'draft',
                'approved',
                'running',
                'ended',
            ])->default('draft');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->unsignedSmallInteger('freeze-minutes')
                ->default(0);
            $table->boolean('is_public')
                ->default(true);
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullonDelete();

            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();
             $table->tinyInteger('active_slot')
                  ->nullable()
                  ->storedAs(
                      "CASE
                          WHEN status IN ('approved', 'running')
                          THEN 1
                          ELSE NULL
                      END"
                  )
                  ->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest');
    }
};
