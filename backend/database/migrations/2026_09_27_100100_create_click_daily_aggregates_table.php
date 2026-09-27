<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read model (CQRS query side): pre-aggregated daily click totals, projected
 * from click_events. Powers the dashboard "clicks over time" chart without
 * scanning the event log on every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_daily_aggregates', function (Blueprint $table) {
            $table->id();

            $table->date('date')->unique();
            $table->unsignedBigInteger('clicks')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_daily_aggregates');
    }
};
