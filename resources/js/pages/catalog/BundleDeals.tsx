import { Head } from '@inertiajs/react';
import { Gift } from 'lucide-react';
import { BundleCard } from '@/components/shared/BundleCard';
import type { ProductBundle } from '@/components/shared/BundleCard';
import { PageHero } from '@/components/shared/PageHero';
import { EmptyState } from '@/components/ui/empty-state';

type Props = {
    bundles: ProductBundle[];
};

export default function BundleDeals({ bundles }: Props): React.JSX.Element {
    return (
        <>
            <Head title="Bundle Deals | ShoppyDrive" />

            <div className="space-y-10 py-6 lg:py-8">
                <PageHero
                    badge={{ icon: Gift, label: 'Bundle deals' }}
                    eyebrow="Save more"
                    title="Bundle Deals"
                    description="Hand-picked product combos at a special price or with free delivery."
                />

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {bundles.length > 0 ? (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {bundles.map((bundle) => (
                                <BundleCard key={bundle.id} bundle={bundle} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={Gift}
                            title="No bundle deals right now"
                            description="Check back soon for new combo offers."
                        />
                    )}
                </section>
            </div>
        </>
    );
}
