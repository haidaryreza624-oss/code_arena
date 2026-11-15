<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->bigInteger('id')
            ->unsigned()
            ->autoIncrement()
            ->primary();
            $table->foreignId('team_id')
            ->references('id')
            ->on('teams')
            ->onDelete('cascade');
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->smallInteger('ordinal')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
