<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_folders', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->timestamps();
        });

        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->string('folder')->default('/')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->dropColumn('folder');
        });
        Schema::dropIfExists('file_folders');
    }
};
