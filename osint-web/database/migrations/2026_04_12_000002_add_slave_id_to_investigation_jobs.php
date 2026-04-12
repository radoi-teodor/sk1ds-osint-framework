<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigation_jobs', function (Blueprint $table) {
            $table->foreignId('slave_id')->nullable()->after('template_id')
                ->constrained('slaves')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investigation_jobs', function (Blueprint $table) {
            $table->dropForeign(['slave_id']);
            $table->dropColumn('slave_id');
        });
    }
};
