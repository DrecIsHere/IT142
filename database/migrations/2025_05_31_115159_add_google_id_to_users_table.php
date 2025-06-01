<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('id'); // Store Google's unique ID
            $table->string('avatar')->nullable()->after('email');      // Store Google avatar URL
            $table->string('password')->nullable()->change();          // Make password nullable if only social login
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
            $table->dropColumn('avatar');
            // If you made password nullable, you might want to revert that change here
            // For simplicity, we'll assume it was already nullable or this is a new setup.
            // If it wasn't nullable before: $table->string('password')->nullable(false)->change();
        });
    }
};