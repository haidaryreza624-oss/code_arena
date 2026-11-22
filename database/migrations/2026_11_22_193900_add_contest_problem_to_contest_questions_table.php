<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contest_questions', function (Blueprint $table) {
            $table->foreignId('contest_problem_id')
                ->nullable()
                ->after('participation_id')
                ->constrained('contest_problems')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contest_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contest_problem_id');
        });
    }
};
