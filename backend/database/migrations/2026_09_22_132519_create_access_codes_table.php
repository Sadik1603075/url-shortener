<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('code', 100)->unique();

            $table->boolean('is_active')->default(true);

            $table->timestamp('expires_at')->nullable();

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_codes');
    }
};