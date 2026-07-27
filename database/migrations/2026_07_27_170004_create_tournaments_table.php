<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained();
            $table->string('name', 180);
            $table->string('slug', 180);
            $table->string('format', 30);
            $table->string('status', 30)->default('draft')->index();
            $table->string('season_label', 80)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_public')->default(true);
            $table->json('points_config');
            $table->json('sanction_rules');
            $table->json('tiebreaker_rules');
            $table->json('format_config')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tournament_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_groups');
        Schema::dropIfExists('tournaments');
    }
};
