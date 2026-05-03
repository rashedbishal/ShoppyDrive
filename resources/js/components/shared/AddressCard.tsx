import { MapPin, Phone, User, Edit2, Trash2, CheckCircle2 } from 'lucide-react';
import React from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Address } from '@/types';

interface AddressCardProps {
    address: Address;
    onEdit?: (address: Address) => void;
    onDelete?: (addressId: number) => void;
    onSetDefault?: (addressId: number) => void;
}

export function AddressCard({
    address,
    onEdit,
    onDelete,
    onSetDefault,
}: AddressCardProps): React.JSX.Element {
    return (
        <div className={`p-6 rounded-3xl border-2 transition-all h-full flex flex-col justify-between ${
            address.is_default 
                ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] ring-4 ring-[var(--color-primary-light)]' 
                : 'border-[var(--color-border)] bg-white hover:border-[var(--color-text-primary)]'
        }`}>
            <div>
                <div className="flex justify-between items-start mb-6">
                    <div className="flex items-center gap-3">
                        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${
                            address.is_default ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]'
                        }`}>
                            <MapPin size={20} />
                        </div>
                        <div>
                            <h4 className="font-bold text-[var(--color-text-primary)]">{address.label || 'Home'}</h4>
                            {address.is_default && (
                                <span className="text-[10px] font-bold text-[var(--color-primary)] uppercase tracking-widest">Default Address</span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="space-y-4 mb-8">
                    <div className="flex items-start gap-3">
                        <User size={16} className="text-[var(--color-text-muted)] shrink-0 mt-0.5" />
                        <span className="text-sm font-bold text-[var(--color-text-primary)]">{address.name}</span>
                    </div>
                    <div className="flex items-start gap-3">
                        <Phone size={16} className="text-[var(--color-text-muted)] shrink-0 mt-0.5" />
                        <span className="text-sm font-medium text-[var(--color-text-secondary)]">{address.phone}</span>
                    </div>
                    <div className="flex items-start gap-3">
                        <MapPin size={16} className="text-[var(--color-text-muted)] shrink-0 mt-0.5" />
                        <p className="text-sm text-[var(--color-text-secondary)] leading-relaxed">
                            {address.address_line1}<br />
                            {address.address_line2 && <>{address.address_line2}<br /></>}
                            {address.city}, {address.state} {address.postal_code}<br />
                            {address.country}
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex items-center justify-between pt-6 border-t border-[var(--color-border)]/50">
                <div className="flex gap-2">
                    <Button 
                        variant="ghost" 
                        size="sm" 
                        className="rounded-lg h-9 w-9 p-0 hover:bg-white hover:text-[var(--color-primary)] transition-all"
                        onClick={() => onEdit?.(address)}
                    >
                        <Edit2 size={16} />
                    </Button>
                    {!address.is_default && (
                        <Button 
                            variant="ghost" 
                            size="sm" 
                            className="rounded-lg h-9 w-9 p-0 hover:bg-white hover:text-[var(--color-error)] transition-all"
                            onClick={() => onDelete?.(address.id)}
                        >
                            <Trash2 size={16} />
                        </Button>
                    )}
                </div>
                
                {!address.is_default && (
                    <button 
                        onClick={() => onSetDefault?.(address.id)}
                        className="text-xs font-bold text-[var(--color-primary)] hover:underline"
                    >
                        Set as Default
                    </button>
                )}
                {address.is_default && (
                    <div className="flex items-center gap-1.5 text-[var(--color-primary)]">
                        <CheckCircle2 size={14} />
                        <span className="text-xs font-bold">Selected</span>
                    </div>
                )}
            </div>
        </div>
    );
}
