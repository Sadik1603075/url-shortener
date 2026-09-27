<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only event store for UrlClicked. The write model of the CQRS split:
 * every redirect that produces a click is recorded here as an immutable fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_events', function (Blueprint $table) {
            $table->id();

            $table->string('short_code', 32)->index();
            $table->text('long_url');

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();

            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_events');
    }
};
