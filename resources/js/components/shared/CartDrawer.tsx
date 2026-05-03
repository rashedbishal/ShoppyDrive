import { Link, router } from '@inertiajs/react';
import { ShoppingBag, Trash2, X } from 'lucide-react';
import React, { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { Input } from '@/components/ui/input';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';

export function CartDrawer(): React.JSX.Element {
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
    
    const { cartOpen, closeCart } = useUiStore();
    const [couponCode, setCouponCode] = useState('');
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);

    const handleApplyCoupon = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!couponCode.trim()) return;

        setIsApplyingCoupon(true);
        try {
            // In a real app, this would be an API call
            const response = await fetch(`/api/coupons/validate?code=${couponCode}`);
            if (response.ok) {
                const data = await response.json();
                applyCoupon(data.coupon);
                setCouponCode('');
            } else {
                // handle error
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

    const footer = (
        <div className="space-y-4">
            {/* Coupon Code */}
            {!coupon ? (
                <form onSubmit={handleApplyCoupon} className="flex gap-2">
                    <Input
                        placeholder="Coupon code"
                        value={couponCode}
                        onChange={(e) => setCouponCode(e.target.value)}
                        className="h-10 rounded-xl"
                    />
                    <Button 
                        type="submit" 
                        variant="secondary" 
                        disabled={isApplyingCoupon || !couponCode.trim()}
                        className="h-10 rounded-xl whitespace-nowrap"
                    >
                        Apply
                    </Button>
                </form>
            ) : (
                <div className="flex items-center justify-between p-3 rounded-xl bg-[var(--color-primary-light)] border border-[var(--color-primary)]/10">
                    <div className="flex flex-col">
                        <span className="text-[10px] uppercase font-bold text-[var(--color-primary)] tracking-widest">Active Coupon</span>
                        <span className="text-sm font-bold text-[var(--color-text-primary)]">{coupon.code}</span>
                    </div>
                    <button 
                        onClick={() => applyCoupon(null)}
                        className="p-1.5 hover:bg-white rounded-lg text-[var(--color-error)] transition-colors"
                    >
                        <X size={16} />
                    </button>
                </div>
            )}

            {/* Totals */}
            <div className="space-y-2 pt-2">
                <div className="flex justify-between text-sm">
                    <span className="text-[var(--color-text-secondary)]">Subtotal</span>
                    <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(subtotal)}</span>
                </div>
                {discount > 0 && (
                    <div className="flex justify-between text-sm">
                        <span className="text-[var(--color-text-secondary)]">Discount</span>
                        <span className="font-bold text-[var(--color-success)]">-{formatCurrency(discount)}</span>
                    </div>
                )}
                <div className="flex justify-between text-sm">
                    <span className="text-[var(--color-text-secondary)]">Shipping</span>
                    <span className="font-bold text-[var(--color-text-primary)]">
                        {shippingEstimate > 0 ? formatCurrency(shippingEstimate) : 'Calculated at checkout'}
                    </span>
                </div>
                <div className="pt-2 border-t border-[var(--color-border)] flex justify-between items-center">
                    <span className="text-lg font-bold text-[var(--color-text-primary)]">Total</span>
                    <span className="text-2xl font-bold text-[var(--color-primary)]">{formatCurrency(total)}</span>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-2 pt-2">
                <Button 
                    asChild
                    size="lg"
                    className="w-full h-12 rounded-xl font-bold"
                    onClick={closeCart}
                >
                    <Link href="/checkout">
                        Proceed to Checkout
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    asChild
                    className="w-full h-11 font-bold text-[var(--color-text-secondary)] hover:text-[var(--color-primary)] hover:bg-[var(--color-primary-light)] rounded-xl"
                    onClick={closeCart}
                >
                    <Link href="/cart">
                        View Shopping Cart
                    </Link>
                </Button>
            </div>
        </div>
    );

    return (
        <Drawer
            isOpen={cartOpen}
            onClose={closeCart}
            title="Your Cart"
            description={items.length > 0 ? `You have ${items.length} items in your cart` : 'Your cart is empty'}
            footer={items.length > 0 ? footer : null}
            size="md"
        >
            {items.length > 0 ? (
                <div className="space-y-6">
                    {items.map((item) => (
                        <div key={item.item_key} className="flex gap-4">
                            <Link 
                                href={`/product/${item.product.slug}`} 
                                className="w-20 h-20 rounded-xl overflow-hidden bg-[var(--color-surface-alt)] border border-[var(--color-border)] shrink-0"
                                onClick={closeCart}
                            >
                                <img 
                                    src={item.variant?.image || item.product.primary_image_url || item.product.images[0]?.path} 
                                    alt={item.product.name} 
                                    className="w-full h-full object-cover" 
                                />
                            </Link>
                            
                            <div className="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                                <div>
                                    <div className="flex justify-between items-start gap-2">
                                        <Link 
                                            href={`/product/${item.product.slug}`}
                                            className="text-sm font-bold text-[var(--color-text-primary)] hover:text-[var(--color-primary)] transition-colors truncate"
                                            onClick={closeCart}
                                        >
                                            {item.product.name}
                                        </Link>
                                        <button 
                                            onClick={() => removeItem(item.item_key)}
                                            className="p-1 text-[var(--color-text-muted)] hover:text-[var(--color-error)] transition-colors"
                                        >
                                            <Trash2 size={16} />
                                        </button>
                                    </div>
                                    {item.variant && (
                                        <div className="flex flex-wrap gap-x-2 gap-y-1 mt-1">
                                            {Object.entries(item.variant.attributes).map(([key, value]) => (
                                                <span key={key} className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-wider">
                                                    {key}: {value}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="flex justify-between items-center mt-2">
                                    <QuantityStepper
                                        value={item.quantity}
                                        onChange={(val) => updateQuantity(item.item_key, val)}
                                        max={item.variant?.stock_quantity ?? item.product.stock_quantity}
                                        className="h-8 rounded-lg scale-90 -ml-2"
                                    />
                                    <span className="text-sm font-bold text-[var(--color-primary)]">
                                        {formatCurrency(item.subtotal)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="h-full flex flex-col items-center justify-center text-center px-6">
                    <div className="w-20 h-20 rounded-full bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-text-muted)] mb-4">
                        <ShoppingBag size={40} />
                    </div>
                    <h3 className="text-lg font-bold text-[var(--color-text-primary)] mb-2">Your cart is empty</h3>
                    <p className="text-sm text-[var(--color-text-secondary)] mb-8">
                        Looks like you haven't added anything to your cart yet.
                    </p>
                    <Button asChild onClick={closeCart} className="rounded-xl px-8 font-bold">
                        <Link href="/products">
                            Start Shopping
                        </Link>
                    </Button>
                </div>
            )}
        </Drawer>
    );
}
