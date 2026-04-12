<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slaves', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type', 16)->default('ssh');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username')->nullable();
            $table->string('auth_method', 16)->nullable();
            $table->text('encrypted_credential')->nullable();
            $table->json('fingerprint')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slaves');
    }
};
