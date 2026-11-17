import { Head, useForm, usePage } from '@inertiajs/react';

import LanguageSwitch from '@/components/landing/LanguageSwitch';
import { SiteLogo } from '@/components/shared/SiteLogo';
import {
    BANGLA_FONT_CSS,
    BANGLA_FONT_FAMILY,
    useLandingLanguage,
} from '@/lib/landing-i18n';
import type { LandingLanguageSettings } from '@/lib/landing-i18n';

type Props = {
    token: string;
    slug: string;
    amount: number;
    orderNumber: string;
    provider: 'bkash' | 'nagad';
    bkashNumber: string | null;
    nagadNumber: string | null;
    siteName: string;
    siteLogo: string | null;
    /** DISC-65: the landing page's language settings. */
    language?: LandingLanguageSettings | null;
};

export default function LandingPayment({
    token,
    slug,
    amount,
    orderNumber,
    provider,
    bkashNumber,
    nagadNumber,
    siteName,
    siteLogo,
    language,
}: Props) {
    const text = useLandingLanguage(language);
    const providerNumber = provider === 'bkash' ? bkashNumber : nagadNumber;
    const providerLabel = text.t(provider === 'bkash' ? 'bkash' : 'nagad');
    const providerInitial = provider === 'bkash' ? 'B' : 'N';
    const amountText = text.isBangla
        ? `৳${text.num(amount.toLocaleString())}`
        : `৳${amount.toLocaleString()}`;
    const providerColor = provider === 'bkash' ? '#e2136e' : '#f4821f';

    const { errors: pageErrors } = usePage<any>().props as {
        errors: Record<string, string>;
    };
    const { data, setData, post, processing, errors, transform } = useForm({
        transaction_id: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // DISC-65: messages come back in the visitor's language.
        transform((formData) => ({ ...formData, lang: text.lang }));
        post(route('lp.payment.submit', { slug, token }));
    };

    return (
        <>
            <Head title={`${text.t('complete_payment_title')} — ${siteName}`}>
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
                    <div className="mx-auto max-w-md space-y-6">
                        <div className="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                            <div className="text-center">
                                <div
                                    className="mb-3 inline-flex size-14 items-center justify-center rounded-full"
                                    style={{
                                        backgroundColor: providerColor + '20',
                                    }}
                                >
                                    <span
                                        className="text-lg font-extrabold"
                                        style={{ color: providerColor }}
                                    >
                                        {providerInitial}
                                    </span>
                                </div>
                                <h1 className="text-lg font-bold text-slate-900">
                                    {text.t('pay_via', {
                                        provider: providerLabel,
                                    })}
                                </h1>
                                <p className="mt-0.5 text-sm text-slate-600">
                                    {text.t('order_number', {
                                        number: orderNumber,
                                    })}
                                </p>
                            </div>

                            <div
                                className="rounded-xl p-4 text-center"
                                style={{
                                    backgroundColor: providerColor + '10',
                                    borderColor: providerColor + '30',
                                    borderWidth: 1,
                                }}
                            >
                                <p className="mb-1 text-xs text-slate-600">
                                    {text.t('send_exactly')}
                                </p>
                                <p className="text-3xl font-extrabold text-slate-900">
                                    {amountText}
                                </p>
                                <p className="mt-2 text-xs text-slate-600">
                                    {text.t('to_this_number', {
                                        provider: providerLabel,
                                    })}
                                </p>
                                <p
                                    className="mt-1 text-xl font-bold"
                                    style={{ color: providerColor }}
                                >
                                    {providerNumber}
                                </p>
                                <p className="mt-2 text-xs text-slate-600">
                                    {text.t('send_money_personal')}
                                </p>
                            </div>

                            <ol className="space-y-2 text-sm text-slate-600">
                                <li className="flex gap-2">
                                    <span className="font-bold text-slate-800">
                                        {text.num(1)}.
                                    </span>{' '}
                                    {text.t('step_open_app', {
                                        provider: providerLabel,
                                    })}
                                </li>
                                <li className="flex gap-2">
                                    <span className="font-bold text-slate-800">
                                        {text.num(2)}.
                                    </span>{' '}
                                    <span>{text.rich('step_send_money')}</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="font-bold text-slate-800">
                                        {text.num(3)}.
                                    </span>{' '}
                                    <span>
                                        {text.rich('step_send_exactly', {
                                            amount: amountText,
                                        })}
                                    </span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="font-bold text-slate-800">
                                        {text.num(4)}.
                                    </span>{' '}
                                    <span>{text.rich('step_copy_trx')}</span>
                                </li>
                                <li className="flex gap-2">
                                    <span className="font-bold text-slate-800">
                                        {text.num(5)}.
                                    </span>{' '}
                                    {text.t('step_enter_below')}
                                </li>
                            </ol>

                            <form onSubmit={handleSubmit} className="space-y-3">
                                {pageErrors.ip && (
                                    <p className="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                                        {pageErrors.ip}
                                    </p>
                                )}
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        {text.t('transaction_id')}
                                    </label>
                                    <input
                                        maxLength={40}
                                        className="h-10 w-full rounded-xl border border-slate-200 px-3 font-mono text-sm uppercase focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none"
                                        value={data.transaction_id}
                                        onChange={(e) =>
                                            setData(
                                                'transaction_id',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={text.t(
                                            'transaction_id_example',
                                        )}
                                    />
                                    {errors.transaction_id && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.transaction_id}
                                        </p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={
                                        processing || !data.transaction_id
                                    }
                                    className="w-full rounded-xl py-3 text-sm font-bold text-white transition-colors disabled:opacity-60"
                                    style={{ backgroundColor: providerColor }}
                                >
                                    {processing
                                        ? text.t('verifying')
                                        : text.t('confirm_payment')}
                                </button>
                            </form>
                        </div>

                        <p className="text-center text-xs text-slate-600">
                            {text.t('confirmed_once_verified')}
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
