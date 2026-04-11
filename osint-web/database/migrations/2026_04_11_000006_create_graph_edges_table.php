<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graph_id')->constrained('graphs')->cascadeOnDelete();
            $table->string('cy_id', 64);
            $table->string('source_cy_id', 64);
            $table->string('target_cy_id', 64);
            $table->string('label')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['graph_id', 'cy_id']);
            $table->index(['graph_id', 'source_cy_id']);
            $table->index(['graph_id', 'target_cy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_edges');
    }
};
