import React, { useState, useEffect } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { 
    Check, 
    ChevronRight, 
    CreditCard, 
    Truck, 
    MapPin, 
    User as UserIcon, 
    ShoppingBag,
    ArrowLeft,
    AlertCircle
} from 'lucide-react';
import { AnimatePresence, motion } from 'framer-motion';

import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import type { Address, ShippingMethod, Product, CartItem, User, SharedPageProps } from '@/types';

type CheckoutStep = 'info' | 'address' | 'shipping' | 'payment' | 'review';

const STEPS: { id: CheckoutStep; label: string; icon: any }[] = [
    { id: 'info', label: 'Info', icon: UserIcon },
    { id: 'address', label: 'Address', icon: MapPin },
    { id: 'shipping', label: 'Shipping', icon: Truck },
    { id: 'payment', label: 'Payment', icon: CreditCard },
    { id: 'review', label: 'Review', icon: ShoppingBag },
];

interface CheckoutProps {
    savedAddresses: Address[];
    availableShippingMethods: ShippingMethod[];
}

export default function Checkout({ 
    savedAddresses = [], 
    availableShippingMethods = [] 
}: CheckoutProps): React.JSX.Element {
    const { auth } = usePage<SharedPageProps>().props;
    const { items, getSubtotal, getDiscount, getTotal, coupon, shippingEstimate, setShippingEstimate } = useCartStore();
    
    const [currentStep, setCurrentStep] = useState<CheckoutStep>('info');
    const [selectedAddressId, setSelectedAddressId] = useState<number | null>(
        savedAddresses.find(a => a.is_default)?.id || (savedAddresses.length > 0 ? savedAddresses[0].id : null)
    );

    const form = useForm({
        // Info
        name: auth.user?.name || '',
        email: auth.user?.email || '',
        phone: auth.user?.phone || '',
        
        // Address
        address_line1: '',
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'Bangladesh',
        use_saved_address: savedAddresses.length > 0,
        address_id: savedAddresses.find(a => a.is_default)?.id || null,
        save_address: false,

        // Shipping
        shipping_method_id: availableShippingMethods[0]?.id || null,

        // Payment
        payment_method: 'cod' as 'cod',
    });

    // Sync shipping cost when method changes
    useEffect(() => {
        const method = availableShippingMethods.find(m => m.id === form.data.shipping_method_id);
        if (method) {
            setShippingEstimate(method.cost);
        }
    }, [form.data.shipping_method_id, availableShippingMethods]);

    const subtotal = getSubtotal();
    const discount = getDiscount();
    const total = getTotal();

    const goToStep = (step: CheckoutStep) => {
        // Simple validation logic before moving
        if (currentStep === 'info') {
            if (!form.data.name || !form.data.email || !form.data.phone) {
                // Trigger validation or show errors
                return;
            }
        }
        if (currentStep === 'address') {
            if (!form.data.use_saved_address && (!form.data.address_line1 || !form.data.city)) {
                return;
            }
        }
        setCurrentStep(step);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (currentStep !== 'review') return;

        form.post(route('checkout.store'), {
            onSuccess: () => {
                // Cart clearing is usually handled by the backend or upon reaching confirmation
            }
        });
    };

    const renderStepIndicator = () => (
        <div className="flex items-center justify-between max-w-2xl mx-auto mb-12">
            {STEPS.map((step, index) => {
                const isCompleted = STEPS.findIndex(s => s.id === currentStep) > index;
                const isActive = step.id === currentStep;
                const Icon = step.icon;

                return (
                    <React.Fragment key={step.id}>
                        <div className="flex flex-col items-center gap-2 relative z-10">
                            <button
                                onClick={() => isCompleted && setCurrentStep(step.id)}
                                disabled={!isCompleted && !isActive}
                                className={`w-10 h-10 rounded-full flex items-center justify-center transition-all ${
                                    isCompleted 
                                        ? 'bg-[var(--color-success)] text-white shadow-lg shadow-green-100' 
                                        : isActive 
                                            ? 'bg-[var(--color-primary)] text-white shadow-lg shadow-red-100 ring-4 ring-[var(--color-primary-light)]' 
                                            : 'bg-white border-2 border-[var(--color-border)] text-[var(--color-text-muted)]'
                                }`}
                            >
                                {isCompleted ? <Check size={18} /> : <Icon size={18} />}
                            </button>
                            <span className={`text-[10px] uppercase tracking-widest font-bold ${isActive ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-muted)]'}`}>
                                {step.label}
                            </span>
                        </div>
                        {index < STEPS.length - 1 && (
                            <div className="flex-1 h-0.5 mx-2 bg-[var(--color-border)] relative top-[-10px]">
                                <motion.div 
                                    className="h-full bg-[var(--color-success)]"
                                    initial={{ width: 0 }}
                                    animate={{ width: isCompleted ? '100%' : '0%' }}
                                    transition={{ duration: 0.5 }}
                                />
                            </div>
                        )}
                    </React.Fragment>
                );
            })}
        </div>
    );

    const renderSummary = () => (
        <div className="bg-white rounded-3xl p-6 md:p-8 border border-[var(--color-border)] shadow-sm sticky top-24">
            <h3 className="text-xl font-bold text-[var(--color-text-primary)] mb-6">Order Summary</h3>
            
            <div className="space-y-4 mb-6 max-h-[300px] overflow-y-auto pr-2 scrollbar-hide">
                {items.map((item) => (
                    <div key={item.item_key} className="flex gap-4">
                        <div className="w-16 h-16 rounded-xl bg-[var(--color-surface-alt)] overflow-hidden shrink-0 border border-[var(--color-border)]">
                            <img src={item.variant?.image || item.product.primary_image_url || item.product.images[0]?.path} alt={item.product.name} className="w-full h-full object-cover" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-bold text-[var(--color-text-primary)] truncate">{item.product.name}</p>
                            <p className="text-xs text-[var(--color-text-muted)] font-medium">Qty: {item.quantity}</p>
                            <p className="text-sm font-bold text-[var(--color-primary)] mt-1">{formatCurrency(item.subtotal)}</p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="space-y-3 pt-6 border-t border-[var(--color-border)]">
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
                        {shippingEstimate > 0 ? formatCurrency(shippingEstimate) : 'Free'}
                    </span>
                </div>
                <div className="pt-4 border-t border-[var(--color-border)] flex justify-between items-end">
                    <span className="text-lg font-bold text-[var(--color-text-primary)]">Total</span>
                    <div className="text-right">
                        <p className="text-2xl font-bold text-[var(--color-primary)]">{formatCurrency(total)}</p>
                        <p className="text-[10px] text-[var(--color-text-muted)] uppercase tracking-tighter">VAT Included</p>
                    </div>
                </div>
            </div>
            
            {currentStep === 'review' && (
                <Button 
                    onClick={handleSubmit} 
                    disabled={form.processing}
                    size="lg" 
                    className="w-full h-14 rounded-2xl font-bold text-lg mt-8 shadow-lg shadow-[var(--color-primary)]/20"
                >
                    {form.processing ? 'Processing...' : 'Place Order'}
                </Button>
            )}
        </div>
    );

    return (
        <CheckoutLayout>
            <Head title="Checkout" />
            
            {renderStepIndicator()}

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-8">
                    <form onSubmit={(e) => e.preventDefault()} className="space-y-8">
                        {/* Step 1: Customer Info */}
                        {currentStep === 'info' && (
                            <motion.div 
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                className="bg-white rounded-3xl p-6 md:p-10 border border-[var(--color-border)] shadow-sm"
                            >
                                <h2 className="text-2xl font-bold text-[var(--color-text-primary)] mb-8">Customer Information</h2>
                                
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">Full Name</Label>
                                            <Input 
                                                id="name" 
                                                value={form.data.name} 
                                                onChange={e => form.setData('name', e.target.value)} 
                                                placeholder="Enter your full name"
                                                className="h-12 rounded-xl"
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email Address</Label>
                                            <Input 
                                                id="email" 
                                                type="email"
                                                value={form.data.email} 
                                                onChange={e => form.setData('email', e.target.value)} 
                                                placeholder="example@mail.com"
                                                className="h-12 rounded-xl"
                                                required
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2 max-w-md">
                                        <Label htmlFor="phone">Phone Number</Label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-[var(--color-text-muted)]">+880</span>
                                            <Input 
                                                id="phone" 
                                                value={form.data.phone} 
                                                onChange={e => form.setData('phone', e.target.value)} 
                                                placeholder="1XXXXXXXXX"
                                                className="h-12 pl-16 rounded-xl"
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end mt-12">
                                    <Button onClick={() => goToStep('address')} size="lg" className="rounded-xl px-10 font-bold h-12">
                                        Continue to Shipping
                                        <ChevronRight size={18} className="ml-2" />
                                    </Button>
                                </div>
                            </motion.div>
                        )}

                        {/* Step 2: Shipping Address */}
                        {currentStep === 'address' && (
                            <motion.div 
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                className="bg-white rounded-3xl p-6 md:p-10 border border-[var(--color-border)] shadow-sm"
                            >
                                <h2 className="text-2xl font-bold text-[var(--color-text-primary)] mb-8">Shipping Address</h2>
                                
                                {savedAddresses.length > 0 && (
                                    <div className="mb-10">
                                        <p className="text-sm font-bold text-[var(--color-text-muted)] uppercase tracking-widest mb-4">Saved Addresses</p>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {savedAddresses.map((addr) => (
                                                <button
                                                    key={addr.id}
                                                    onClick={() => {
                                                        form.setData({
                                                            ...form.data,
                                                            use_saved_address: true,
                                                            address_id: addr.id
                                                        });
                                                    }}
                                                    className={`p-4 rounded-2xl border-2 text-left transition-all ${
                                                        form.data.use_saved_address && form.data.address_id === addr.id
                                                            ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] ring-4 ring-[var(--color-primary-light)]'
                                                            : 'border-[var(--color-border)] hover:border-[var(--color-text-primary)]'
                                                    }`}
                                                >
                                                    <div className="flex justify-between items-start mb-2">
                                                        <span className="text-sm font-bold text-[var(--color-text-primary)]">{addr.label || 'Home'}</span>
                                                        {addr.is_default && <Badge variant="secondary" className="text-[10px]">Default</Badge>}
                                                    </div>
                                                    <p className="text-xs text-[var(--color-text-secondary)] line-clamp-2 leading-relaxed">
                                                        {addr.address_line1}, {addr.city}
                                                    </p>
                                                </button>
                                            ))}
                                            <button
                                                onClick={() => form.setData('use_saved_address', false)}
                                                className={`p-4 rounded-2xl border-2 border-dashed flex flex-col items-center justify-center gap-2 transition-all ${
                                                    !form.data.use_saved_address
                                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                        : 'border-[var(--color-border)] text-[var(--color-text-muted)] hover:border-[var(--color-text-primary)] hover:text-[var(--color-text-primary)]'
                                                }`}
                                            >
                                                <Plus size={20} />
                                                <span className="text-sm font-bold">New Address</span>
                                            </button>
                                        </div>
                                    </div>
                                )}

                                {!form.data.use_saved_address && (
                                    <div className="space-y-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="address">Address Line 1</Label>
                                            <Input 
                                                id="address" 
                                                value={form.data.address_line1} 
                                                onChange={e => form.setData('address_line1', e.target.value)} 
                                                placeholder="Street address, P.O. box, company name"
                                                className="h-12 rounded-xl"
                                                required
                                            />
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div className="space-y-2">
                                                <Label htmlFor="city">City / District</Label>
                                                <Input 
                                                    id="city" 
                                                    value={form.data.city} 
                                                    onChange={e => form.setData('city', e.target.value)} 
                                                    placeholder="e.g. Dhaka"
                                                    className="h-12 rounded-xl"
                                                    required
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="state">Division / State</Label>
                                                <Input 
                                                    id="state" 
                                                    value={form.data.state} 
                                                    onChange={e => form.setData('state', e.target.value)} 
                                                    placeholder="e.g. Dhaka Division"
                                                    className="h-12 rounded-xl"
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div className="space-y-2">
                                                <Label htmlFor="postal">Postal Code</Label>
                                                <Input 
                                                    id="postal" 
                                                    value={form.data.postal_code} 
                                                    onChange={e => form.setData('postal_code', e.target.value)} 
                                                    placeholder="e.g. 1212"
                                                    className="h-12 rounded-xl"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="country">Country</Label>
                                                <Input 
                                                    id="country" 
                                                    value={form.data.country} 
                                                    readOnly
                                                    className="h-12 rounded-xl bg-[var(--color-surface-alt)]"
                                                />
                                            </div>
                                        </div>
                                        
                                        {auth.user && (
                                            <div className="flex items-center space-x-2">
                                                <Checkbox 
                                                    id="save_address" 
                                                    checked={form.data.save_address}
                                                    onCheckedChange={(checked) => form.setData('save_address', !!checked)}
                                                />
                                                <Label htmlFor="save_address" className="text-sm font-medium cursor-pointer">
                                                    Save this address for future orders
                                                </Label>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="flex justify-between mt-12 pt-8 border-t border-[var(--color-border)]">
                                    <Button variant="ghost" onClick={() => goToStep('info')} className="font-bold text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]">
                                        <ArrowLeft size={18} className="mr-2" />
                                        Back to Info
                                    </Button>
                                    <Button onClick={() => goToStep('shipping')} size="lg" className="rounded-xl px-10 font-bold h-12 shadow-lg">
                                        Select Shipping
                                        <ChevronRight size={18} className="ml-2" />
                                    </Button>
                                </div>
                            </motion.div>
                        )}

                        {/* Step 3: Shipping Method */}
                        {currentStep === 'shipping' && (
                            <motion.div 
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                className="bg-white rounded-3xl p-6 md:p-10 border border-[var(--color-border)] shadow-sm"
                            >
                                <h2 className="text-2xl font-bold text-[var(--color-text-primary)] mb-8">Shipping Method</h2>
                                
                                <RadioGroup 
                                    value={form.data.shipping_method_id?.toString()} 
                                    onValueChange={(val) => form.setData('shipping_method_id', parseInt(val))}
                                    className="space-y-4"
                                >
                                    {availableShippingMethods.length > 0 ? (
                                        availableShippingMethods.map((method) => (
                                            <div key={method.id} className="relative">
                                                <RadioGroupItem 
                                                    value={method.id.toString()} 
                                                    id={`ship-${method.id}`}
                                                    className="peer sr-only"
                                                />
                                                <Label 
                                                    htmlFor={`ship-${method.id}`}
                                                    className="flex items-center justify-between p-6 rounded-2xl border-2 border-[var(--color-border)] cursor-pointer transition-all peer-data-[state=checked]:border-[var(--color-primary)] peer-data-[state=checked]:bg-[var(--color-primary-light)] peer-data-[state=checked]:ring-4 peer-data-[state=checked]:ring-[var(--color-primary-light)] hover:border-[var(--color-text-primary)]"
                                                >
                                                    <div className="flex items-center gap-4">
                                                        <div className="w-12 h-12 rounded-xl bg-white border border-[var(--color-border)] flex items-center justify-center text-[var(--color-primary)] shadow-sm">
                                                            <Truck size={24} />
                                                        </div>
                                                        <div>
                                                            <p className="font-bold text-[var(--color-text-primary)]">{method.name}</p>
                                                            <p className="text-xs text-[var(--color-text-muted)] mt-0.5">Estimated delivery: {method.estimated_days} business days</p>
                                                        </div>
                                                    </div>
                                                    <span className="text-lg font-bold text-[var(--color-text-primary)]">
                                                        {method.cost === 0 ? 'FREE' : formatCurrency(method.cost)}
                                                    </span>
                                                </Label>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="p-12 text-center border-2 border-dashed border-[var(--color-border)] rounded-3xl">
                                            <Truck size={48} className="mx-auto text-[var(--color-text-muted)] mb-4 opacity-20" />
                                            <p className="text-[var(--color-text-muted)] font-medium">Standard Delivery (calculated automatically)</p>
                                            <p className="text-xl font-bold text-[var(--color-primary)] mt-2">৳60.00</p>
                                        </div>
                                    )}
                                </RadioGroup>

                                <div className="flex justify-between mt-12 pt-8 border-t border-[var(--color-border)]">
                                    <Button variant="ghost" onClick={() => goToStep('address')} className="font-bold text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]">
                                        <ArrowLeft size={18} className="mr-2" />
                                        Back to Address
                                    </Button>
                                    <Button onClick={() => goToStep('payment')} size="lg" className="rounded-xl px-10 font-bold h-12">
                                        Continue to Payment
                                        <ChevronRight size={18} className="ml-2" />
                                    </Button>
                                </div>
                            </motion.div>
                        )}

                        {/* Step 4: Payment */}
                        {currentStep === 'payment' && (
                            <motion.div 
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                className="bg-white rounded-3xl p-6 md:p-10 border border-[var(--color-border)] shadow-sm"
                            >
                                <h2 className="text-2xl font-bold text-[var(--color-text-primary)] mb-8">Payment Method</h2>
                                
                                <div className="rounded-2xl border-2 border-[var(--color-primary)] bg-[var(--color-primary-light)] p-6">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-[var(--color-border)] bg-white text-[var(--color-primary)] shadow-sm">
                                            <ShoppingBag size={24} />
                                        </div>
                                        <div>
                                            <p className="font-bold text-[var(--color-text-primary)]">Cash on Delivery</p>
                                            <p className="text-xs text-[var(--color-text-muted)]">Pay with cash when your order arrives</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-6 flex gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                                    <AlertCircle size={18} className="mt-0.5 shrink-0 text-[var(--color-text-muted)]" />
                                    <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                        Please keep the exact change ready for the delivery person. Delivery time may vary depending on your location.
                                    </p>
                                </div>

                                <div className="flex justify-between mt-12 pt-8 border-t border-[var(--color-border)]">
                                    <Button variant="ghost" onClick={() => goToStep('shipping')} className="font-bold text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]">
                                        <ArrowLeft size={18} className="mr-2" />
                                        Back to Shipping
                                    </Button>
                                    <Button onClick={() => goToStep('review')} size="lg" className="rounded-xl px-10 font-bold h-12">
                                        Review Order
                                        <ChevronRight size={18} className="ml-2" />
                                    </Button>
                                </div>
                            </motion.div>
                        )}

                        {/* Step 5: Review */}
                        {currentStep === 'review' && (
                            <motion.div 
                                initial={{ opacity: 0, scale: 0.98 }}
                                animate={{ opacity: 1, scale: 1 }}
                                className="space-y-6"
                            >
                                <div className="bg-white rounded-3xl p-6 md:p-10 border border-[var(--color-border)] shadow-sm">
                                    <h2 className="text-2xl font-bold text-[var(--color-text-primary)] mb-8">Review Order</h2>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <div className="space-y-6">
                                            <div className="p-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                                                <div className="flex justify-between items-center mb-3">
                                                    <h4 className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest">Customer Details</h4>
                                                    <button onClick={() => setCurrentStep('info')} className="text-xs font-bold text-[var(--color-primary)] hover:underline">Edit</button>
                                                </div>
                                                <p className="font-bold text-sm text-[var(--color-text-primary)]">{form.data.name}</p>
                                                <p className="text-xs text-[var(--color-text-secondary)] mt-1">{form.data.email}</p>
                                                <p className="text-xs text-[var(--color-text-secondary)] mt-1">+880 {form.data.phone}</p>
                                            </div>

                                            <div className="p-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                                                <div className="flex justify-between items-center mb-3">
                                                    <h4 className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest">Payment Method</h4>
                                                    <button onClick={() => setCurrentStep('payment')} className="text-xs font-bold text-[var(--color-primary)] hover:underline">Edit</button>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-lg bg-white border border-[var(--color-border)] flex items-center justify-center text-[var(--color-primary)]">
                                                        <ShoppingBag size={16} />
                                                    </div>
                                                    <p className="font-bold text-sm text-[var(--color-text-primary)]">Cash on Delivery</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="p-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] h-full">
                                            <div className="flex justify-between items-center mb-3">
                                                <h4 className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest">Shipping & Address</h4>
                                                <button onClick={() => setCurrentStep('address')} className="text-xs font-bold text-[var(--color-primary)] hover:underline">Edit</button>
                                            </div>
                                            <div className="space-y-4">
                                                <div className="flex gap-3">
                                                    <MapPin size={16} className="text-[var(--color-text-muted)] shrink-0 mt-0.5" />
                                                    <div className="text-xs text-[var(--color-text-secondary)] leading-relaxed">
                                                        {form.data.use_saved_address ? (
                                                            <p>{savedAddresses.find(a => a.id === form.data.address_id)?.address_line1}, {savedAddresses.find(a => a.id === form.data.address_id)?.city}</p>
                                                        ) : (
                                                            <p>{form.data.address_line1}, {form.data.city}, {form.data.state}</p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex gap-3">
                                                    <Truck size={16} className="text-[var(--color-text-muted)] shrink-0 mt-0.5" />
                                                    <div className="text-xs text-[var(--color-text-secondary)] leading-relaxed">
                                                        <p className="font-bold text-[var(--color-text-primary)]">
                                                            {availableShippingMethods.find(m => m.id === form.data.shipping_method_id)?.name || 'Standard Delivery'}
                                                        </p>
                                                        <p>Arriving in {availableShippingMethods.find(m => m.id === form.data.shipping_method_id)?.estimated_days || '3-5'} business days</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div className="mt-12 flex justify-between pt-8 border-t border-[var(--color-border)]">
                                        <Button variant="ghost" onClick={() => setCurrentStep('payment')} className="font-bold text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]">
                                            <ArrowLeft size={18} className="mr-2" />
                                            Back to Payment
                                        </Button>
                                        <div className="lg:hidden">
                                             <Button onClick={handleSubmit} disabled={form.processing} size="lg" className="rounded-xl px-10 font-bold h-12 shadow-lg">
                                                {form.processing ? 'Processing...' : 'Place Order'}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="p-6 bg-blue-50 border border-blue-100 rounded-3xl flex gap-4">
                                    <div className="w-10 h-10 rounded-full bg-white flex items-center justify-center text-blue-600 shadow-sm shrink-0">
                                        <ShieldCheck size={20} />
                                    </div>
                                    <div className="text-xs text-blue-800 leading-relaxed">
                                        <p className="font-bold mb-1">Your purchase is protected</p>
                                        <p>By clicking "Place Order", you agree to our Terms of Service and Privacy Policy. All transactions are secure and encrypted.</p>
                                    </div>
                                </div>
                            </motion.div>
                        )}
                    </form>
                </div>

                {/* Right Column: Order Summary */}
                <div className="lg:col-span-4">
                    {renderSummary()}
                </div>
            </div>
        </CheckoutLayout>
    );
}

// Simple ShieldCheck component as it wasn't imported
function ShieldCheck({ size = 20, className = "" }) {
    return (
        <svg 
            xmlns="http://www.w3.org/2000/svg" 
            width={size} 
            height={size} 
            viewBox="0 0 24 24" 
            fill="none" 
            stroke="currentColor" 
            strokeWidth="2" 
            strokeLinecap="round" 
            strokeLinejoin="round" 
            className={className}
        >
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}
