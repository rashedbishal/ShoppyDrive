import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShoppingBag, Trash2, X } from 'lucide-react';
import React, { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import AppLayout from '@/components/layout/AppLayout';

export default function Cart(): React.JSX.Element {
    const { 
        items, 
        removeItem, 
        updateQuantity, 
        getSubtotal, 
        getDiscount, 
        getTotal, 
        coupon,
        applyCoupon,
        shippingEstimate
    } = useCartStore();

    const [couponCode, setCouponCode] = useState('');
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);

    const handleApplyCoupon = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!couponCode.trim()) return;

        setIsApplyingCoupon(true);
        try {
            const response = await fetch(`/api/coupons/validate?code=${couponCode}`);
            if (response.ok) {
                const data = await response.json();
                applyCoupon(data.coupon);
                setCouponCode('');
            }
        } catch (error) {
            console.error('Coupon error:', error);
        } finally {
            setIsApplyingCoupon(false);
        }
    };

    const subtotal = getSubtotal();
    const discount = getDiscount();
    const total = getTotal();

    if (items.length === 0) {
        return (
            <AppLayout>
                <div className="flex items-center justify-center py-20">
                    <Head title="Shopping Cart" />
                    <div className="max-w-md w-full bg-white rounded-3xl p-12 shadow-sm border border-[var(--color-border)] text-center">
                        <div className="w-24 h-24 rounded-full bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-text-muted)] mx-auto mb-6">
                            <ShoppingBag size={48} />
                        </div>
                        <h1 className="text-2xl font-bold text-[var(--color-text-primary)] mb-3">Your cart is empty</h1>
                        <p className="text-[var(--color-text-secondary)] mb-10">
                            Looks like you haven't added anything to your cart yet. Browse our products and find something you like!
                        </p>
                        <Button asChild className="w-full h-14 rounded-2xl font-bold text-lg shadow-lg">
                            <Link href={route('products.index')}>
                                Start Shopping
                            </Link>
                        </Button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <div className="py-12">
                <Head title="Shopping Cart" />
                
                <div className="container mx-auto px-4">
                    <div className="flex items-center gap-4 mb-8">
                        <Link 
                            href={route('products.index')}
                            className="p-2 rounded-full bg-white border border-[var(--color-border)] text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors"
                        >
                            <ArrowLeft size={20} />
                        </Link>
                        <h1 className="text-3xl font-serif font-bold text-[var(--color-text-primary)]">Shopping Cart</h1>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                        {/* Cart Items */}
                        <div className="lg:col-span-8 space-y-4">
                            <div className="bg-white rounded-3xl shadow-sm border border-[var(--color-border)] overflow-hidden">
                                <div className="hidden md:grid grid-cols-12 gap-4 px-8 py-4 border-b border-[var(--color-border)] text-xs font-bold text-[var(--color-text-muted)] uppercase tracking-widest">
                                    <div className="col-span-6">Product</div>
                                    <div className="col-span-2 text-center">Price</div>
                                    <div className="col-span-2 text-center">Quantity</div>
                                    <div className="col-span-2 text-right">Total</div>
                                </div>

                                <div className="divide-y divide-[var(--color-border)]">
                                    {items.map((item) => (
                                        <div key={item.item_key} className="p-6 md:p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                                            {/* Product Info */}
                                            <div className="md:col-span-6 flex gap-4 md:gap-6">
                                                <Link 
                                                    href={route('product.show', item.product.slug)}
                                                    className="w-24 h-24 md:w-32 md:h-32 rounded-2xl overflow-hidden bg-[var(--color-surface-alt)] border border-[var(--color-border)] shrink-0"
                                                >
                                                    <img 
                                                        src={item.variant?.image || item.product.primary_image_url || item.product.images[0]?.path} 
                                                        alt={item.product.name} 
                                                        className="w-full h-full object-cover" 
                                                    />
                                                </Link>
                                                <div className="flex flex-col justify-center min-w-0">
                                                    <Link 
                                                        href={route('product.show', item.product.slug)}
                                                        className="text-lg font-bold text-[var(--color-text-primary)] hover:text-[var(--color-primary)] transition-colors line-clamp-2 mb-1"
                                                    >
                                                        {item.product.name}
                                                    </Link>
                                                    {item.variant && (
                                                        <div className="flex flex-wrap gap-x-3 gap-y-1">
                                                            {Object.entries(item.variant.attributes).map(([key, value]) => (
                                                                <span key={key} className="text-xs font-bold text-[var(--color-text-muted)] uppercase tracking-wider">
                                                                    {key}: {value}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                    <button 
                                                        onClick={() => removeItem(item.item_key)}
                                                        className="mt-4 flex items-center gap-2 text-sm font-bold text-[var(--color-error)] hover:opacity-80 transition-opacity"
                                                    >
                                                        <Trash2 size={16} />
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Price (Mobile labels shown via CSS or separate divs) */}
                                            <div className="md:col-span-2 text-center">
                                                <span className="md:hidden text-xs font-bold text-[var(--color-text-muted)] uppercase block mb-1">Price</span>
                                                <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(item.price)}</span>
                                            </div>

                                            {/* Quantity */}
                                            <div className="md:col-span-2 flex flex-col items-center">
                                                <span className="md:hidden text-xs font-bold text-[var(--color-text-muted)] uppercase block mb-1">Quantity</span>
                                                <QuantityStepper
                                                    value={item.quantity}
                                                    onChange={(val) => updateQuantity(item.item_key, val)}
                                                    max={item.variant?.stock_quantity ?? item.product.stock_quantity}
                                                    className="h-10"
                                                />
                                            </div>

                                            {/* Total */}
                                            <div className="md:col-span-2 text-right">
                                                <span className="md:hidden text-xs font-bold text-[var(--color-text-muted)] uppercase block mb-1">Total</span>
                                                <span className="text-xl font-bold text-[var(--color-primary)]">{formatCurrency(item.subtotal)}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-between items-center px-4">
                                <Link 
                                    href={route('products.index')}
                                    className="text-sm font-bold text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors flex items-center gap-2"
                                >
                                    <ArrowLeft size={16} />
                                    Continue Shopping
                                </Link>
                                <span className="text-sm text-[var(--color-text-muted)] font-medium">
                                    {items.length} items in your cart
                                </span>
                            </div>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-4">
                            <div className="bg-white rounded-3xl p-8 shadow-sm border border-[var(--color-border)] sticky top-24">
                                <h2 className="text-xl font-bold text-[var(--color-text-primary)] mb-6">Order Summary</h2>
                                
                                {/* Coupon */}
                                <div className="mb-8">
                                    <p className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-wider mb-3">Promo Code</p>
                                    {!coupon ? (
                                        <form onSubmit={handleApplyCoupon} className="flex gap-2">
                                            <Input
                                                placeholder="Enter code"
                                                value={couponCode}
                                                onChange={(e) => setCouponCode(e.target.value)}
                                                className="h-12 rounded-xl"
                                            />
                                            <Button 
                                                type="submit" 
                                                variant="secondary" 
                                                disabled={isApplyingCoupon || !couponCode.trim()}
                                                className="h-12 px-6 rounded-xl font-bold"
                                            >
                                                Apply
                                            </Button>
                                        </form>
                                    ) : (
                                        <div className="flex items-center justify-between p-4 rounded-2xl bg-[var(--color-primary-light)] border border-[var(--color-primary)]/10">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 rounded-full bg-white flex items-center justify-center text-[var(--color-primary)] shadow-sm">
                                                    <ShoppingBag size={18} />
                                                </div>
                                                <div className="flex flex-col">
                                                    <span className="text-xs font-bold text-[var(--color-primary)] uppercase tracking-widest leading-none">Coupon Active</span>
                                                    <span className="text-base font-bold text-[var(--color-text-primary)]">{coupon.code}</span>
                                                </div>
                                            </div>
                                            <button 
                                                onClick={() => applyCoupon(null)}
                                                className="p-2 hover:bg-white rounded-xl text-[var(--color-error)] transition-colors shadow-sm"
                                            >
                                                <X size={20} />
                                            </button>
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-4 mb-8">
                                    <div className="flex justify-between items-center text-[var(--color-text-secondary)]">
                                        <span className="font-medium">Subtotal</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(subtotal)}</span>
                                    </div>
                                    {discount > 0 && (
                                        <div className="flex justify-between items-center">
                                            <span className="text-[var(--color-text-secondary)] font-medium">Discount</span>
                                            <span className="font-bold text-[var(--color-success)]">-{formatCurrency(discount)}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between items-center text-[var(--color-text-secondary)]">
                                        <span className="font-medium">Shipping</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">
                                            {shippingEstimate > 0 ? formatCurrency(shippingEstimate) : 'Calculated at checkout'}
                                        </span>
                                    </div>
                                    <div className="h-px bg-[var(--color-border)] w-full my-4" />
                                    <div className="flex justify-between items-center">
                                        <span className="text-xl font-bold text-[var(--color-text-primary)]">Total</span>
                                        <span className="text-3xl font-bold text-[var(--color-primary)]">{formatCurrency(total)}</span>
                                    </div>
                                </div>

                                <Button 
                                    asChild
                                    size="lg"
                                    className="w-full h-14 rounded-2xl font-bold text-lg shadow-lg shadow-[var(--color-primary)]/20"
                                >
                                    <Link href={route('checkout')}>
                                        Proceed to Checkout
                                    </Link>
                                </Button>
                                
                                <div className="mt-8 pt-8 border-t border-[var(--color-border)]">
                                    <div className="flex items-center gap-4 text-xs font-bold text-[var(--color-text-muted)] uppercase tracking-widest justify-center">
                                        <span>Secure Payment</span>
                                        <div className="w-1 h-1 rounded-full bg-[var(--color-border)]" />
                                        <span>Easy Returns</span>
                                        <div className="w-1 h-1 rounded-full bg-[var(--color-border)]" />
                                        <span>Genuine Products</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
