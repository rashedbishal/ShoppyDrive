import { Head } from '@inertiajs/react';
import { CheckCircle, Download, Mail, Phone } from 'lucide-react';
import { useEffect } from 'react';
import LanguageSwitch from '@/components/landing/LanguageSwitch';
import { SiteLogo } from '@/components/shared/SiteLogo';
import {
    BANGLA_FONT_CSS,
    BANGLA_FONT_FAMILY,
    useLandingLanguage,
} from '@/lib/landing-i18n';
import type { LandingLanguageSettings } from '@/lib/landing-i18n';
import { trackMetaPurchase } from '@/lib/meta-tracking';

type OrderItem = {
    id: number;
    product_name: string;
    variant_label?: string | null;
    combo_name?: string | null;
    price: number;
    quantity: number;
    subtotal: number;
    /** DISC-65: Bangla labels from the landing page, when it has Bangla on. */
    bn?: {
        product_name: string | null;
        variant_label: string | null;
        combo_name: string | null;
    } | null;
};
type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    payment_method: string;
    subtotal: number;
    shipping_cost: number;
    discount: number;
    total: number;
    shipping_address: Record<string, string>;
    items: OrderItem[];
};

type Props = {
    order: Order;
    guestToken: string;
    support?: { phone: string | null; email: string | null };
    siteName: string;
    siteLogo: string | null;
    /** DISC-65: the landing page's language settings. */
    language?: LandingLanguageSettings | null;
};

export default function LandingConfirmation({
    order,
    guestToken,
    support,
    siteName,
    siteLogo,
    language,
}: Props) {
    const text = useLandingLanguage(language);
    const taka = (amount: number): string =>
        `৳${text.num(amount.toLocaleString())}`;
    const address = order.shipping_address;
    const isPaid = order.payment_status === 'paid';
    const invoiceUrl = route('lp.invoice', guestToken);

    // Meta Purchase (browser half) for a landing-page order: same
    // `order-<number>` event id the server sends from the order itself.
    useEffect(() => {
        if (order.status !== 'cancelled') {
            trackMetaPurchase({
                orderNumber: order.order_number,
                value: order.total,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

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
            <Head title={`${text.t('order_confirmed_title')} — ${siteName}`}>
                {text.banglaEnabled && <style>{BANGLA_FONT_CSS}</style>}
            </Head>
            <div
                className="flex min-h-screen flex-col bg-slate-50"
                lang={text.banglaEnabled ? text.lang : undefined}
                style={
                    text.isBangla
                        ? { fontFamily: BANGLA_FONT_FAMILY }
                        : undefined
                }
            >
                <header className="border-b border-slate-100 bg-white px-4 py-3">
                    <div
                        className={
                            text.banglaEnabled
                                ? 'mx-auto flex max-w-lg items-center justify-between gap-3'
                                : 'mx-auto flex max-w-lg items-center'
                        }
                    >
                        <SiteLogo
                            src={siteLogo}
                            name={siteName}
                            className="h-9"
                        />
                        <LanguageSwitch text={text} tone="light" />
                    </div>
                </header>

                <main className="flex-1 px-4 py-10">
                    <div className="mx-auto max-w-md space-y-5">
                        {/* Success banner */}
                        <div className="space-y-2 rounded-2xl border border-emerald-100 bg-emerald-50 p-6 text-center">
                            <CheckCircle className="mx-auto size-12 text-emerald-500" />
                            <h1 className="text-xl font-bold text-emerald-900">
                                {text.t('order_placed')}
                            </h1>
                            <p className="text-sm text-emerald-700">
                                {text.t('thank_you', { name: address.name })}
                            </p>
                            <p className="inline-block rounded-lg bg-emerald-100 px-3 py-1.5 font-mono text-xs font-semibold text-emerald-800">
                                {order.order_number}
                            </p>
                        </div>

                        {/* Payment status */}
                        <div
                            className={`rounded-xl px-4 py-3 text-sm font-medium ${isPaid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}
                        >
                            {isPaid
                                ? text.t('payment_confirmed')
                                : text.t('cod_pay_on_receive')}
                        </div>

                        {/* Order details */}
                        <div className="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                            <h2 className="text-sm font-bold text-slate-800">
                                {text.t('order_summary')}
                            </h2>
                            <div className="space-y-2">
                                {order.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <span className="text-slate-700">
                                            {item.combo_name && (
                                                <span className="mr-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">
                                                    {text.pick(
                                                        item.combo_name,
                                                        item.bn?.combo_name,
                                                    )}
                                                </span>
                                            )}
                                            {text.pick(
                                                item.product_name,
                                                item.bn?.product_name,
                                            )}
                                            {item.variant_label && (
                                                <span className="text-slate-600">
                                                    {' '}
                                                    (
                                                    {text.pick(
                                                        item.variant_label,
                                                        item.bn?.variant_label,
                                                    )}
                                                    )
                                                </span>
                                            )}{' '}
                                            <span className="text-slate-600">
                                                ×{text.num(item.quantity)}
                                            </span>
                                        </span>
                                        <span className="font-medium">
                                            {taka(item.subtotal)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <div className="space-y-1 border-t border-slate-100 pt-2 text-sm">
                                <div className="flex justify-between text-slate-600">
                                    <span>{text.t('subtotal')}</span>
                                    <span>{taka(order.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>{text.t('delivery')}</span>
                                    <span>
                                        {order.shipping_cost === 0
                                            ? text.t('free')
                                            : `৳${text.num(order.shipping_cost)}`}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t border-slate-100 pt-1 text-base font-bold text-slate-900">
                                    <span>{text.t('total')}</span>
                                    <span>{taka(order.total)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Delivery address */}
                        <div className="space-y-2 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                            <h2 className="text-sm font-bold text-slate-800">
                                {text.t('delivery_address')}
                            </h2>
                            <div className="space-y-0.5 text-sm text-slate-600">
                                <p className="font-medium text-slate-800">
                                    {address.name}
                                </p>
                                <p>{address.phone}</p>
                                <p>
                                    {address.address_line1}
                                    {address.address_line2
                                        ? `, ${address.address_line2}`
                                        : ''}
                                </p>
                                <p>
                                    {text.city(address.city)},{' '}
                                    {text.district(address.state)}
                                </p>
                                <p>{text.t('bangladesh')}</p>
                            </div>
                        </div>

                        {/* Support contact */}
                        {(support?.phone || support?.email) && (
                            <div className="space-y-3 rounded-2xl bg-[#0b1020] p-5 text-center text-white">
                                <h2 className="text-sm font-bold">
                                    {text.t('need_help')}
                                </h2>
                                <div className="flex flex-col items-center gap-2 text-sm sm:flex-row sm:justify-center sm:gap-6">
                                    {support.phone && (
                                        <a
                                            href={`tel:${support.phone}`}
                                            className="inline-flex items-center gap-2 font-semibold text-amber-300 hover:text-amber-200"
                                        >
                                            <Phone className="size-4" />
                                            {support.phone}
                                        </a>
                                    )}
                                    {support.email && (
                                        <a
                                            href={`mailto:${support.email}`}
                                            className="inline-flex items-center gap-2 text-white/80 hover:text-white"
                                        >
                                            <Mail className="size-4" />
                                            {support.email}
                                        </a>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Invoice download — only for completed online payments */}
                        {isPaid && (
                            <>
                                <a
                                    href={invoiceUrl}
                                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                                >
                                    <Download className="size-4" />
                                    {text.t('download_invoice')}
                                </a>
                                <p className="text-center text-xs text-slate-600">
                                    {text.t('invoice_started')}
                                </p>
                            </>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
