<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-003: these five relationships were only ever enforced by
     * application code (CartManager::forUser(), CouponService::consume(),
     * InventoryService's flash-sale lookups, …), with no database backstop.
     * A 2026-11-07 production check found zero existing duplicates in any
     * of the five groups, so these go straight in — no cleanup migration
     * needed first.
     *
     * cart_items and flash_sale_items have a nullable variant_id
     * (cart_items also a nullable bundle_id) for simple, non-variant
     * products, and MySQL/MariaDB treat every NULL as distinct for
     * uniqueness purposes — so a plain composite unique index on the raw
     * columns would silently fail to catch almost every real duplicate
     * (confirmed empirically while building this migration: it missed not
     * just the no-variant case but any row with a null bundle_id, which is
     * most cart lines). A VIRTUAL generated column that collapses NULL to
     * 0 closes this cleanly: unlike STORED, it doesn't force InnoDB to
     * drop variant_id/bundle_id's existing ON DELETE CASCADE/SET NULL
     * behavior (also confirmed empirically — STORED does, VIRTUAL doesn't),
     * so deleting a variant or bundle still silently drops it from carts.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('transaction_id');
        });

        Schema::table('coupon_uses', function (Blueprint $table) {
            $table->unique(['coupon_id', 'order_id']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unique('user_id');
        });

        DB::statement('ALTER TABLE cart_items
            ADD COLUMN variant_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(variant_id, 0)) VIRTUAL,
            ADD COLUMN bundle_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(bundle_id, 0)) VIRTUAL,
            ADD UNIQUE KEY cart_items_cart_product_variant_bundle_unique (cart_id, product_id, variant_id_key, bundle_id_key)');

        DB::statement('ALTER TABLE flash_sale_items
            ADD COLUMN variant_id_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(variant_id, 0)) VIRTUAL,
            ADD UNIQUE KEY flash_sale_items_sale_product_variant_unique (flash_sale_id, product_id, variant_id_key)');
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE flash_sale_items DROP INDEX flash_sale_items_sale_product_variant_unique, DROP COLUMN variant_id_key');

        DB::statement('ALTER TABLE cart_items DROP INDEX cart_items_cart_product_variant_bundle_unique, DROP COLUMN variant_id_key, DROP COLUMN bundle_id_key');

        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('coupon_uses', function (Blueprint $table) {
            $table->dropUnique(['coupon_id', 'order_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
        });
    }
};
