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
        Schema::create('contest_pivots', function (Blueprint $table) {
            $table->foreignId('contest_id')->constrained('contests');
            $table->foreignId('problem_id')->constrained('problems');
            $table->string('problem_label'); //A //B //C
            $table->integer('points');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_pivots');
    }
};
