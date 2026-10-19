import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, ChevronDown, ChevronUp, Mail, MapPin, Phone, Play, ShoppingBag, Star, Truck } from 'lucide-react';
import SocialChatWidget from '@/components/layout/SocialChatWidget';
import { Select } from '@/components/ui/select';
import type { SharedPageProps } from '@/types';
import { useEffect, useState } from 'react';

type ProductMedia = { id: number; path: string; type: 'image' | 'video'; is_primary: boolean };
type Sections = Record<string, any>;
type PendingPayment = { token: string; amount: number; order_number: string; provider: 'bkash' | 'nagad' };

type Props = {
    page: { id: number; title: string; slug: string; sections: Sections };
    product: {
        id: number; name: string; description: string | null; short_description: string | null;
        price: number; compare_price: number | null; free_delivery: boolean; free_delivery_min_qty: number | null;
        media: ProductMedia[];
    };
    bkashNumber: string | null;
    nagadNumber: string | null;
    siteName: string;
    siteLogo: string | null;
    pendingPayment: PendingPayment | null;
};

const BD_DISTRICTS = [
    'Bagerhat','Bandarban','Barguna','Barisal','Bhola','Bogura','Brahmanbaria','Chandpur',
    'Chapainawabganj','Chattogram','Chuadanga','Cox\'s Bazar','Cumilla','Dhaka','Dinajpur',
    'Faridpur','Feni','Gaibandha','Gazipur','Gopalganj','Habiganj','Jamalpur','Jashore',
    'Jhalokathi','Jhenaidah','Joypurhat','Khagrachhari','Khulna','Kishoreganj','Kurigram',
    'Kushtia','Lakshmipur','Lalmonirhat','Madaripur','Magura','Manikganj','Meherpur',
    'Moulvibazar','Munshiganj','Mymensingh','Naogaon','Narail','Narayanganj','Narsingdi',
    'Natore','Netrokona','Nilphamari','Noakhali','Pabna','Panchagarh','Patuakhali',
    'Pirojpur','Rajbari','Rajshahi','Rangamati','Rangpur','Satkhira','Shariatpur',
    'Sherpur','Sirajganj','Sunamganj','Sylhet','Tangail','Thakurgaon',
];

function calcShipping(state: string, city: string, freeDelivery: boolean): number {
    if (freeDelivery) return 0;
    if (state === 'Dhaka' && city === 'Inside Dhaka City Corporation') return 60;
    return 120;
}

function StarRating({ rating }: { rating: number }) {
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map((s) => (
                <Star key={s} className={`size-4 ${s <= rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200'}`} />
            ))}
        </div>
    );
}

