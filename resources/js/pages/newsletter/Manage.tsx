import { Head, Link, useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';
import { SiteLogo } from '@/components/shared/SiteLogo';

export default function Manage(): React.JSX.Element {
    const { data, setData, post, processing } = useForm({ email: '' });
    const [submitted, setSubmitted] = useState(false);

    const submit = (e: React.FormEvent): void => {
        e.preventDefault();
        post('/newsletter/manage', {
            onSuccess: () => setSubmitted(true),
        });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Manage Subscription — ShoppyDrive" />

            <div className="w-full max-w-[440px]">
                <div className="mb-8 flex justify-center">
                    <Link href="/" className="inline-flex items-center">
                        <SiteLogo className="h-11" />
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                    <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-red-50">
                        <Mail className="size-8 text-brand" />
                    </div>

                    <h1 className="mb-2 text-2xl font-bold text-gray-900">
                        Manage your subscription
                    </h1>

                    {submitted ? (
                        <p className="text-sm leading-6 text-gray-600">
                            If that email is subscribed to our newsletter, we've
                            sent a link to manage it — check your inbox.
                        </p>
                    ) : (
                        <>
                            <p className="mb-6 text-sm leading-6 text-gray-600">
                                Enter your email and, if it's subscribed, we'll
                                send you a link to unsubscribe.
                            </p>

                            <form
                                onSubmit={submit}
                                className="space-y-3 text-left"
                            >
                                <label
                                    htmlFor="email"
                                    className="text-sm font-medium text-gray-700"
                                >
                                    Email address
                                </label>
                                <input
                                    maxLength={150}
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    className="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-900 focus:border-brand focus:outline-none"
                                />
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-brand-foreground transition hover:bg-[#a60d26] disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Sending…'
                                        : 'Send me the link'}
                                </button>
                            </form>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
