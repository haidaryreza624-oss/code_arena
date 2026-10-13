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

        //
        Schema::create('problems',function(Blueprint $table){
            $table->id('id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->string('input_format');
            $table->string('output_format');
            $table->string('constraints')->nullable();
            $table->string('difficulty')->default('easy'); //easy //medium //hard
            $table->integer('time_limit_ms')->default(10);
            $table->integer('memory_limit_mb')->default(128);
            $table->string('staus')->default('draft'); //draft //confirmed
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('problems');
    }
};