function InlinePayment({ pending, slug, bkashNumber, nagadNumber }: { pending: PendingPayment; slug: string; bkashNumber: string | null; nagadNumber: string | null }) {
    const { errors } = usePage<any>().props as { errors: Record<string, string> };
    const providerNumber = pending.provider === 'bkash' ? bkashNumber : nagadNumber;
    const providerLabel = pending.provider === 'bkash' ? 'Bkash' : 'Nagad';
    const providerColor = pending.provider === 'bkash' ? '#e2136e' : '#f4821f';

    const { data, setData, post, processing } = useForm({ transaction_id: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('lp.payment.submit', { slug, token: pending.token }));
    };

    return (
        <div className="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-6 space-y-5">
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    onClick={() => router.get(route('lp.show', slug))}
                    className="flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="size-3.5" /> Back to order form
                </button>
            </div>

            <div className="text-center">
                <div className="inline-flex size-14 items-center justify-center rounded-full mb-3" style={{ backgroundColor: providerColor + '20' }}>
                    <span className="text-xl font-extrabold" style={{ color: providerColor }}>{providerLabel[0]}</span>
                </div>
                <h2 className="text-lg font-bold text-slate-900">Complete Payment via {providerLabel}</h2>
                <p className="text-xs text-slate-400 mt-0.5">Order #{pending.order_number}</p>
            </div>

            <div className="rounded-xl p-4 text-center space-y-1" style={{ backgroundColor: providerColor + '10', border: `1px solid ${providerColor}30` }}>
                <p className="text-xs text-slate-500">Send exactly</p>
                <p className="text-3xl font-extrabold text-slate-900">৳{pending.amount.toLocaleString()}</p>
                <p className="text-xs text-slate-500 mt-1">to this {providerLabel} number</p>
                <p className="text-xl font-bold" style={{ color: providerColor }}>{providerNumber}</p>
                <p className="text-[11px] text-slate-400">Send Money → Personal</p>
            </div>

            <ol className="space-y-1.5 text-sm text-slate-600">
                <li className="flex gap-2"><span className="font-bold text-slate-800 w-4 shrink-0">1.</span> Open your {providerLabel} app</li>
                <li className="flex gap-2"><span className="font-bold text-slate-800 w-4 shrink-0">2.</span> Go to <strong>Send Money</strong> and enter the number above</li>
                <li className="flex gap-2"><span className="font-bold text-slate-800 w-4 shrink-0">3.</span> Send exactly <strong>৳{pending.amount.toLocaleString()}</strong></li>
                <li className="flex gap-2"><span className="font-bold text-slate-800 w-4 shrink-0">4.</span> Copy the <strong>Transaction ID</strong> from the confirmation SMS</li>
                <li className="flex gap-2"><span className="font-bold text-slate-800 w-4 shrink-0">5.</span> Paste it below and click Confirm</li>
            </ol>

            <form onSubmit={handleSubmit} className="space-y-3">
                <div>
                    <input
                        className="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-mono uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-[#c8102e]/30 focus:border-[#c8102e]"
                        value={data.transaction_id}
                        onChange={(e) => setData('transaction_id', e.target.value)}
                        placeholder="Transaction ID from SMS…"
                        autoFocus
                    />
                    {errors.transaction_id && (
                        <p className="mt-1 text-xs font-medium text-red-600">{errors.transaction_id}</p>
                    )}
                </div>
                <button
                    type="submit"
                    disabled={processing || !data.transaction_id}
                    className="w-full rounded-xl py-3 text-sm font-bold text-white disabled:opacity-60 transition-colors"
                    style={{ backgroundColor: providerColor }}
                >
                    {processing ? 'Verifying…' : 'Confirm Payment'}
                </button>
            </form>
        </div>
    );
}

