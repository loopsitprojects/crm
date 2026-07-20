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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'date_of_delivery')) {
                $table->date('date_of_delivery')->nullable()->after('date');
            }
            if (!Schema::hasColumn('invoices', 'place_of_supply')) {
                $table->string('place_of_supply')->nullable()->after('date_of_delivery');
            }
        });

        Schema::table('temp_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('temp_invoices', 'date_of_delivery')) {
                $table->date('date_of_delivery')->nullable()->after('date');
            }
            if (!Schema::hasColumn('temp_invoices', 'place_of_supply')) {
                $table->string('place_of_supply')->nullable()->after('date_of_delivery');
            }
        });

        // Copy existing delivery fields from quotations to invoices and temp_invoices where NULL
        DB::table('invoices')
            ->join('quotations', 'invoices.quotation_id', '=', 'quotations.id')
            ->whereNull('invoices.date_of_delivery')
            ->update([
                'invoices.date_of_delivery' => DB::raw('quotations.date_of_delivery'),
                'invoices.place_of_supply' => DB::raw('quotations.place_of_supply'),
            ]);

        DB::table('temp_invoices')
            ->join('quotations', 'temp_invoices.quotation_id', '=', 'quotations.id')
            ->whereNull('temp_invoices.date_of_delivery')
            ->update([
                'temp_invoices.date_of_delivery' => DB::raw('quotations.date_of_delivery'),
                'temp_invoices.place_of_supply' => DB::raw('quotations.place_of_supply'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'place_of_supply')) {
                $table->dropColumn('place_of_supply');
            }
            if (Schema::hasColumn('invoices', 'date_of_delivery')) {
                $table->dropColumn('date_of_delivery');
            }
        });

        Schema::table('temp_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('temp_invoices', 'place_of_supply')) {
                $table->dropColumn('place_of_supply');
            }
            if (Schema::hasColumn('temp_invoices', 'date_of_delivery')) {
                $table->dropColumn('date_of_delivery');
            }
        });
    }
};
