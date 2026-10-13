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
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->dateTime('start_time');
            $table->datetime('end_time');
            $table->datetime('registration_start');
            $table->datetime('registration_end');
            $table->string('status')->default('draft'); //draft //schudled //running //finished
            $table->string('visibility')->default('prublic'); //private //public
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contests');
    }
};
