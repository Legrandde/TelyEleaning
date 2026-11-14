<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnes_newsletter', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('demande_le')->nullable();
            $table->timestamp('consenti_le')->nullable();
            $table->timestamp('desabonne_le')->nullable();
            $table->timestamps();
        });

        Schema::create('diffusions_newsletter', function (Blueprint $table) {
            $table->string('cle')->primary();
            $table->boolean('annoncee')->default(true);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diffusions_newsletter');
        Schema::dropIfExists('abonnes_newsletter');
    }
};
