<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('group')->default('SBU');
                $table->string('status')->default('active'); // active, inactive
                $table->timestamps();
            });

            // Seed initial departments from User::DEPARTMENT_HIERARCHY
            $now = now();
            $records = [];
            foreach (User::DEPARTMENT_HIERARCHY as $group => $depts) {
                foreach ($depts as $deptName) {
                    $records[] = [
                        'name' => $deptName,
                        'group' => $group,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($records)) {
                DB::table('departments')->insertOrIgnore($records);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
