import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type Id = number;

export type UserRole = 'customer' | 'admin';
export type OrderStatus =
    | 'pending'
    | 'processing'
    | 'shipped'
    | 'delivered'
    | 'cancelled'
    | 'refunded';
export type PaymentStatus = 'unpaid' | 'paid' | 'refunded';
export type PaymentMethod = 'cod' | 'zinipay' | 'mobile_banking';
export type InventoryType =
    | 'in'
    | 'out'
    | 'adjustment'
    | 'reservation'
    | 'release';
export type CouponType = 'percent' | 'fixed';
export type AttributeType = 'select' | 'color' | 'text';
export type ReturnStatus = 'pending' | 'approved' | 'rejected' | 'refunded';
export type StockStatus = 'in_stock' | 'low_stock' | 'out_of_stock';
export type DrawerSide = 'left' | 'right';
export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';
export type BadgeVariant =
    | 'default'
    | 'success'
    | 'warning'
    | 'error'
    | 'info'
    | 'amber';

export interface Timestamped {
    created_at: string;
    updated_at: string;
}

export interface SoftDeletable {
    deleted_at?: string | null;
}

export interface FlashToast {
    id?: string;
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    title?: string;
    duration?: number;
    /** When set, the toast's trailing "N product(s)" becomes a clickable link that opens a modal listing these. */
    products?: { id: Id; name: string; slug: string }[];
}

export interface User extends Timestamped {
    id: Id;
    name: string;
    email: string;
    phone?: string | null;
    avatar?: string | null;
    role: UserRole;
    email_verified_at: string | null;
    /** False for a customer who has only ever signed in with Google/Facebook. */
    has_password?: boolean;
    referral_code?: string;
    points_balance?: number;
}

export interface Auth {
    user: User | null;
    admin: User | null;
}

export interface AppSettings {
    site_name: string;
    logo?: string | null;
    loading_logo?: string | null;
    loading_spin_seconds?: number;
    loading_ring_size?: number;
    loading_logo_size?: number;
    loading_ring_thickness?: number;
    favicon?: string | null;
    currency_symbol: string;
    contact_email: string;
    contact_phone?: string | null;
    contact_address?: string | null;
    legal_business_name?: string | null;
    trade_licence_number?: string | null;
    support_hours?: string;
    social_links: {
        facebook?: string;
        instagram?: string;
        twitter?: string;
        youtube?: string;
        tiktok?: string;
    };
    google_analytics_id?: string | null;
    whatsapp_number?: string | null;
    messenger_link?: string | null;
    ai_assistant_enabled?: boolean;
    free_delivery_threshold?: number | null;
    shipping_rate_inside_dhaka?: number;
    shipping_rate_outside_dhaka?: number;
    google_login_enabled?: boolean;
    facebook_login_enabled?: boolean;
    referral_enabled?: boolean;
    referral_discount_type?: 'percent' | 'fixed';
    referral_discount_value?: number;
    /** Whether customers may attach photos to a review, and the most per review. */
    review_images_enabled?: boolean;
    review_images_max?: number;
}

export interface Category extends Timestamped, SoftDeletable {
    id: Id;
    name: string;
    slug: string;
    image?: string | null;
    icon?: string | null;
    color?: string | null;
    description?: string | null;
    meta_title?: string | null;
    meta_description?: string | null;
    is_active: boolean;
    sort_order: number;
    sub_categories?: SubCategory[];
    products_count?: number;
}

export interface SubCategory extends Timestamped, SoftDeletable {
    id: Id;
    category_id: Id;
    name: string;
    slug: string;
    image?: string | null;
    description?: string | null;
    meta_title?: string | null;
    meta_description?: string | null;
    is_active: boolean;
    sort_order: number;
    products_count?: number;
}

export interface Brand extends Timestamped {
    id: Id;
    name: string;
    slug: string;
    logo?: string | null;
    description?: string | null;
    is_active: boolean;
    products_count?: number;
}

export interface AttributeValue extends Timestamped {
    id: Id;
    attribute_id: Id;
    value: string;
    color_hex?: string | null;
    sort_order: number;
}

export interface Attribute extends Timestamped {
    id: Id;
    name: string;
    type: AttributeType;
    values: AttributeValue[];
}

export interface ProductMedia extends Timestamped {
    id: Id;
    product_id: Id;
    variant_id?: Id | null;
    /** Set when the image was chosen for an attribute value (e.g. Red): it belongs to every variant with that value. */
    attribute_value_id?: Id | null;
    path: string;
    type: 'image' | 'video';
    alt?: string | null;
    sort_order: number;
    is_primary: boolean;
    is_hover?: boolean;
}

