<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->unsignedSmallInteger('jersey_number')->nullable();
            $table->string('document_id', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('status', 30)->default('enabled')->index();
            $table->unsignedInteger('yellow_cards_count')->default(0);
            $table->unsignedInteger('red_cards_count')->default(0);
            $table->unsignedInteger('suspension_matches_left')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
