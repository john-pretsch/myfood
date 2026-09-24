<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tags are now managed by admins, so start from an empty list.
     */
    public function up(): void
    {
        DB::table('recipe_tag')->delete();
        DB::table('tags')->delete();
    }

    public function down(): void
    {
        //
    }
};
