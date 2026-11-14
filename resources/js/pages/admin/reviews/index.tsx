import { Head, Link, router } from '@inertiajs/react';
import { Check, Search, Star, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { ImageLightbox } from '@/components/shared/ImageLightbox';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';

type Review = {
    id: number;
    rating: number;
    title: string | null;
    body: string;
    images?: string[] | null;
    is_approved: boolean;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
    product: { id: number; name: string; slug: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    reviews: Paginated<Review>;
    filters: { search?: string; status?: string };
};

/** The photos a customer attached to a review, small, each opening full size. */
function ReviewPhotos({ images }: { images: string[] }) {
    const [open, setOpen] = useState<number | null>(null);

    return (
        <div className="mt-2 flex flex-wrap gap-1.5">
            {images.map((image, index) => (
                <button
                    key={image}
                    type="button"
                    onClick={() => setOpen(index)}
                    aria-label={`View review photo ${index + 1}`}
                    className="size-14 overflow-hidden rounded-md border border-slate-200 hover:opacity-80"
                >
                    <img
                        src={image}
                        alt={`Review photo ${index + 1}`}
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                </button>
            ))}
            <ImageLightbox
                images={images}
                index={open}
                onIndexChange={setOpen}
                label="Review photo"
            />
        </div>
    );
}

export default function ReviewsIndex({ reviews, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);

    const applyFilters = (overrides: Record<string, string>) => {
        const params = { ...filters, search, ...overrides };

        if (
            (params.search ?? '') === (filters.search ?? '') &&
            (params.status ?? '') === (filters.status ?? '')
        ) {
            return;
        }

        router.get(route('admin.reviews.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.reviews.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleApprove = (id: number) =>
        router.patch(route('admin.reviews.approve', id));
    const handleReject = (id: number) =>
        router.patch(route('admin.reviews.reject', id));
    const confirmDelete = () => {
        if (deleteTarget === null) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.reviews.destroy', deleteTarget), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const Stars = ({ rating }: { rating: number }) => (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map((i) => (
                <Star
                    key={i}
                    className={`size-3.5 ${i <= rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200'}`}
                />
            ))}
        </div>
    );

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applyFilters({}));

    return (
        <>
            <Head title="Reviews — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Reviews
                    </h1>
                    <p className="text-sm text-slate-600">
                        {reviews.total} total
                    </p>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative min-w-[200px] flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search by product or customer…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters({})
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-brand focus:outline-none"
                            />
                        </div>
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                applyFilters({ status: e.target.value })
                            }
                            containerClassName="min-w-40"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Reviews</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                        </Select>
                        <ClearFiltersButton
                            active={Boolean(
                                search || filters.search || filters.status,
                            )}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {reviews.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-600">
                                No reviews found.
                            </p>
                        ) : (
                            reviews.data.map((review) => (
                                <div
                                    key={review.id}
                                    className="admin-row-card px-4 py-3 hover:bg-slate-50/60"
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Stars rating={review.rating} />
                                                {review.title && (
                                                    <span className="text-sm font-semibold break-words text-slate-800">
                                                        {review.title}
                                                    </span>
                                                )}
                                                <Badge
                                                    variant={
                                                        review.is_approved
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                    size="sm"
                                                >
                                                    {review.is_approved
                                                        ? 'Approved'
                                                        : 'Pending'}
                                                </Badge>
                                            </div>
                                            <p className="keep-clamp mt-1 line-clamp-2 text-xs text-slate-600">
                                                {review.body}
                                            </p>
                                            {review.images &&
                                                review.images.length > 0 && (
                                                    <ReviewPhotos
                                                        images={review.images}
                                                    />
                                                )}
                                            <p className="mt-1 text-xs text-slate-600">
                                                {review.user?.name ?? 'Unknown'}{' '}
                                                ·{' '}
                                                {review.product?.name ??
                                                    'Unknown product'}{' '}
                                                ·{' '}
                                                {new Date(
                                                    review.created_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            {!review.is_approved && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleApprove(review.id)
                                                    }
                                                    title="Approve"
                                                    className="rounded-lg p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
                                                >
                                                    <Check className="size-4" />
                                                </button>
                                            )}
                                            {review.is_approved && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleReject(review.id)
                                                    }
                                                    title="Reject"
                                                    className="rounded-lg p-1.5 text-slate-400 hover:bg-amber-50 hover:text-amber-600"
                                                >
                                                    <X className="size-4" />
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDeleteTarget(review.id)
                                                }
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {reviews.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-600">
                                Page {reviews.current_page} of{' '}
                                {reviews.last_page}
                            </p>
                            <div className="flex gap-1">
                                {reviews.links[0]?.url && (
                                    <Link
                                        href={reviews.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {reviews.links[reviews.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            reviews.links[
                                                reviews.links.length - 1
                                            ].url!
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete review?"
                description="This review will be permanently removed."
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
