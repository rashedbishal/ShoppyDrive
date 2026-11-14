export type Compare = { value: number; previous: number };

export type DashboardRange = {
    preset: 'today' | '7d' | '30d' | '90d' | 'month' | 'custom';
    from: string;
    to: string;
    days: number;
    label: string;
};

export type DashboardAccess = {
    orders: boolean;
    products: boolean;
    customers: boolean;
    reviews: boolean;
    promotions: boolean;
    edit_target: boolean;
};

export type TrendPoint = {
    key: string;
    label: string;
    revenue: number;
    orders: number;
    previous_revenue: number;
    previous_orders: number;
    visitors: number | null;
    new_customers: number;
};

export type MonthlyTarget = {
    target: number | null;
    month_revenue: number;
    projected: number;
    day_of_month: number;
    days_in_month: number;
    month_label: string;
};

export type Overview = {
    kpis: {
        revenue: Compare;
        orders: Compare;
        average_order_value: Compare;
        visitors: Compare;
        conversion_rate: Compare;
        new_customers: Compare | null;
    };
    trend: TrendPoint[];
    today: {
        revenue: number;
        orders: number;
        yesterday_revenue: number;
        yesterday_orders: number;
        as_of: string;
    };
    target: MonthlyTarget;
};

export type ActionNeeded = {
    pending_orders: number | null;
    processing_not_sent: number | null;
    mobile_payments_to_check: number | null;
    courier_unknown: number | null;
    cod_with_courier: { orders: number; amount: number } | null;
    pending_returns: number | null;
    pending_reviews: number | null;
    out_of_stock: number | null;
    low_stock: number | null;
    abandoned_checkouts: number | null;
};

export type OrderHealth = {
    statuses: { status: string; count: number }[];
    payment_methods: { method: string; orders: number; amount: number }[];
    funnel: { step: string; count: number }[];
    cancellation_rate: number;
    cancelled: number;
    return_rate: number;
    returned: number;
    refunds: { count: number; amount: number };
    discounts: { amount: number; orders: number };
};

export type CourierRow = {
    courier: 'pathao' | 'steadfast';
    sent: number;
    delivered: number;
    in_transit: number;
    cancelled: number;
    delivered_rate: number;
    cancelled_rate: number;
    average_days: number | null;
};

export type RankedRow = {
    id: number | null;
    name: string;
    units: number;
    revenue: number;
};

export type ProductInsights = {
    top_products: RankedRow[];
    top_categories: RankedRow[];
    top_brands: RankedRow[];
    slow_movers: {
        id: number;
        name: string;
        stock: number;
        days_listed: number;
    }[];
    most_wishlisted: { id: number; name: string; wishlists: number }[];
    out_of_stock: { id: number; name: string; sku: string | null }[];
    stock_activity:
        | {
              id: number;
              product_id: number | null;
              product: string;
              type: string;
              quantity: number;
              note: string | null;
              created_at: string | null;
          }[]
        | null;
};

export type Promotions = {
    flash_sales:
        | {
              id: number;
              name: string;
              sold: number;
              stock_limit: number | null;
              revenue: number;
              is_active: boolean;
          }[]
        | null;
    bundles:
        | { id: number; name: string; orders: number; revenue: number }[]
        | null;
    coupons:
        | { id: number; code: string; uses: number; discount: number }[]
        | null;
};

export type CustomerInsights = {
    new_customers: number;
    returning_customers: number;
    guest_orders: number;
    registered_orders: number;
    repeat_rate: number;
    average_lifetime_spend: number;
    top_customers: {
        id: number;
        name: string;
        orders: number;
        spent: number;
    }[];
    districts: { district: string; orders: number }[];
    loyalty: {
        points_outstanding: number;
        points_earned: number;
        points_redeemed: number;
    };
    referrals: number;
    newsletter: { subscribers: number; joined: number; left: number } | null;
};

export type Marketing = {
    traffic_sources:
        | { source: string; orders: number; revenue: number }[]
        | null;
    devices: { mobile: number; tablet: number; desktop: number };
    heatmap: number[][] | null;
    landing_pages:
        | { id: number; title: string; orders: number; amount: number }[]
        | null;
    abandoned: { count: number; value: number; reminded: number } | null;
    top_searches: { term: string; searches: number; results: number }[] | null;
    no_result_searches: { term: string; searches: number }[] | null;
};

export type ReviewInsights = {
    average: number;
    total: number;
    distribution: { stars: number; reviews: number }[];
    latest: {
        id: number;
        product: string;
        customer: string;
        rating: number;
        text: string;
        is_approved: boolean;
        created_at: string | null;
    }[];
    lowest_rated: {
        id: number;
        name: string;
        average: number;
        reviews: number;
    }[];
};

export type LiveOrder = {
    id: number;
    order_number: string;
    customer: string;
    district: string;
    total: number;
    status: string;
    payment_method: string;
    created_at: string | null;
};

export type LiveFeed = {
    orders: LiveOrder[];
    today_orders: number;
    server_time: string;
};
