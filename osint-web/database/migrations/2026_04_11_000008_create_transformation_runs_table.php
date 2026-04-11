<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transformation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('graph_id')->nullable()->constrained('graphs')->nullOnDelete();
            $table->string('source_cy_id', 64)->nullable();
            $table->string('transform_name')->index();
            $table->string('input_type', 64)->nullable();
            $table->text('input_value')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transformation_runs');
    }
};
