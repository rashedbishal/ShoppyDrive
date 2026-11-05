<?php

use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BIZ-032/BIZ-034: pages have no admin editor, so this is how the corrected
 * text reaches production. Rewrites the Terms, FAQ and Privacy Policy to
 * match how the shop actually works, and creates the Return, Refund and
 * Shipping policy pages. Content comes from database/seeders/pages/*.html,
 * shared with PageSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (PageSeeder::pages() as $page) {
            $exists = DB::table('pages')->where('slug', $page['slug'])->exists();

            if ($exists) {
                DB::table('pages')->where('slug', $page['slug'])->update([...$page, 'updated_at' => $now]);
            } else {
                DB::table('pages')->insert([...$page, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /**
     * Removes only the three pages this migration created. The previous
     * Terms/FAQ/Privacy text is not restored: it misdescribed the shop.
     */
    public function down(): void
    {
        DB::table('pages')->whereIn('slug', ['return-policy', 'refund-policy', 'shipping-policy'])->delete();
    }
};
