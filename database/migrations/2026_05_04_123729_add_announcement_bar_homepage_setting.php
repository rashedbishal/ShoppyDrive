<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('homepage_settings')->updateOrInsert(
            ['section_key' => 'announcement_bar'],
            [
                'section_name' => 'Announcement Bar',
                'title' => 'Free delivery on orders over ৳999',
                'is_visible' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('homepage_settings')->where('section_key', 'announcement_bar')->delete();
    }
};
