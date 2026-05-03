import { Head, useForm } from '@inertiajs/react';
import { 
    Plus,
    MapPin,
    AlertCircle
} from 'lucide-react';
import React, { useState } from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { AddressCard } from '@/components/shared/AddressCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Modal } from '@/components/ui/modal';
import type { Address } from '@/types';

interface AddressesProps {
    addresses: Address[];
}

export default function Addresses({
    addresses = [],
}: AddressesProps): React.JSX.Element {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingAddress, setEditingAddress] = useState<Address | null>(null);

    const form = useForm({
        id: undefined as number | undefined,
        label: 'Home',
        name: '',
        phone: '',
        address_line1: '',
        address_line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'Bangladesh',
        is_default: false,
    });

    const openModal = (address: Address | null = null) => {
        if (address) {
            setEditingAddress(address);
            form.setData({
                id: address.id,
                label: address.label || 'Home',
                name: address.name,
                phone: address.phone,
                address_line1: address.address_line1,
                address_line2: address.address_line2 || '',
                city: address.city,
                state: address.state,
                postal_code: address.postal_code || '',
                country: address.country,
                is_default: address.is_default,
            });
        } else {
            setEditingAddress(null);
            form.reset();
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingAddress) {
            form.put(route('account.addresses.update', editingAddress.id), {
                onSuccess: () => setIsModalOpen(false),
            });
        } else {
            form.post(route('account.addresses.store'), {
                onSuccess: () => setIsModalOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this address?')) {
            form.delete(route('account.addresses.destroy', id));
        }
    };

    const handleSetDefault = (id: number) => {
        form.patch(route('account.addresses.default', id));
    };

    return (
        <AccountLayout>
            <Head title="Saved Addresses" />
            
            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-serif font-bold text-[var(--color-text-primary)] mb-1">Saved Addresses</h1>
                        <p className="text-[var(--color-text-secondary)]">Manage your shipping addresses for a faster checkout experience.</p>
                    </div>
                    <Button onClick={() => openModal()} className="rounded-xl font-bold bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)]">
                        <Plus size={18} className="mr-2" />
                        Add New Address
                    </Button>
                </div>

                {addresses.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {addresses.map((address) => (
                            <AddressCard 
                                key={address.id} 
                                address={address} 
                                onEdit={openModal}
                                onDelete={handleDelete}
                                onSetDefault={handleSetDefault}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="bg-white rounded-3xl p-20 border border-[var(--color-border)] text-center">
                        <div className="w-20 h-20 rounded-full bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-text-muted)] mx-auto mb-6">
                            <MapPin size={40} />
                        </div>
                        <h3 className="text-xl font-bold text-[var(--color-text-primary)] mb-2">No addresses saved</h3>
                        <p className="text-[var(--color-text-secondary)] mb-10 max-w-sm mx-auto">
                            Save your shipping addresses here to speed up your checkout process next time you shop.
                        </p>
                        <Button onClick={() => openModal()} className="rounded-xl px-10 h-14 font-bold shadow-lg shadow-[var(--color-primary)]/20">
                            Add Your First Address
                        </Button>
                    </div>
                )}

                <div className="p-6 bg-blue-50 border border-blue-100 rounded-3xl flex gap-4">
                    <AlertCircle size={24} className="text-blue-600 shrink-0 mt-0.5" />
                    <div className="text-sm text-blue-800 leading-relaxed">
                        <p className="font-bold mb-1">Privacy Notice</p>
                        <p>Your address information is stored securely and only used for shipping your orders. We never share your personal data with third parties without your consent.</p>
                    </div>
                </div>
            </div>

            {/* Address Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title={editingAddress ? 'Edit Address' : 'Add New Address'}
                size="lg"
            >
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <Label htmlFor="label">Address Label (e.g. Home, Office)</Label>
                            <Input 
                                id="label" 
                                value={form.data.label} 
                                onChange={e => form.setData('label', e.target.value)} 
                                placeholder="Home"
                                className="h-12 rounded-xl"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="name">Receiver's Name</Label>
                            <Input 
                                id="name" 
                                value={form.data.name} 
                                onChange={e => form.setData('name', e.target.value)} 
                                placeholder="Full Name"
                                className="h-12 rounded-xl"
                                required
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="phone">Phone Number</Label>
                        <div className="relative">
                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-[var(--color-text-muted)]">+880</span>
                            <Input 
                                id="phone" 
                                value={form.data.phone} 
                                onChange={e => form.setData('phone', e.target.value)} 
                                placeholder="1XXXXXXXXX"
                                className="h-12 pl-16 rounded-xl"
                                required
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="address">Address Line 1</Label>
                        <Input 
                            id="address" 
                            value={form.data.address_line1} 
                            onChange={e => form.setData('address_line1', e.target.value)} 
                            placeholder="Street address, P.O. box, house number"
                            className="h-12 rounded-xl"
                            required
                        />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <Label htmlFor="city">City / District</Label>
                            <Input 
                                id="city" 
                                value={form.data.city} 
                                onChange={e => form.setData('city', e.target.value)} 
                                placeholder="e.g. Dhaka"
                                className="h-12 rounded-xl"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="state">Division / State</Label>
                            <Input 
                                id="state" 
                                value={form.data.state} 
                                onChange={e => form.setData('state', e.target.value)} 
                                placeholder="e.g. Dhaka Division"
                                className="h-12 rounded-xl"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                            <Label htmlFor="postal">Postal Code</Label>
                            <Input 
                                id="postal" 
                                value={form.data.postal_code} 
                                onChange={e => form.setData('postal_code', e.target.value)} 
                                placeholder="e.g. 1212"
                                className="h-12 rounded-xl"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="country">Country</Label>
                            <Input 
                                id="country" 
                                value={form.data.country} 
                                readOnly
                                className="h-12 rounded-xl bg-[var(--color-surface-alt)]"
                            />
                        </div>
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox 
                            id="is_default" 
                            checked={form.data.is_default}
                            onCheckedChange={(checked) => form.setData('is_default', !!checked)}
                        />
                        <Label htmlFor="is_default" className="text-sm font-medium cursor-pointer">
                            Set as default shipping address
                        </Label>
                    </div>

                    <div className="flex justify-end gap-3 pt-6">
                        <Button type="button" variant="ghost" onClick={() => setIsModalOpen(false)} className="rounded-xl font-bold">
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing} className="rounded-xl px-8 font-bold h-12">
                            {form.processing ? 'Saving...' : editingAddress ? 'Update Address' : 'Save Address'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AccountLayout>
    );
}
