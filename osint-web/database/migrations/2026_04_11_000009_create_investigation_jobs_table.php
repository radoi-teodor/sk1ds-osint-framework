<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graph_id')->constrained('graphs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // 'transform' | 'template'
            $table->string('kind', 16)->index();
            // for transform: engine transform name
            $table->string('transform_name')->nullable();
            // for template: id of the template graph
            $table->foreignId('template_id')->nullable()->constrained('graphs')->nullOnDelete();
            // which investigation-side nodes triggered this job
            $table->json('source_cy_ids')->nullable();
            // queued | running | completed | failed | cancelled
            $table->string('status', 16)->default('queued')->index();
            $table->unsignedInteger('progress_done')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            // accumulated outputs (arrays of node/edge DTOs)
            $table->json('created_nodes')->nullable();
            $table->json('created_edges')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transformation_runs', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable()->after('id')
                ->constrained('investigation_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transformation_runs', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->dropColumn('job_id');
        });
        Schema::dropIfExists('investigation_jobs');
    }
};
