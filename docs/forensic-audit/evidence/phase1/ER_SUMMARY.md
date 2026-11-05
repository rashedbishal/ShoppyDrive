# ER summary (dev MySQL, read-only information_schema)

Generated from the local dev database at the latest migration. Row counts are local dev data and say nothing about production.

| Table | Cols | Dev rows | Unique | Foreign keys (on delete) |
|---|---|---|---|---|
| `activity_log` | 12 | 14 |  |  |
| `addresses` | 14 | 0 |  | user_id→users (CASCADE) |
| `admins` | 9 | 1 | email |  |
| `attribute_values` | 7 | 9 | attribute_id,value | attribute_id→attributes (CASCADE) |
| `attributes` | 5 | 2 |  |  |
| `blocked_ips` | 6 | 0 | ip_address | blocked_by→admins (SET NULL) |
| `blogs` | 13 | 0 | slug |  |
| `brands` | 8 | 2 | slug |  |
| `cache` | 3 | 39 |  |  |
| `cache_locks` | 3 | 0 |  |  |
| `cart_items` | 10 | 0 |  | bundle_id→product_bundles (SET NULL); cart_id→carts (CASCADE); product_id→products (CASCADE); variant_id→product_variants (CASCADE) |
| `carts` | 6 | 0 |  | coupon_id→coupons (SET NULL); user_id→users (CASCADE) |
| `categories` | 14 | 2 | slug |  |
| `contact_messages` | 9 | 0 |  |  |
| `coupon_category` | 2 | 0 |  | category_id→categories (CASCADE); coupon_id→coupons (CASCADE) |
| `coupon_product_targets` | 6 | 0 | coupon_id,product_id,variant_id | coupon_id→coupons (CASCADE); product_id→products (CASCADE); variant_id→product_variants (CASCADE) |
| `coupon_sub_category` | 2 | 0 |  | coupon_id→coupons (CASCADE); sub_category_id→sub_categories (CASCADE) |
| `coupon_uses` | 7 | 0 |  | coupon_id→coupons (RESTRICT); order_id→orders (RESTRICT); user_id→users (RESTRICT) |
| `coupons` | 16 | 0 | code |  |
| `courier_delivery_evidence` | 25 | 0 | anchor_key; gap_key; order_status_history_id | courier_submission_id→courier_submissions (SET NULL); order_id→orders (RESTRICT); order_status_history_id→order_status_histories (RESTRICT) |
| `courier_submissions` | 26 | 0 | active_order_id | claimed_by_admin_id→admins (SET NULL); order_id→orders (SET NULL); resolved_by_admin_id→admins (SET NULL) |
| `courier_verification_readiness` | 24 | 0 |  |  |
| `daily_visits` | 6 | 7 | date |  |
| `failed_jobs` | 7 | 0 | uuid |  |
| `flash_sale_items` | 9 | 1 |  | flash_sale_id→flash_sales (CASCADE); product_id→products (CASCADE); variant_id→product_variants (CASCADE) |
| `flash_sales` | 7 | 1 |  |  |
| `hero_slides` | 12 | 0 |  |  |
| `homepage_settings` | 13 | 2 | section_key |  |
| `incomplete_orders` | 10 | 0 | attempt_token | user_id→users (SET NULL) |
| `inventory_logs` | 11 | 0 |  | created_by→users (SET NULL); product_id→products (CASCADE); variant_id→product_variants (CASCADE) |
| `job_batches` | 10 | 0 |  |  |
| `jobs` | 7 | 0 |  |  |
| `landing_pages` | 8 | 0 | slug | product_id→products (CASCADE) |
| `migrations` | 3 | 139 |  |  |
| `newsletter_subscribers` | 9 | 0 | email; token |  |
| `notifications` | 8 | 0 |  |  |
| `order_deletion_audits` | 24 | 0 |  |  |
| `order_delivery_verifications` | 22 | 0 |  | courier_submission_id→courier_submissions (RESTRICT); order_id→orders (RESTRICT); readiness_id→courier_verification_readiness (RESTRICT) |
| `order_items` | 16 | 0 |  | bundle_id→product_bundles (SET NULL); flash_sale_item_id→flash_sale_items (SET NULL); order_id→orders (CASCADE); product_id→products (SET NULL); variant_id→product_variants (SET NULL) |
| `order_refunds` | 16 | 0 | return_request_id | order_id→orders (RESTRICT); return_request_id→return_requests (RESTRICT) |
| `order_sequences` | 4 | 0 | prefix,year |  |
| `order_status_histories` | 7 | 0 |  | created_by→users (SET NULL); order_id→orders (CASCADE) |
| `orders` | 47 | 0 | guest_token; order_number; pathao_consignment_id; steadfast_consignment_id | coupon_id→coupons (SET NULL); landing_page_id→landing_pages (SET NULL); user_id→users (RESTRICT) |
| `pages` | 9 | 3 | slug |  |
| `password_reset_tokens` | 3 | 0 |  |  |
| `payments` | 10 | 0 |  | order_id→orders (RESTRICT) |
| `pending_checkouts` | 12 | 0 | guest_token; token | cart_id→carts (SET NULL); landing_page_id→landing_pages (SET NULL); user_id→users (SET NULL) |
| `popular_keywords` | 6 | 0 |  |  |
| `product_attribute_values` | 4 | 0 |  | attribute_value_id→attribute_values (CASCADE); product_id→products (CASCADE) |
| `product_bundle_items` | 7 | 0 |  | product_bundle_id→product_bundles (CASCADE); product_id→products (CASCADE); variant_id→product_variants (SET NULL) |
| `product_bundles` | 11 | 0 | slug |  |
| `product_media` | 11 | 11 |  | product_id→products (CASCADE); variant_id→product_variants (CASCADE) |
| `product_questions` | 9 | 0 |  | answered_by→users (SET NULL); product_id→products (CASCADE); user_id→users (CASCADE) |
| `product_tags` | 4 | 0 |  | product_id→products (CASCADE); tag_id→tags (CASCADE) |
| `product_variants` | 14 | 0 | product_id,color_value_id,size_value_id; sku | color_value_id→attribute_values (SET NULL); product_id→products (CASCADE); size_value_id→attribute_values (SET NULL) |
| `products` | 31 | 10 | sku; slug | brand_id→brands (CASCADE); category_id→categories (CASCADE); sub_category_id→sub_categories (CASCADE) |
| `promotional_banner_product` | 3 | 0 |  | product_id→products (CASCADE); promotional_banner_id→promotional_banners (CASCADE) |
| `promotional_banners` | 11 | 0 |  |  |
| `received_sms` | 11 | 0 | provider,parsed_transaction_id | matched_order_id→orders (SET NULL) |
| `referral_point_transactions` | 10 | 0 |  | order_id→orders (SET NULL); referred_user_id→users (SET NULL); user_id→users (RESTRICT) |
| `return_requests` | 12 | 0 |  | order_id→orders (RESTRICT); user_id→users (RESTRICT) |
| `review_votes` | 5 | 0 |  | review_id→reviews (CASCADE); user_id→users (CASCADE) |
| `reviews` | 13 | 0 |  | order_id→orders (CASCADE); product_id→products (CASCADE); user_id→users (CASCADE) |
| `sessions` | 6 | 3 |  |  |
| `settings` | 5 | 1 |  |  |
| `shipping_methods` | 10 | 0 |  | zone_id→shipping_zones (CASCADE) |
| `shipping_rates` | 7 | 0 |  | method_id→shipping_methods (CASCADE) |
| `shipping_zones` | 6 | 0 |  |  |
| `sms_patterns` | 11 | 0 |  |  |
| `social_accounts` | 9 | 0 | provider,provider_id | user_id→users (CASCADE) |
| `stock_reservations` | 9 | 0 |  | product_id→products (CASCADE); user_id→users (CASCADE); variant_id→product_variants (CASCADE) |
| `sub_categories` | 13 | 3 | slug | category_id→categories (CASCADE) |
| `tags` | 5 | 0 | slug |  |
| `tax_rates` | 8 | 0 |  |  |
| `users` | 26 | 0 | email; referral_code | referral_discount_used_order_id→orders (RESTRICT); referred_by_id→users (RESTRICT) |
| `wishlists` | 6 | 0 | user_id,product_id | product_id→products (CASCADE); user_id→users (CASCADE); variant_id→product_variants (CASCADE) |
