<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_entries', function (Blueprint $table) {
            $table->decimal('remaining_volume', 15, 4)->nullable()->after('progress_volume');
        });
    }

    public function down(): void
    {
        Schema::table('progress_entries', function (Blueprint $table) {
            $table->dropColumn('remaining_volume');
        });
    }
};
