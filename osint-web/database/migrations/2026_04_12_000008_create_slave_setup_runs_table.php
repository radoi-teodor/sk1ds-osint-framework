<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slave_setup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slave_id')->constrained('slaves')->cascadeOnDelete();
            $table->foreignId('script_id')->constrained('slave_setup_scripts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('queued');
            $table->longText('stdout')->nullable();
            $table->longText('stderr')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slave_setup_runs');
    }
};
