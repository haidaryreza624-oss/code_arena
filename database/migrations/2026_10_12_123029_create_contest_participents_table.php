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
        Schema::create('contest_participents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('contests');
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('registered_at');
            $table->string('status')->default('registered'); //registered //disqualified //completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_participents');
    }
};
