<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optional_audit_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->string('action', 100);

            $table->string('subject_type', 100)->nullable();

            $table->unsignedBigInteger('subject_id')->nullable();

            $table->json('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optional_audit_log');
    }
};
