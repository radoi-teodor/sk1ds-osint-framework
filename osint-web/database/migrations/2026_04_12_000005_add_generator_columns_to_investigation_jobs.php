<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigation_jobs', function (Blueprint $table) {
            $table->string('generator_name')->nullable()->after('slave_id');
            $table->foreignId('generator_file_id')->nullable()->after('generator_name')
                ->constrained('uploaded_files')->nullOnDelete();
            $table->text('generator_text_input')->nullable()->after('generator_file_id');
            $table->longText('generator_output')->nullable()->after('generator_text_input');
        });
    }

    public function down(): void
    {
        Schema::table('investigation_jobs', function (Blueprint $table) {
            $table->dropForeign(['generator_file_id']);
            $table->dropColumn(['generator_name', 'generator_file_id', 'generator_text_input', 'generator_output']);
        });
    }
};
