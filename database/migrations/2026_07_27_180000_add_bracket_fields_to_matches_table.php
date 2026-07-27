<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedInteger('bracket_slot')->nullable()->after('stage');
            $table->string('bracket_code', 40)->nullable()->after('bracket_slot');
            $table->foreignId('home_from_match_id')
                ->nullable()
                ->after('away_team_id')
                ->constrained('matches')
                ->nullOnDelete();
            $table->foreignId('away_from_match_id')
                ->nullable()
                ->after('home_from_match_id')
                ->constrained('matches')
                ->nullOnDelete();
            $table->string('home_from_result', 10)->nullable()->after('away_from_match_id'); // winner|loser
            $table->string('away_from_result', 10)->nullable()->after('home_from_result');
            $table->index(['tournament_id', 'bracket_slot']);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_from_match_id');
            $table->dropConstrainedForeignId('away_from_match_id');
            $table->dropIndex(['tournament_id', 'bracket_slot']);
            $table->dropColumn([
                'bracket_slot',
                'bracket_code',
                'home_from_result',
                'away_from_result',
            ]);
        });
    }
};
