<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device-breakdown read model (D5-T3): clicks counted per
 * (browser, os, device_type) bucket, projected from UrlClicked. Never on the
 * hot path — written only by the ClickProjector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_device_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('browser', 32);
            $table->string('os', 32);
            $table->string('device_type', 16);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['browser', 'os', 'device_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_device_aggregates');
    }
};
