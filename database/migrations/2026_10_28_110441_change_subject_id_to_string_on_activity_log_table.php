<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `Setting`'s primary key is the string `key` column (non-incrementing),
     * but `subject_id` was created as `unsignedBigInteger` by Spatie's
     * default `nullableMorphs()`. Logging any Setting save inserts its
     * string key as `subject_id`, which MySQL strict mode rejects as an
     * invalid integer — this broke every Setting update, not just one.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });
    }
};
