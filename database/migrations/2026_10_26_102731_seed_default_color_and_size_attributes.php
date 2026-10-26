<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Product variants depend on a "Color" and "Size" attribute existing
     * (see ProductVariant::color_value_id / size_value_id), and the admin
     * attributes page has nothing to render without them. This used to be
     * a db:seed-only step, which meant new environments silently ended up
     * without them unless someone remembered to seed.
     */
    public function up(): void
    {
        foreach ([
            ['name' => 'Color', 'type' => 'color'],
            ['name' => 'Size', 'type' => 'select'],
        ] as $attribute) {
            $exists = DB::table('attributes')->where('name', $attribute['name'])->exists();

            if (! $exists) {
                DB::table('attributes')->insert([
                    'name' => $attribute['name'],
                    'type' => $attribute['type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')->whereIn('name', ['Color', 'Size'])->delete();
    }
};