export interface ProductVariant extends Timestamped {
    id: Id;
    product_id: Id;
    sku?: string | null;
    price?: number | null;
    compare_price?: number | null;
    cost_price?: number | null;
    stock_quantity: number;
    low_stock_threshold?: number | null;
    attributes: Record<string, string>;
    is_default: boolean;
    color_value_id?: Id | null;
    /** One value per variant attribute, in the product's attribute order (e.g. [Red, 12pcs]). */
    attribute_value_ids?: Id[] | null;
    size_value_id?: Id | null;
    formatted_price?: string;
    image?: string | null;
    in_flash_sale?: boolean;
    flash_sale_price?: number | null;
    flash_sale_ends_at?: string | null;
}

export interface Product extends Timestamped, SoftDeletable {
    id: Id;
    name: string;
    slug: string;
    sku?: string | null;
    short_description?: string | null;
    description?: string | null;
    specifications?: { key: string; value: string }[] | null;
    category: Category;
    brand?: Brand | null;
    price: number;
    compare_price?: number | null;
    cost_price?: number | null;
    formatted_price: string;
    formatted_compare_price?: string | null;
    discount_percentage?: number | null;
    stock_quantity: number;
    available_stock?: number;
    low_stock_threshold: number;
    is_active: boolean;
    is_featured: boolean;
    is_digital: boolean;
    free_delivery: boolean;
    free_delivery_min_qty?: number | null;
    cry_baby_enabled?: boolean;
    weight: number;
    meta_title?: string | null;
    meta_description?: string | null;
    meta_keywords?: string | null;
    published_at?: string | null;
    primary_image_url?: string | null;
    media: ProductMedia[];
    images: ProductMedia[];
    videos: ProductMedia[];
    variants: ProductVariant[];
    attributes: Attribute[];
    tags: string[];
    average_rating?: number | null;
    reviews_count?: number | null;
    is_wishlisted?: boolean;
    in_flash_sale?: boolean;
    flash_sale_price?: number | null;
    flash_sale_ends_at?: string | null;
}

export interface CartItemBundle {
    id: Id;
    name: string;
    image: string | null;
    discount_type: 'fixed_price' | 'free_delivery';
}

/** A component of an active bundle's recipe — just enough to detect a match and prorate pricing, not full catalog data. */
export interface BundleRecipeItem {
    product_id: Id;
    variant_id: Id | null;
    quantity: number;
    base_price: number;
}

/** Mirrors `BundleService::activeRecipes()` — shared globally so the cart store can recognize when separately-added plain items happen to satisfy a bundle. */
export interface BundleRecipe {
    id: Id;
    name: string;
    discount_type: 'fixed_price' | 'free_delivery';
    bundle_price: number | null;
    items: BundleRecipeItem[];
}

export interface CartItem {
    id: Id;
    item_key: string;
    product: Product;
    variant?: ProductVariant | null;
    bundle_id?: Id | null;
    bundle?: CartItemBundle | null;
    bundle_unit_quantity?: number | null;
    quantity: number;
    price: number;
    formatted_price: string;
    subtotal: number;
    formatted_subtotal: string;
}

export interface Coupon {
    id: Id;
    code: string;
    type: CouponType;
    value: number;
    discount_amount?: number;
    /** The cart lines a selected-items coupon covers; absent means every line. */
    eligible_keys?: string[] | null;
}

export interface Cart {
    items: CartItem[];
    subtotal: number;
    discount: number;
    shipping_estimate: number;
    tax: number;
    total: number;
    coupon?: Coupon | null;
    item_count: number;
    formatted_subtotal: string;
    formatted_total: string;
    /** CartManager::version(): identifies the server cart's contents, for cross-device sync. */
    version?: string;
}

export interface Address {
    id: Id;
    label?: string | null;
    name: string;
    email?: string | null;
    phone: string;
    address_line1: string;
    address_line2?: string | null;
    city: string;
    state: string;
    postal_code?: string | null;
    country: string;
    is_default: boolean;
}

export interface StockReservation extends Timestamped {
    id: Id;
    product_id: Id;
    variant_id?: Id | null;
    quantity: number;
    session_id?: string | null;
    user_id?: Id | null;
    expires_at: string;
}

export interface OrderItem extends Timestamped {
    id: Id;
    order_id: Id;
    product_id?: Id | null;
    variant_id?: Id | null;
    product_name: string;
    sku?: string | null;
    price: number;
    quantity: number;
    subtotal: number;
    formatted_price: string;
    formatted_subtotal: string;
    product_snapshot: Record<string, unknown>;
    product?: Product | null;
    variant?: ProductVariant | null;
}

export interface OrderStatusHistory extends Timestamped {
    id: Id;
    order_id: Id;
    status: OrderStatus;
    notes?: string | null;
    created_by?: Pick<User, 'id' | 'name'> | null;
}

