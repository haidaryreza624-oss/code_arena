<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('contest_participations', function (Blueprint $table) {
           $table->id();
            $table->foreignId('contest_id')
                ->constrained('contests')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->restrictOnDelete();
            $table->string('username', 50);
            $table->string('password', 255);
            $table->unsignedSmallInteger('seat-number')
                ->nullable();
            $table->timestamp('credentials_generated_at')
                ->nullable();
            $table->timestamps();

            $table->unique(['contest_id', 'username'], 'cp_contest_username_unique');
            $table->unique(['contest_id', 'team_id'], 'cp_contest_team_id_unique');        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_participations');
    }
};
