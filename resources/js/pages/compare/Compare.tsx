import { Head, Link } from '@inertiajs/react';
import { 
    GitCompare, 
    Trash2, 
    ShoppingCart, 
    Star, 
    Check, 
    X,
    ArrowLeft,
    Plus
} from 'lucide-react';
import React from 'react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { Product } from '@/types';

interface CompareProps {
    products: Product[];
}

export default function Compare({
    products = [],
}: CompareProps): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const removeCompareItem = useUiStore((state) => state.removeCompareItem);
    const clearCompareItems = useUiStore((state) => state.clearCompareItems);

    const attributes = Array.from(
        new Set(products.flatMap(p => p.attributes.map(a => a.name)))
    );

    const renderPrice = (product: Product) => {
        const currentPrice = product.in_flash_sale && product.flash_sale_price 
            ? product.flash_sale_price 
            : product.price;
        return (
            <div className="flex flex-col items-center">
                <span className="text-xl font-bold text-[var(--color-primary)]">{formatCurrency(currentPrice)}</span>
                {product.compare_price && product.compare_price > currentPrice && (
                    <span className="text-xs text-[var(--color-text-muted)] line-through">{formatCurrency(product.compare_price)}</span>
                )}
            </div>
        );
    };

    if (products.length === 0) {
        return (
            <div className="bg-[var(--color-surface-alt)] min-h-screen flex items-center justify-center py-20 px-4">
                <Head title="Compare Products" />
                <div className="max-w-md w-full bg-white rounded-3xl p-12 shadow-sm border border-[var(--color-border)] text-center">
                    <div className="w-24 h-24 rounded-full bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-text-muted)] mx-auto mb-6">
                        <GitCompare size={48} />
                    </div>
                    <h1 className="text-2xl font-bold text-[var(--color-text-primary)] mb-3">No items to compare</h1>
                    <p className="text-[var(--color-text-secondary)] mb-10">
                        Add up to 3 products to the comparison list to see their differences side-by-side.
                    </p>
                    <Button asChild className="w-full h-14 rounded-2xl font-bold text-lg shadow-lg">
                        <Link href={route('products.index')}>
                            Explore Products
                        </Link>
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-[var(--color-surface-alt)] min-h-screen py-12">
            <Head title="Compare Products" />
            
            <div className="container mx-auto px-4 md:max-w-7xl">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
                    <div className="flex items-center gap-4">
                        <Link 
                            href={route('products.index')}
                            className="p-2 rounded-full bg-white border border-[var(--color-border)] text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors"
                        >
                            <ArrowLeft size={18} />
                        </Link>
                        <div>
                            <h1 className="text-3xl font-serif font-bold text-[var(--color-text-primary)] mb-1">Compare Products</h1>
                            <p className="text-[var(--color-text-secondary)]">Showing side-by-side comparison of {products.length} items.</p>
                        </div>
                    </div>
                    
                    <button 
                        onClick={clearCompareItems}
                        className="text-sm font-bold text-[var(--color-error)] hover:underline flex items-center gap-2"
                    >
                        <Trash2 size={16} />
                        Clear Comparison
                    </button>
                </div>

                <div className="bg-white rounded-3xl shadow-sm border border-[var(--color-border)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <tbody>
                                {/* Product Images & Basic Info */}
                                <tr>
                                    <td className="p-8 w-64 bg-[var(--color-surface-alt)]/30 border-b border-[var(--color-border)]">
                                        <h3 className="text-xs font-bold text-[var(--color-text-muted)] uppercase tracking-widest">Product Information</h3>
                                    </td>
                                    {products.map((product) => (
                                        <td key={product.id} className="p-8 min-w-[280px] border-b border-l border-[var(--color-border)] text-center relative group">
                                            <button 
                                                onClick={() => removeCompareItem(product.id)}
                                                className="absolute top-4 right-4 p-1.5 rounded-full bg-red-50 text-[var(--color-error)] opacity-0 group-hover:opacity-100 transition-opacity"
                                                title="Remove"
                                            >
                                                <X size={14} strokeWidth={3} />
                                            </button>
                                            
                                            <div className="w-40 h-40 mx-auto mb-6 rounded-2xl overflow-hidden bg-[var(--color-surface-alt)]">
                                                <img 
                                                    src={product.primary_image_url || product.images[0]?.path} 
                                                    alt={product.name} 
                                                    className="w-full h-full object-cover" 
                                                />
                                            </div>
                                            <p className="text-[10px] font-bold text-[var(--color-primary)] uppercase tracking-widest mb-1">
                                                {product.brand?.name || 'GENUINE'}
                                            </p>
                                            <Link 
                                                href={route('product.show', product.slug)}
                                                className="text-base font-bold text-[var(--color-text-primary)] hover:text-[var(--color-primary)] transition-colors line-clamp-2 h-12 mb-4"
                                            >
                                                {product.name}
                                            </Link>
                                            
                                            <Button 
                                                onClick={() => { addItem({ product, quantity: 1 }); }}
                                                className="w-full rounded-xl font-bold h-11"
                                            >
                                                <ShoppingCart size={16} className="mr-2" />
                                                Add to Cart
                                            </Button>
                                        </td>
                                    ))}
                                    {/* Fill empty spots */}
                                    {[...Array(3 - products.length)].map((_, i) => (
                                        <td key={i} className="p-8 min-w-[280px] border-b border-l border-[var(--color-border)] bg-[var(--color-surface-alt)]/10">
                                            <div className="flex flex-col items-center justify-center h-full text-[var(--color-text-muted)] py-20">
                                                <div className="w-12 h-12 rounded-2xl border-2 border-dashed border-current flex items-center justify-center mb-4 opacity-30">
                                                    <Plus size={24} />
                                                </div>
                                                <p className="text-xs font-bold uppercase tracking-widest opacity-50">Add Product</p>
                                            </div>
                                        </td>
                                    ))}
                                </tr>

                                {/* Price Row */}
                                <tr className="hover:bg-[var(--color-surface-alt)]/30 transition-colors">
                                    <td className="px-8 py-5 bg-[var(--color-surface-alt)]/30 font-bold text-sm text-[var(--color-text-secondary)]">Price</td>
                                    {products.map(p => (
                                        <td key={p.id} className="px-8 py-5 border-l border-[var(--color-border)] text-center font-bold">
                                            {renderPrice(p)}
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map((_, i) => <td key={i} className="border-l border-[var(--color-border)]"></td>)}
                                </tr>

                                {/* Rating Row */}
                                <tr className="hover:bg-[var(--color-surface-alt)]/30 transition-colors">
                                    <td className="px-8 py-5 bg-[var(--color-surface-alt)]/30 font-bold text-sm text-[var(--color-text-secondary)]">Customer Rating</td>
                                    {products.map(p => (
                                        <td key={p.id} className="px-8 py-5 border-l border-[var(--color-border)] text-center">
                                            <div className="flex items-center justify-center gap-1.5">
                                                <Star size={16} className="fill-[var(--color-accent)] text-[var(--color-accent)]" />
                                                <span className="font-bold text-[var(--color-text-primary)]">{p.average_rating?.toFixed(1) || '5.0'}</span>
                                                <span className="text-[10px] text-[var(--color-text-muted)] font-medium">({p.reviews_count || 0})</span>
                                            </div>
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map((_, i) => <td key={i} className="border-l border-[var(--color-border)]"></td>)}
                                </tr>

                                {/* Stock Status */}
                                <tr className="hover:bg-[var(--color-surface-alt)]/30 transition-colors">
                                    <td className="px-8 py-5 bg-[var(--color-surface-alt)]/30 font-bold text-sm text-[var(--color-text-secondary)]">Availability</td>
                                    {products.map(p => (
                                        <td key={p.id} className="px-8 py-5 border-l border-[var(--color-border)] text-center">
                                            {p.stock_quantity > 0 ? (
                                                <div className="inline-flex items-center gap-1.5 text-[var(--color-success)] text-xs font-bold uppercase tracking-widest">
                                                    <Check size={14} strokeWidth={3} />
                                                    In Stock
                                                </div>
                                            ) : (
                                                <div className="inline-flex items-center gap-1.5 text-[var(--color-error)] text-xs font-bold uppercase tracking-widest">
                                                    <X size={14} strokeWidth={3} />
                                                    Out of Stock
                                                </div>
                                            )}
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map((_, i) => <td key={i} className="border-l border-[var(--color-border)]"></td>)}
                                </tr>

                                {/* Category */}
                                <tr className="hover:bg-[var(--color-surface-alt)]/30 transition-colors">
                                    <td className="px-8 py-5 bg-[var(--color-surface-alt)]/30 font-bold text-sm text-[var(--color-text-secondary)]">Category</td>
                                    {products.map(p => (
                                        <td key={p.id} className="px-8 py-5 border-l border-[var(--color-border)] text-center text-sm font-medium text-[var(--color-text-primary)]">
                                            {p.category.name}
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map((_, i) => <td key={i} className="border-l border-[var(--color-border)]"></td>)}
                                </tr>

                                {/* Attributes */}
                                {attributes.map(attrName => (
                                    <tr key={attrName} className="hover:bg-[var(--color-surface-alt)]/30 transition-colors">
                                        <td className="px-8 py-5 bg-[var(--color-surface-alt)]/30 font-bold text-sm text-[var(--color-text-secondary)]">{attrName}</td>
                                        {products.map(p => {
                                            const attr = p.attributes.find(a => a.name === attrName);
                                            return (
                                                <td key={p.id} className="px-8 py-5 border-l border-[var(--color-border)] text-center text-sm font-medium">
                                                    {attr ? attr.values.map(v => v.value).join(', ') : <span className="text-[var(--color-text-muted)]">—</span>}
                                                </td>
                                            );
                                        })}
                                        {[...Array(3 - products.length)].map((_, i) => <td key={i} className="border-l border-[var(--color-border)]"></td>)}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
