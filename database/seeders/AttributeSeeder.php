<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Attribute::firstOrCreate(
            ['name' => 'Color'],
            ['type' => AttributeType::COLOR],
        );

        Attribute::firstOrCreate(
            ['name' => 'Size'],
            ['type' => AttributeType::SELECT],
        );
    }
}
