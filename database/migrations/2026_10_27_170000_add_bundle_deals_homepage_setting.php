<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('homepage_settings')->updateOrInsert(
            ['section_key' => 'bundle_deals'],
            [
                'section_name' => 'Bundle Deals',
                'eyebrow' => 'Save more',
                'title' => 'Bundle deals',
                'description' => 'Hand-picked product combos at a special price or with free delivery.',
                'action_label' => 'View all bundles',
                'action_href' => '/bundle-deals',
                'is_visible' => true,
                'sort_order' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('homepage_settings')->where('section_key', 'bundle_deals')->delete();
    }
};
