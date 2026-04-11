<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            // e.g. "C99_API_KEY" — the identifier transforms ask for
            $table->string('name')->unique();
            $table->string('label')->nullable();
            // Laravel Crypt::encryptString() output (already Base64)
            $table->text('ciphertext');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