function OrderForm({ product, slug, bkashNumber, nagadNumber }: { product: Props['product']; slug: string; bkashNumber: string | null; nagadNumber: string | null }) {
    const [qty, setQty] = useState(1);
    const [state, setState] = useState('');
    const [city, setCity] = useState('');
    const [paymentStep, setPaymentStep] = useState(false);

    const shipping = calcShipping(state, city, product.free_delivery);
    const subtotal = product.price * qty;
    const total = subtotal + shipping;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        phone: '',
        email: '',
        address_line1: '',
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
        quantity: 1,
        payment_method: 'cod' as 'cod' | 'mobile_banking',
        provider: '' as '' | 'bkash' | 'nagad',
    });

    const updateState = (v: string) => { setState(v); setData('state', v); };
    const updateCity = (v: string) => { setCity(v); setData('city', v); };
    const updateQty = (v: number) => { const q = Math.max(1, v); setQty(q); setData('quantity', q); };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('lp.order', slug));
    };

    const inputClass = 'h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#c8102e]/30 focus:border-[#c8102e]';

    return (
        <div className="rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-6 space-y-4">
            <div className="space-y-1">
                <div className="flex items-baseline gap-2">
                    <span className="text-2xl font-bold text-slate-900">৳{product.price.toLocaleString()}</span>
                    {product.compare_price && product.compare_price > product.price && (
                        <span className="text-sm text-slate-400 line-through">৳{product.compare_price.toLocaleString()}</span>
                    )}
                </div>
                <p className="text-xs text-slate-500">
                    {product.free_delivery ? '🚚 Free delivery' : `Delivery: ৳60 (Dhaka) / ৳120 (Outside)`}
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-3">
                {/* Quantity */}
                <div className="flex items-center gap-3">
                    <span className="text-sm font-medium text-slate-700 w-16">Qty</span>
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => updateQty(qty - 1)} className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">−</button>
                        <span className="w-8 text-center text-sm font-semibold">{qty}</span>
                        <button type="button" onClick={() => updateQty(qty + 1)} className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">+</button>
                    </div>
                </div>

                {/* Personal info */}
                <div>
                    <input className={inputClass} placeholder="Full Name *" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    {errors.name && <p className="mt-0.5 text-xs text-red-500">{errors.name}</p>}
                </div>
                <div>
                    <input className={inputClass} placeholder="Phone Number *" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    {errors.phone && <p className="mt-0.5 text-xs text-red-500">{errors.phone}</p>}
                </div>
                <input className={inputClass} placeholder="Email (optional)" value={data.email} onChange={(e) => setData('email', e.target.value)} />

                {/* Address */}
                <input className={inputClass} placeholder="Address *" value={data.address_line1} onChange={(e) => setData('address_line1', e.target.value)} />
                {errors.address_line1 && <p className="-mt-2 text-xs text-red-500">{errors.address_line1}</p>}
                <input className={inputClass} placeholder="Area / Landmark (optional)" value={data.address_line2} onChange={(e) => setData('address_line2', e.target.value)} />

                <div className="grid grid-cols-2 gap-2">
                    <div>
                        <Select selectClassName={inputClass} value={data.state} onChange={(e) => updateState(e.target.value)}>
                            <option value="">District *</option>
                            {BD_DISTRICTS.map((d) => <option key={d} value={d}>{d}</option>)}
                        </Select>
                        {errors.state && <p className="mt-0.5 text-xs text-red-500">{errors.state}</p>}
                    </div>
                    <div>
                        {data.state === 'Dhaka' ? (
                            <Select selectClassName={inputClass} value={data.city} onChange={(e) => updateCity(e.target.value)}>
                                <option value="">Area *</option>
                                <option value="Inside Dhaka City Corporation">Inside Dhaka City</option>
                                <option value="Outside Dhaka City Corporation">Outside Dhaka City</option>
                            </Select>
                        ) : (
                            <input className={inputClass} placeholder="City / Upazila *" value={data.city} onChange={(e) => updateCity(e.target.value)} />
                        )}
                        {errors.city && <p className="mt-0.5 text-xs text-red-500">{errors.city}</p>}
                    </div>
                </div>

                {/* Payment method */}
                <div className="space-y-2">
                    <p className="text-sm font-medium text-slate-700">Payment Method</p>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="pm" value="cod" checked={data.payment_method === 'cod'} onChange={() => setData('payment_method', 'cod')} className="accent-[#c8102e]" />
                        <span className="text-sm">Cash on Delivery</span>
                    </label>
                    {(bkashNumber || nagadNumber) && (
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="pm" value="mobile_banking" checked={data.payment_method === 'mobile_banking'} onChange={() => setData('payment_method', 'mobile_banking')} className="accent-[#c8102e]" />
                            <span className="text-sm">Mobile Banking (Bkash / Nagad)</span>
                        </label>
                    )}
                    {data.payment_method === 'mobile_banking' && (
                        <div className="flex gap-2 pl-6">
                            {bkashNumber && (
                                <label className="flex items-center gap-1 cursor-pointer">
                                    <input type="radio" name="provider" value="bkash" checked={data.provider === 'bkash'} onChange={() => setData('provider', 'bkash')} className="[--control-color:var(--color-pink-600)]" />
                                    <span className="text-sm font-semibold text-pink-700">Bkash</span>
                                </label>
                            )}
                            {nagadNumber && (
                                <label className="flex items-center gap-1 cursor-pointer">
                                    <input type="radio" name="provider" value="nagad" checked={data.provider === 'nagad'} onChange={() => setData('provider', 'nagad')} className="[--control-color:var(--color-orange-600)]" />
                                    <span className="text-sm font-semibold text-orange-700">Nagad</span>
                                </label>
                            )}
                            {errors.provider && <p className="text-xs text-red-500">{errors.provider}</p>}
                        </div>
                    )}
                </div>

                {/* Order summary */}
                {data.state && data.city && (
                    <div className="rounded-xl bg-slate-50 p-3 text-sm space-y-1">
                        <div className="flex justify-between text-slate-600"><span>Subtotal ({qty}×)</span><span>৳{subtotal.toLocaleString()}</span></div>
                        <div className="flex justify-between text-slate-600"><span>Delivery</span><span>{shipping === 0 ? 'Free' : `৳${shipping}`}</span></div>
                        <div className="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-1"><span>Total</span><span>৳{total.toLocaleString()}</span></div>
                    </div>
                )}

                <button type="submit" disabled={processing} className="w-full rounded-xl bg-[#c8102e] py-3 text-sm font-bold text-white hover:bg-[#a50d25] disabled:opacity-60 transition-colors">
                    {processing ? 'Placing Order…' : (data.sections?.hero?.cta_text ?? 'Place Order')}
                </button>
            </form>
        </div>
    );
}