export interface Order extends Timestamped, SoftDeletable {
    id: Id;
    order_number: string;
    user_id?: Id | null;
    status: OrderStatus;
    payment_status: PaymentStatus;
    payment_method: PaymentMethod;
    subtotal: number;
    shipping_cost: number;
    discount: number;
    tax: number;
    total: number;
    coupon_id?: Id | null;
    coupon_code?: string | null;
    shipping_address: Address;
    billing_address: Address;
    notes?: string | null;
    tracking_number?: string | null;
    cancellation_reason?: string | null;
    shipped_at?: string | null;
    delivered_at?: string | null;
    items: OrderItem[];
    status_histories: OrderStatusHistory[];
    formatted_total: string;
    latest_return_request?: ReturnRequest | null;
}

export interface Payment extends Timestamped {
    id: Id;
    order_id: Id;
    method: string;
    amount: number;
    status: PaymentStatus | 'pending' | 'failed';
}

export interface ReturnRequest extends Timestamped {
    id: Id;
    order_id: Id;
    user_id: Id;
    reason: string;
    status: ReturnStatus;
    items: Array<{
        order_item_id: Id;
        product_id: Id;
        quantity: number;
        reason?: string;
    }>;
    admin_note?: string | null;
    refund_amount?: number | null;
    refund_method?: string | null;
    resolved_at?: string | null;
}

export interface InventoryLog extends Timestamped {
    id: Id;
    product_id: Id;
    variant_id?: Id | null;
    type: InventoryType;
    quantity: number;
    reference_type?: string | null;
    reference_id?: Id | null;
    note?: string | null;
    created_by?: Id | null;
}

export interface CouponUse extends Timestamped {
    id: Id;
    coupon_id: Id;
    user_id: Id;
    order_id: Id;
    discount_amount: number;
}

export interface Banner extends Timestamped {
    id: Id;
    title: string;
    subtitle?: string | null;
    image: string;
    link?: string | null;
    sort_order: number;
    is_active: boolean;
    starts_at?: string | null;
    expires_at?: string | null;
}

export interface FlashSale extends Timestamped {
    id: Id;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    items?: FlashSaleItem[];
}

export interface FlashSaleItem extends Timestamped {
    id: Id;
    flash_sale_id: Id;
    product_id: Id;
    variant_id?: Id | null;
    sale_price: number;
    stock_limit?: number | null;
    sold_count: number;
    product?: Product;
    variant?: ProductVariant | null;
}

export interface CmsPage extends Timestamped {
    id: Id;
    title: string;
    slug: string;
    content: string;
    meta_title?: string | null;
    meta_description?: string | null;
    is_published: boolean;
}

export interface ContactMessage extends Timestamped {
    id: Id;
    name: string;
    email: string;
    subject: string;
    message: string;
    is_read: boolean;
    read_at?: string | null;
}

export interface NewsletterSubscriber extends Timestamped {
    id: Id;
    email: string;
    name?: string | null;
    token: string;
    is_active: boolean;
    subscribed_at: string;
    unsubscribed_at?: string | null;
}

export interface Review extends Timestamped, SoftDeletable {
    id: Id;
    product_id: Id;
    user_id: Id;
    order_id?: Id | null;
    rating: number;
    title?: string | null;
    body: string;
    images: string[];
    is_approved: boolean;
    is_verified_purchase?: boolean;
    helpful_count?: number;
    not_helpful_count?: number;
    user_voted?: boolean | null;
    user: Pick<User, 'id' | 'name' | 'avatar'>;
}

export interface ReviewVote extends Timestamped {
    review_id: Id;
    user_id: Id;
    is_helpful: boolean;
}

export interface ShippingRate extends Timestamped {
    id: Id;
    method_id: Id;
    min_weight: number;
    max_weight: number;
    rate: number;
}

export interface RecentlyViewedProduct extends Timestamped {
    id: Id;
    user_id: Id;
    product_id: Id;
    viewed_at: string;
    product?: Product;
}

export interface Blog extends Timestamped {
    id: Id;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string;
    featured_image: string | null;
    author_name: string | null;
    meta_title: string | null;
    meta_description: string | null;
    is_published: boolean;
    published_at: string | null;
}

export interface PageLink {
    url?: string | null;
    label: string;
    active: boolean;
}

export interface PointTransaction {
    id: Id;
    type:
        | 'earn_order'
        | 'earn_referral'
        | 'earn_registration'
        | 'earn_review'
        | 'earn_daily_login'
        | 'redeem'
        | 'revoke'
        | 'refund'
        | 'revoke_order'
        | 'refund_partial';
    points: number;
    currency_amount: number | null;
    note: string | null;
    referred_user: { id: Id; name: string } | null;
    created_at: string;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PageLink[];
}

export interface ProductFilters {
    category?: string;
    brand?: string[];
    min_price?: number;
    max_price?: number;
    rating?: number;
    in_stock?: boolean;
    attributes?: Record<string, string[]>;
    sort?: string;
    page?: number;
    q?: string;
}

