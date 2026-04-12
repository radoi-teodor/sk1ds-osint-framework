<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graph_nodes', function (Blueprint $table) {
            $table->boolean('flagged_for_report')->default(false)->after('position_y');
        });
    }

    public function down(): void
    {
        Schema::table('graph_nodes', function (Blueprint $table) {
            $table->dropColumn('flagged_for_report');
        });
    }
};