export default function LandingPageShow({ page, product, bkashNumber, nagadNumber, siteName, siteLogo, pendingPayment }: Props) {
    const sec = page.sections;
    const { settings } = usePage<SharedPageProps>().props;

    // Scroll payment section into view when pending payment is active
    useEffect(() => {
        if (pendingPayment) {
            setTimeout(() => {
                document.getElementById('order-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }, [!!pendingPayment]);
    const [openFaq, setOpenFaq] = useState<number | null>(null);
    const [activeMediaIdx, setActiveMediaIdx] = useState(0);
    const allMedia = product.media ?? [];
    const primaryIdx = allMedia.findIndex((m) => m.is_primary && m.type === 'image');
    const orderedMedia = primaryIdx >= 0
        ? [allMedia[primaryIdx], ...allMedia.filter((_, i) => i !== primaryIdx)]
        : allMedia;
    const heroImage = orderedMedia.find((m) => m.type === 'image');

    return (
        <>
            <Head title={page.title}>
                <style>{`
                    @font-face {
                        font-family: 'Kalpurush';
                        src: url('/fonts/Kalpurush.ttf') format('truetype');
                        font-weight: normal;
                        font-style: normal;
                        font-display: swap;
                        unicode-range: U+0980-U+09FF;
                    }
                `}</style>
            </Head>
            <div className="min-h-screen bg-white" style={{ fontFamily: "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif, 'Kalpurush'" }}>
                {/* Minimal header */}
                <header className="sticky top-0 z-30 border-b border-slate-100 bg-white/95 backdrop-blur px-4 py-3">
                    <div className="mx-auto flex max-w-6xl items-center justify-between">
                        <div className="flex items-center gap-2">
                            {siteLogo ? (
                                <img src={siteLogo} alt={siteName} className="h-14 w-auto object-contain" />
                            ) : (
                                <span className="flex size-8 items-center justify-center rounded-lg bg-[#c8102e]">
                                    <ShoppingBag className="size-4 text-white" />
                                </span>
                            )}
                            <span className="text-sm font-bold text-slate-800">{siteName}</span>
                        </div>
                        <a href="#order-form" className="rounded-lg bg-[#c8102e] px-4 py-1.5 text-xs font-bold text-white hover:bg-[#a50d25] transition-colors">
                            {sec.hero?.cta_text || 'Order Now'}
                        </a>
                    </div>
                </header>

                {/* Hero */}
                {sec.hero?.enabled !== false && (
                    <section style={{ backgroundColor: sec.hero?.bg_color || '#0f172a' }} className="py-12 px-4">
                        <div className="mx-auto max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                            <div className="space-y-4 text-white order-2 lg:order-1">
                                <h1 className="text-3xl lg:text-4xl font-extrabold leading-tight">
                                    {sec.hero?.headline || product.name}
                                </h1>
                                {sec.hero?.subheadline && (
                                    <p className="text-lg text-white/80">{sec.hero.subheadline}</p>
                                )}
                                {product.short_description && (
                                    <p className="text-white/70 text-sm leading-relaxed">{product.short_description}</p>
                                )}
                                <div className="flex items-center gap-3 pt-2">
                                    <span className="text-3xl font-bold">৳{product.price.toLocaleString()}</span>
                                    {product.compare_price && product.compare_price > product.price && (
                                        <span className="text-lg text-white/50 line-through">৳{product.compare_price.toLocaleString()}</span>
                                    )}
                                    {product.compare_price && product.compare_price > product.price && (
                                        <span className="rounded-full bg-red-500 px-2.5 py-0.5 text-xs font-bold text-white">
                                            {Math.round((1 - product.price / product.compare_price) * 100)}% OFF
                                        </span>
                                    )}
                                </div>
                                <a href="#order-form" className="inline-block rounded-xl bg-white text-[#c8102e] px-6 py-3 font-bold hover:bg-slate-100 transition-colors">
                                    {sec.hero?.cta_text || 'Order Now'}
                                </a>
                            </div>
                            {heroImage && (
                                <div className="order-1 lg:order-2">
                                    <img src={heroImage.path} alt={product.name} className="w-full max-h-96 object-contain rounded-2xl" />
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* Benefits */}
                {sec.benefits?.enabled && (
                    <section className="py-12 px-4 bg-slate-50">
                        <div className="mx-auto max-w-3xl text-center">
                            <h2 className="text-2xl font-bold text-slate-900 mb-8">{sec.benefits.title}</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                {(sec.benefits.items as string[]).map((item, i) => (
                                    <div key={i} className="flex items-start gap-3 rounded-xl bg-white p-4 shadow-sm text-left">
                                        <CheckCircle className="size-5 text-[#c8102e] shrink-0 mt-0.5" />
                                        <span className="text-sm text-slate-700">{item}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {/* Main content + Order form */}
                <section id="order-form" className="py-12 px-4">
                    <div className="mx-auto max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
                        <div className="space-y-8">
                            {/* Gallery */}
                            {sec.gallery?.enabled && orderedMedia.length > 0 && (
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900 mb-4">{sec.gallery.title}</h2>
                                    <div className="space-y-3">
                                        {/* Active media viewer */}
                                        {orderedMedia[activeMediaIdx]?.type === 'video' ? (
                                            <video
                                                key={orderedMedia[activeMediaIdx].path}
                                                src={orderedMedia[activeMediaIdx].path}
                                                controls
                                                className="w-full rounded-2xl max-h-80 bg-slate-900"
                                            />
                                        ) : (
                                            <img
                                                src={orderedMedia[activeMediaIdx]?.path}
                                                alt={product.name}
                                                className="w-full rounded-2xl object-contain max-h-80 bg-slate-50"
                                            />
                                        )}

                                        {/* Thumbnail strip */}
                                        {orderedMedia.length > 1 && (
                                            <div className="flex gap-2 overflow-x-auto pb-1">
                                                {orderedMedia.map((item, i) => (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() => setActiveMediaIdx(i)}
                                                        className={`relative shrink-0 size-16 rounded-xl overflow-hidden border-2 transition-colors bg-slate-100 ${i === activeMediaIdx ? 'border-[#c8102e]' : 'border-transparent'}`}
                                                    >
                                                        {item.type === 'video' ? (
                                                            <>
                                                                <video src={item.path} className="size-full object-cover" muted />
                                                                <div className="absolute inset-0 flex items-center justify-center bg-black/30">
                                                                    <Play className="size-5 fill-white text-white" />
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <img src={item.path} alt="" className="size-full object-cover" />
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Description */}
                            {sec.description?.enabled && (
                                <div>
                                    <h2 className="text-xl font-bold text-slate-900 mb-3 whitespace-pre-line">{sec.description.title}</h2>
                                    {sec.description.use_product_description ? (
                                        <div className="prose prose-sm max-w-none text-slate-600" dangerouslySetInnerHTML={{ __html: product.description ?? '' }} />
                                    ) : (
                                        <div className="prose prose-sm max-w-none text-slate-600" dangerouslySetInnerHTML={{ __html: sec.description.custom_content }} />
                                    )}
                                </div>
                            )}

                            {/* Delivery info */}
                            <div className="rounded-xl border border-slate-100 p-4 space-y-2">
                                <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <Truck className="size-4 text-[#c8102e]" /> Delivery Info
                                </div>
                                {product.free_delivery ? (
                                    <p className="text-sm text-emerald-600 font-medium">✓ Free delivery on this product</p>
                                ) : (
                                    <div className="text-sm text-slate-600 space-y-1">
                                        <p>Inside Dhaka City Corporation: <strong>৳60</strong></p>
                                        <p>Outside Dhaka / Rest of Bangladesh: <strong>৳120</strong></p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Order form / inline payment */}
                        <div className="lg:sticky lg:top-20">
                            {pendingPayment ? (
                                <InlinePayment
                                    pending={pendingPayment}
                                    slug={page.slug}
                                    bkashNumber={bkashNumber}
                                    nagadNumber={nagadNumber}
                                />
                            ) : (
                                <OrderForm product={product} slug={page.slug} bkashNumber={bkashNumber} nagadNumber={nagadNumber} />
                            )}
                        </div>
                    </div>
                </section>

                {/* Testimonials */}
                {sec.testimonials?.enabled && sec.testimonials.items?.length > 0 && (
                    <section className="py-12 px-4 bg-slate-50">
                        <div className="mx-auto max-w-4xl">
                            <h2 className="text-2xl font-bold text-slate-900 mb-8 text-center">{sec.testimonials.title}</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                {sec.testimonials.items.map((item: any, i: number) => item.name && (
                                    <div key={i} className="rounded-2xl bg-white p-5 shadow-sm">
                                        <StarRating rating={item.rating} />
                                        <p className="mt-2 text-sm text-slate-600 leading-relaxed">"{item.text}"</p>
                                        <p className="mt-3 text-xs font-semibold text-slate-800">— {item.name}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {/* FAQ */}
                {sec.faq?.enabled && sec.faq.items?.length > 0 && (
                    <section className="py-12 px-4">
                        <div className="mx-auto max-w-2xl">
                            <h2 className="text-2xl font-bold text-slate-900 mb-6 text-center">{sec.faq.title}</h2>
                            <div className="space-y-2">
                                {sec.faq.items.map((item: any, i: number) => item.question && (
                                    <div key={i} className="rounded-xl border border-slate-100 overflow-hidden">
                                        <button type="button" onClick={() => setOpenFaq(openFaq === i ? null : i)} className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-slate-800 hover:bg-slate-50">
                                            {item.question}
                                            {openFaq === i ? <ChevronUp className="size-4 shrink-0 text-slate-400" /> : <ChevronDown className="size-4 shrink-0 text-slate-400" />}
                                        </button>
                                        {openFaq === i && (
                                            <div className="px-4 pb-3 text-sm text-slate-600 leading-relaxed">{item.answer}</div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {/* Footer */}
                {sec.footer?.enabled && (
                    <footer className="bg-slate-900 pt-10 pb-6 px-4">
                        <div className="mx-auto max-w-6xl">
                            {/* Top row: brand + contact */}
                            <div className="flex flex-col items-center gap-8 sm:flex-row sm:items-start sm:justify-between">
                                {/* Brand */}
                                <div className="flex flex-col items-center gap-3 sm:items-start">
                                    <div className="flex items-center gap-2">
                                        {siteLogo ? (
                                            <img src={siteLogo} alt={siteName} className="h-14 w-auto object-contain" />
                                        ) : (
                                            <ShoppingBag className="size-6 text-white" />
                                        )}
                                        <span className="text-base font-bold text-white">{siteName}</span>
                                    </div>
                                    {sec.footer.text && (
                                        <p className="text-xs text-slate-400 whitespace-pre-line text-center sm:text-left max-w-xs">
                                            {sec.footer.text}
                                        </p>
                                    )}
                                </div>

                                {/* Contact info */}
                                <div className="flex flex-col items-center gap-3 sm:items-end">
                                    <p className="text-xs font-semibold uppercase tracking-widest text-slate-500">Contact Us</p>
                                    {settings.contact_phone && (
                                        <a href={`tel:${settings.contact_phone}`} className="flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors">
                                            <Phone size={14} className="text-slate-500 shrink-0" />
                                            {settings.contact_phone}
                                        </a>
                                    )}
                                    {settings.contact_email && (
                                        <a href={`mailto:${settings.contact_email}`} className="flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors">
                                            <Mail size={14} className="text-slate-500 shrink-0" />
                                            {settings.contact_email}
                                        </a>
                                    )}
                                    {settings.contact_address && (
                                        <p className="flex items-start gap-2 text-sm text-slate-300 text-right max-w-xs">
                                            <MapPin size={14} className="text-slate-500 shrink-0 mt-0.5" />
                                            {settings.contact_address}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Divider + copyright */}
                            <div className="mt-8 border-t border-slate-800 pt-5 text-center">
                                <p className="text-xs text-slate-500">
                                    &copy; {new Date().getFullYear()} {siteName}. All rights reserved.
                                </p>
                            </div>
                        </div>
                    </footer>
                )}
            </div>

            <SocialChatWidget />
        </>
    );
}
