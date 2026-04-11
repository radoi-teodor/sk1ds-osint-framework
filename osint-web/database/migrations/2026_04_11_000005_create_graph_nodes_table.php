<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graph_id')->constrained('graphs')->cascadeOnDelete();
            // Cytoscape-side id — stable across saves. Unique per graph.
            $table->string('cy_id', 64);
            $table->string('entity_type', 64)->index();
            $table->text('value');
            $table->text('label')->nullable();
            $table->json('data')->nullable();
            $table->double('position_x')->default(0);
            $table->double('position_y')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['graph_id', 'cy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_nodes');
    }
};
