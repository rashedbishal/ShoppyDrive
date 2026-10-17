<?php

use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deployments only run `php artisan migrate --force`, not db:seed (which
     * would also run the destructive demo-data DatabaseSeeder). Seeding the
     * Privacy Policy, Terms, and FAQ pages here guarantees they exist in
     * production without needing a separate seeding step in the deploy
     * pipeline. PageSeeder::run() is idempotent (updateOrCreate by slug).
     */
    public function up(): void
    {
        (new PageSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
