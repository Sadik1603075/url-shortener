<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            $table->string('email')->nullable()->after('code');
            $table->text('description')->nullable()->after('email');

            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('access_codes', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'description']);
        });
    }
};
