<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_code_counters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        // Seed the single counter row so lockForUpdate() always has a row to
        // serialize on (otherwise the first concurrent next() calls race on
        // insert and one hits the unique(name) constraint).
        DB::table('short_code_counters')->insert([
            'name' => 'short_url',
            'value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('short_code_counters');
    }
};
