<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_urls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('short_code', 32)->unique();

            $table->text('long_url');

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('click_count')->default(0);

            $table->timestamp('expires_at')->nullable();

            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_urls');
    }
};