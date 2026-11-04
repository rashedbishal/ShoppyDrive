<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flash sales now sell from a product's main stock with no quantity of
     * their own, so saved limits would keep capping sales with no way left to
     * see or change them. sold_count is kept.
     */
    public function up(): void
    {
        DB::table('flash_sale_items')->update(['stock_limit' => null]);
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to restore: the cleared limits aren't kept anywhere.
     */
    public function down(): void
    {
        //
    }
};