export interface BreadcrumbItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export type AppVariant = 'header' | 'sidebar';

export interface AuthLayoutProps {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export interface NavSubCategory {
    id: Id;
    name: string;
    slug: string;
    description?: string | null;
    category_id: Id;
}

export interface NavCategory {
    id: Id;
    name: string;
    slug: string;
    description?: string | null;
    icon?: string | null;
    color?: string | null;
    image?: string | null;
    sub_categories: NavSubCategory[];
}

export interface HomepageSetting {
    id: Id;
    section_key: string;
    section_name: string;
    title?: string | null;
    description?: string | null;
    eyebrow?: string | null;
    action_label?: string | null;
    action_href?: string | null;
    is_visible: boolean;
    sort_order: number;
    metadata?: Record<string, any> | null;
}

export interface HeroSlide {
    id: Id;
    eyebrow?: string | null;
    title: string;
    description?: string | null;
    image: string;
    primary_cta_label?: string | null;
    primary_cta_href?: string | null;
    stats?: { label: string; value: string }[] | null;
    is_active: boolean;
    sort_order: number;
}

export interface PromotionalBanner {
    id: Id;
    title: string;
    description?: string | null;
    image: string;
    href?: string | null;
    badge?: string | null;
    accent?: string | null;
    is_active: boolean;
    sort_order: number;
}

export interface SharedPageProps {
    [key: string]: unknown;
    name: string;
    appName: string;
    auth: Auth;
    cart: Cart | null;
    /** Admin › Settings › Brand Colour override (inline CSS), or null for the default colour. */
    brandCss?: string | null;
    active_bundle_recipes: BundleRecipe[];
    flash: {
        success?: string | null;
        error?: string | null;
        info?: string | null;
        warning?: string | null;
        toast?: FlashToast | null;
        /** Checkout-specific: a deleted/unavailable product or an out-of-stock quantity blocked the order. See CheckoutController::collectCartIssues(). */
        cartIssues?: string[] | null;
        /** Checkout-specific: the cart's server-corrected prices after a stale-price refusal (BIZ-001) — Checkout.tsx re-hydrates its own cart store with this so a retry submits the real price instead of repeating the same refusal forever. See CheckoutController::store(). */
        priceUpdates?: Cart | null;
    };
    sidebarOpen: boolean;
    locale: string;
    currencySymbol: string;
    settings: AppSettings;
    /**
     * The brand under its own key (HandleInertiaRequests): admin pages that
     * send their own `settings` prop replace the shared one, so the site
     * logo and the page loader read this instead.
     */
    brand: Pick<
        AppSettings,
        | 'site_name'
        | 'logo'
        | 'loading_logo'
        | 'loading_spin_seconds'
        | 'loading_ring_size'
        | 'loading_logo_size'
        | 'loading_ring_thickness'
    >;
    navCategories: NavCategory[];
    /** Every page gets just `{ announcement_bar }`; Home overrides with the full section-config object (see HomeController). */
    homepage_settings: Record<string, HomepageSetting>;
    /** Home-only now — absent on every other page (see HomeController). */
    hero_slides?: HeroSlide[];
    /** Home-only now — absent on every other page (see HomeController). */
    promotional_banners?: PromotionalBanner[];
    wishlist_ids: number[];
    cart_base_shipping_rate: number | null;
    account_stats: {
        orders_count: number;
        wishlist_count: number;
        addresses_count: number;
    } | null;
    unseen_orders_count: number | null;
    /** Each admin list's last-viewed URL (page + filters), keyed by route name; null for a non-admin. Read via `useAdminListUrl`. */
    admin_list_urls: Record<string, string> | null;
    /** OPS-005: false while the bKash/Nagad SMS-forwarder phone looks dead — checkout and the landing-page funnel hide the mobile banking option while this is false, since a Transaction ID submitted then can never be matched. */
    mobile_banking_available: boolean;
    /** PERF-006 (web push follow-up): admin sidebar badge, null for a non-admin or an admin without the received_sms permission. */
    unmatched_sms_count: number | null;
    /** PERF-006 (web push follow-up): this admin's VAPID public key for subscribing to push, null for a non-admin. */
    vapid_public_key: string | null;
    /** Plain-English mirror of the active password policy (App\Support\PasswordPolicy) — shown on every page that sets a new password, instead of a static/wrong hint. */
    password_requirements: string[];
}

export interface UiStateToast extends FlashToast {
    id: string;
}

export interface UiState {
    cartOpen: boolean;
    mobileMenuOpen: boolean;
    searchOpen: boolean;
    toasts: UiStateToast[];
}

export interface ProductCardState {
    product: Product;
    inCart?: boolean;
    inWishlist?: boolean;
}
