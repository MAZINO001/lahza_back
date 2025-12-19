<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, drop the foreign key constraint on team_users
        Schema::table('team_users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        // Add department column to team_users
        Schema::table('team_users', function (Blueprint $table) {
            $table->string('department')->after('team_id');
        });


        // Drop the teams table
        Schema::dropIfExists('teams');

        // Remove the team_id column as it's no longer needed
        Schema::table('team_users', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate teams table
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("department");
            $table->text("description")->nullable();
            $table->timestamps();
        });

        // Add team_id column back to team_users
        Schema::table('team_users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id');
        });

        // Note: We can't automatically restore the team relationships in the down method
        // as we don't have the original team data. This would need to be handled manually.

        // Remove department column
        Schema::table('team_users', function (Blueprint $table) {
            $table->dropColumn('department');
        });

        // Re-add foreign key constraint
        Schema::table('team_users', function (Blueprint $table) {
            $table->foreign('team_id')
                ->references('id')
                ->on('teams')
                ->onDelete('cascade');
        });
    }
};
