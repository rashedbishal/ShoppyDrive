import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import {
    email as emailRule,
    maxLength,
    minLength,
    required,
    validate,
} from '@/lib/validate';

export default function AdminCreate() {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        });

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(255)],
            email: [required(), maxLength(255), emailRule()],
            password: [required(), minLength(8)],
        });

        if (
            data.password &&
            data.password_confirmation &&
            data.password !== data.password_confirmation
        ) {
            clientErrors.password_confirmation = 'The passwords do not match.';
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.admins.store'));
    };

    return (
        <>
            <Head title="New Admin — Admin" />
            <RouteModal
                title="New Admin Account"
                returnTo={route('admin.admins.index')}
                className="sm:max-w-lg"
            >
                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Account Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Full Name
                                </label>
                                <input
                                    className={inputClass}
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Admin name"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Email Address
                                </label>
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="admin@example.com"
                                />
                                {errors.email && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    className={inputClass}
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    placeholder="Minimum 8 characters"
                                />
                                {errors.password && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Confirm Password
                                </label>
                                <input
                                    type="password"
                                    className={inputClass}
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Repeat password"
                                />
                                {errors.password_confirmation && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.password_confirmation}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                >
                                    {processing ? 'Creating…' : 'Create Admin'}
                                </Button>
                                <Link href={route('admin.admins.index')}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </RouteModal>
        </>
    );
}
