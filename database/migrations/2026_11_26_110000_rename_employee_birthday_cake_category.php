<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('expense_categories')
            ->where('name', 'Employee Birthday Cake')
            ->update([
                'name' => 'Employee Birthday',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('expense_categories')
            ->where('name', 'Employee Birthday')
            ->update([
                'name' => 'Employee Birthday Cake',
                'updated_at' => now(),
            ]);
    }
};
