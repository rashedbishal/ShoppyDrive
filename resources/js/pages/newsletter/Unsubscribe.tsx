import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle, Mail, XCircle } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';

type Props = {
    status: 'confirm' | 'already_unsubscribed' | 'invalid';
    email?: string;
    token?: string;
};

export default function Unsubscribe({
    status,
    email,
    token,
}: Props): React.JSX.Element {
    const { post, processing } = useForm({ email: email ?? '', token: token ?? '' });

    const submit = (e: React.FormEvent): void => {
        e.preventDefault();
        post('/newsletter/unsubscribe');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
            <Head title="Unsubscribe — ShoppyDrive" />

            <div className="w-full max-w-[440px]">
                <div className="mb-8 flex justify-center">
                    <Link href="/" className="inline-flex items-center gap-2.5">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                        <span className="text-lg font-bold text-gray-900">
                            ShoppyDrive
                        </span>
                    </Link>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                    {status === 'invalid' && (
                        <>
                            <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-red-50">
                                <XCircle className="size-8 text-[#c8102e]" />
                            </div>
                            <h1 className="mb-2 text-2xl font-bold text-gray-900">
                                Link invalid or expired
                            </h1>
                            <p className="text-sm leading-6 text-gray-500">
                                This unsubscribe link isn't valid. If you
                                still want to stop receiving our emails,
                                use the link from a more recent newsletter.
                            </p>
                        </>
                    )}

                    {status === 'already_unsubscribed' && (
                        <>
                            <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-green-50">
                                <CheckCircle className="size-8 text-green-600" />
                            </div>
                            <h1 className="mb-2 text-2xl font-bold text-gray-900">
                                You're unsubscribed
                            </h1>
                            <p className="text-sm leading-6 text-gray-500">
                                {email} won't receive any more newsletter
                                emails from us.
                            </p>
                        </>
                    )}

                    {status === 'confirm' && (
                        <>
                            <div className="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-red-50">
                                <Mail className="size-8 text-[#c8102e]" />
                            </div>
                            <h1 className="mb-2 text-2xl font-bold text-gray-900">
                                Unsubscribe from our newsletter?
                            </h1>
                            <p className="mb-6 text-sm leading-6 text-gray-500">
                                {email} will stop receiving newsletter
                                emails from us. You can subscribe again at
                                any time.
                            </p>

                            <form onSubmit={submit}>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-xl bg-[#c8102e] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#a60d26] disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Unsubscribing…'
                                        : 'Confirm unsubscribe'}
                                </button>
                            </form>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
