import { Head } from '@inertiajs/react';
import { CheckCircle, Download, ShoppingBag } from 'lucide-react';
import { useEffect } from 'react';

type OrderItem = { id: number; product_name: string; price: number; quantity: number; subtotal: number };
type Order = {
    id: number; order_number: string; status: string; payment_status: string; payment_method: string;
    subtotal: number; shipping_cost: number; discount: number; total: number;
    shipping_address: Record<string, string>;
    items: OrderItem[];
};

type Props = { order: Order; guestToken: string; siteName: string; siteLogo: string | null };

export default function LandingConfirmation({ order, guestToken, siteName, siteLogo }: Props) {
    const address = order.shipping_address;
    const isPaid = order.payment_status === 'paid';
    const invoiceUrl = route('lp.invoice', guestToken);

    // Auto-download invoice when online payment is confirmed
    useEffect(() => {
        if (isPaid) {
            const link = document.createElement('a');
            link.href = invoiceUrl;
            link.download = `invoice-${order.order_number}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <>
            <Head title={`Order Confirmed — ${siteName}`} />
            <div className="min-h-screen bg-slate-50 flex flex-col">
                <header className="border-b border-slate-100 bg-white px-4 py-3">
                    <div className="mx-auto flex max-w-lg items-center gap-2">
                        {siteLogo ? (
                            <img src={siteLogo} alt={siteName} className="h-8 w-auto object-contain" />
                        ) : (
                            <span className="flex size-8 items-center justify-center rounded-lg bg-[#c8102e]">
                                <ShoppingBag className="size-4 text-white" />
                            </span>
                        )}
                        <span className="text-sm font-bold text-slate-800">{siteName}</span>
                    </div>
                </header>

                <main className="flex-1 px-4 py-10">
                    <div className="mx-auto max-w-md space-y-5">
                        {/* Success banner */}
                        <div className="rounded-2xl bg-emerald-50 border border-emerald-100 p-6 text-center space-y-2">
                            <CheckCircle className="mx-auto size-12 text-emerald-500" />
                            <h1 className="text-xl font-bold text-emerald-900">Order Placed!</h1>
                            <p className="text-sm text-emerald-700">Thank you, {address.name}! Your order has been received.</p>
                            <p className="text-xs font-mono font-semibold text-emerald-800 bg-emerald-100 rounded-lg px-3 py-1.5 inline-block">{order.order_number}</p>
                        </div>

                        {/* Payment status */}
                        <div className={`rounded-xl px-4 py-3 text-sm font-medium ${isPaid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                            {isPaid ? '✓ Payment confirmed' : '⏳ Cash on Delivery — pay when you receive your order'}
                        </div>

                        {/* Order details */}
                        <div className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-5 space-y-4">
                            <h2 className="text-sm font-bold text-slate-800">Order Summary</h2>
                            <div className="space-y-2">
                                {order.items.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between text-sm">
                                        <span className="text-slate-700">{item.product_name} <span className="text-slate-400">×{item.quantity}</span></span>
                                        <span className="font-medium">৳{item.subtotal.toLocaleString()}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="border-t border-slate-100 pt-2 space-y-1 text-sm">
                                <div className="flex justify-between text-slate-500"><span>Subtotal</span><span>৳{order.subtotal.toLocaleString()}</span></div>
                                <div className="flex justify-between text-slate-500"><span>Delivery</span><span>{order.shipping_cost === 0 ? 'Free' : `৳${order.shipping_cost}`}</span></div>
                                <div className="flex justify-between font-bold text-slate-900 text-base pt-1 border-t border-slate-100"><span>Total</span><span>৳{order.total.toLocaleString()}</span></div>
                            </div>
                        </div>

                        {/* Delivery address */}
                        <div className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-5 space-y-2">
                            <h2 className="text-sm font-bold text-slate-800">Delivery Address</h2>
                            <div className="text-sm text-slate-600 space-y-0.5">
                                <p className="font-medium text-slate-800">{address.name}</p>
                                <p>{address.phone}</p>
                                <p>{address.address_line1}{address.address_line2 ? `, ${address.address_line2}` : ''}</p>
                                <p>{address.city}, {address.state}</p>
                                <p>Bangladesh</p>
                            </div>
                        </div>

                        {/* Invoice download — only for completed online payments */}
                        {isPaid && (
                            <>
                                <a
                                    href={invoiceUrl}
                                    className="flex items-center justify-center gap-2 w-full rounded-xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
                                >
                                    <Download className="size-4" />
                                    Download Invoice (PDF)
                                </a>
                                <p className="text-center text-xs text-slate-400">
                                    Your invoice download has started automatically. Use the button above if it didn't.
                                </p>
                            </>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
