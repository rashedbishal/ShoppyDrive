import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';

type PageRow = {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    updated_at: string | null;
};

type Props = { pages: PageRow[] };

/**
 * DISC-05: the six legal/info pages. Edit-only: they exist by slug, each at
 * its own storefront URL, so there is no create or delete here.
 */
export default function PagesIndex({ pages }: Props) {
    return (
        <>
            <Head title="Policy & Info Pages — Admin" />
            <div className="space-y-4">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Policy &amp; Info Pages
                    </h1>
                    <p className="text-sm text-slate-500">
                        Edit the text of your legal and information pages.
                        Their web addresses are fixed.
                    </p>
                </div>

                <Card className="divide-y divide-slate-100 shadow-sm">
                    {pages.length === 0 && (
                        <p className="p-6 text-sm text-slate-500">
                            No pages found.
                        </p>
                    )}
                    {pages.map((page) => (
                        <div
                            key={page.id}
                            className="flex flex-wrap items-center gap-3 p-4"
                        >
                            <FileText className="size-4 shrink-0 text-slate-400" />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-slate-800">
                                    {page.title}
                                </p>
                                <p className="truncate text-xs text-slate-400">
                                    /{page.slug}
                                    {page.updated_at &&
                                        ` · updated ${new Date(
                                            page.updated_at,
                                        ).toLocaleDateString('en-BD', {
                                            dateStyle: 'medium',
                                        })}`}
                                </p>
                            </div>
                            <Badge
                                variant={
                                    page.is_published ? 'success' : 'warning'
                                }
                                size="sm"
                            >
                                {page.is_published ? 'Published' : 'Hidden'}
                            </Badge>
                            <Link
                                href={route('admin.pages.edit', page.id)}
                                className="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Edit
                            </Link>
                        </div>
                    ))}
                </Card>
            </div>
        </>
    );
}
