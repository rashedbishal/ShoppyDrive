export type StorefrontLink = {
    label: string;
    href: string;
    description?: string;
};

export type StorefrontFeature = {
    label: string;
    href: string;
    badge?: string;
    description: string;
};

export const primaryLinks: StorefrontLink[] = [
    { label: 'Home', href: '/' },
    { label: 'Shop', href: '/products' },
    { label: 'Flash Sale', href: '/flash-sales' },
    { label: 'Brands', href: '/brands' },
    { label: 'About', href: '/about' },
    { label: 'Contact', href: '/contact' },
];

export const footerQuickLinks: StorefrontLink[] = [
    { label: 'Home', href: '/' },
    { label: 'All Products', href: '/products' },
    { label: 'Flash Sale', href: '/flash-sales' },
    { label: 'Track Order', href: '/track-order' },
    { label: 'About', href: '/about' },
    { label: 'Contact', href: '/contact' },
];

export const footerCustomerLinks: StorefrontLink[] = [
    { label: 'My Account', href: '/account' },
    { label: 'Orders', href: '/account/orders' },
    { label: 'Wishlist', href: '/account/wishlist' },
    { label: 'Addresses', href: '/account/addresses' },
    { label: 'Privacy Policy', href: '/privacy-policy' },
    { label: 'Terms', href: '/terms' },
];

export const storeCategories: StorefrontLink[] = [
    { label: "Men's Clothing", href: '/category/mens-clothing', description: 'T-shirts, shirts, jeans, hoodies & traditional wear' },
    { label: "Women's Clothing", href: '/category/womens-clothing', description: 'Dresses, tops, sarees, kurtis & more' },
    { label: 'Shoes', href: '/category/shoes', description: 'Sneakers, formals, heels, sandals & boots' },
    { label: 'Bags & Luggage', href: '/category/bags-luggage', description: 'Backpacks, handbags, wallets & travel bags' },
    { label: 'Jewelry & Accessories', href: '/category/jewelry-accessories', description: 'Necklaces, earrings, sunglasses & belts' },
    { label: 'Watches', href: '/category/watches', description: "Men's, women's, smart & couple watches" },
    { label: 'Electronics & Gadgets', href: '/category/electronics', description: 'Earbuds, phone accessories, power banks & gadgets' },
    { label: 'Beauty & Health', href: '/category/beauty-health', description: 'Makeup, skincare, haircare & fragrances' },
    { label: 'Home & Living', href: '/category/home-living', description: 'Decor, kitchen, bedding & storage' },
    { label: 'Mother & Kids', href: '/category/mother-kids', description: "Boys', girls', baby clothes & toys" },
    { label: 'Sports & Outdoors', href: '/category/sports-outdoors', description: 'Activewear, sports shoes & gym equipment' },
];

export const featuredStorePicks: StorefrontFeature[] = [
    {
        label: 'Flash Sale Deals',
        href: '/flash-sales',
        badge: 'Hot',
        description: 'Limited-time prices on top-rated products.',
    },
    {
        label: 'New Arrivals',
        href: '/products?sort=newest',
        badge: 'New',
        description: 'Fresh drops across all categories.',
    },
    {
        label: 'Best Sellers',
        href: '/products?sort=popular',
        badge: 'Popular',
        description: 'Most loved items by our shoppers.',
    },
];
