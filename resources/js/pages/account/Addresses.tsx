import { Head, useForm } from '@inertiajs/react';
import { Plus, MapPin, AlertCircle } from 'lucide-react';
import React, { useState } from 'react';

import { AddressCard } from '@/components/shared/AddressCard';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Modal } from '@/components/ui/modal';
import { Select } from '@/components/ui/select';
import type { Address } from '@/types';

const BANGLADESH_DISTRICTS = [
    'Bagerhat',
    'Bandarban',
    'Barguna',
    'Barishal',
    'Bhola',
    'Bogura',
    'Brahmanbaria',
    'Chandpur',
    'Chapai Nawabganj',
    'Chattogram',
    'Chuadanga',
    "Cox's Bazar",
    'Cumilla',
    'Dhaka',
    'Dinajpur',
    'Faridpur',
    'Feni',
    'Gaibandha',
    'Gazipur',
    'Gopalganj',
    'Habiganj',
    'Jamalpur',
    'Jashore',
    'Jhalokati',
    'Jhenaidah',
    'Joypurhat',
    'Khagrachhari',
    'Khulna',
    'Kishoreganj',
    'Kurigram',
    'Kushtia',
    'Lakshmipur',
    'Lalmonirhat',
    'Madaripur',
    'Magura',
    'Manikganj',
    'Meherpur',
    'Moulvibazar',
    'Munshiganj',
    'Mymensingh',
    'Naogaon',
    'Narail',
    'Narayanganj',
    'Narsingdi',
    'Natore',
    'Netrokona',
    'Nilphamari',
    'Noakhali',
    'Pabna',
    'Panchagarh',
    'Patuakhali',
    'Pirojpur',
    'Rajbari',
    'Rajshahi',
    'Rangamati',
    'Rangpur',
    'Satkhira',
    'Shariatpur',
    'Sherpur',
    'Sirajganj',
    'Sunamganj',
    'Sylhet',
    'Tangail',
    'Thakurgaon',
];

const DHAKA_CITY_OPTIONS = [
    'Inside Dhaka City Corporation',
    'Outside Dhaka City Corporation',
];

const DISTRICT_OPTIONS = [
    { label: 'Select district…', value: '', disabled: true },
    ...BANGLADESH_DISTRICTS.map((d) => ({ label: d, value: d })),
];

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

    const isDhaka = form.data.state === 'Dhaka';

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

    const handleDistrictChange = (value: string) => {
        form.setData({
            ...form.data,
            state: value,
            city: '',
        });
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
        <>
            <Head title="Saved Addresses" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                            Saved Addresses
                        </h1>
                        <p className="text-[var(--color-text-secondary)]">
                            Manage your shipping addresses for a faster checkout
                            experience.
                        </p>
                    </div>
                    <Button
                        onClick={() => openModal()}
                        className="rounded-xl bg-[var(--color-primary)] font-bold text-white hover:bg-[var(--color-primary-hover)]"
                    >
                        <Plus size={18} className="mr-2" />
                        Add New Address
                    </Button>
                </div>

                {addresses.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                    <div className="rounded-3xl border border-[var(--color-border)] bg-white p-20 text-center">
                        <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                            <MapPin size={40} />
                        </div>
                        <h3 className="mb-2 text-xl font-bold text-[var(--color-text-primary)]">
                            No addresses saved
                        </h3>
                        <p className="mx-auto mb-10 max-w-sm text-[var(--color-text-secondary)]">
                            Save your shipping addresses here to speed up your
                            checkout process next time you shop.
                        </p>
                        <Button
                            onClick={() => openModal()}
                            className="h-14 rounded-xl px-10 font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                        >
                            Add Your First Address
                        </Button>
                    </div>
                )}

                <div className="flex gap-4 rounded-3xl border border-blue-100 bg-blue-50 p-6">
                    <AlertCircle
                        size={24}
                        className="mt-0.5 shrink-0 text-blue-600"
                    />
                    <div className="text-sm leading-relaxed text-blue-800">
                        <p className="mb-1 font-bold">Privacy Notice</p>
                        <p>
                            Your address information is stored securely and only
                            used for shipping your orders. We never share your
                            personal data with third parties without your
                            consent.
                        </p>
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
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="label">
                                Address Label (e.g. Home, Office)
                            </Label>
                            <Input
                                maxLength={30}
                                id="label"
                                value={form.data.label}
                                onChange={(e) =>
                                    form.setData('label', e.target.value)
                                }
                                placeholder="Home"
                                className="h-12 rounded-xl"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="name">Receiver's Name</Label>
                            <Input
                                maxLength={100}
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                placeholder="Full Name"
                                className="h-12 rounded-xl"
                                required
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="phone">Phone Number</Label>
                        <div className="relative">
                            <span className="absolute top-1/2 left-4 -translate-y-1/2 text-sm font-bold text-[var(--color-text-muted)]">
                                +880
                            </span>
                            <Input
                                maxLength={20}
                                id="phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                placeholder="1XXXXXXXXX"
                                className="h-12 rounded-xl pl-16"
                                required
                            />
                        </div>
                        {form.errors.phone && (
                            <p className="text-xs text-red-500">
                                {form.errors.phone}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="address">Address Line 1</Label>
                        <Input
                            maxLength={200}
                            id="address"
                            value={form.data.address_line1}
                            onChange={(e) =>
                                form.setData('address_line1', e.target.value)
                            }
                            placeholder="Street address, P.O. box, house number"
                            className="h-12 rounded-xl"
                            required
                        />
                        {form.errors.address_line1 && (
                            <p className="text-xs text-red-500">
                                {form.errors.address_line1}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {/* District dropdown */}
                        <div className="space-y-2">
                            <Label htmlFor="state">District *</Label>
                            <Select
                                id="state"
                                value={form.data.state}
                                onChange={(e) =>
                                    handleDistrictChange(e.target.value)
                                }
                                options={DISTRICT_OPTIONS}
                                className="h-12 rounded-xl"
                                required
                                error={form.errors.state}
                            />
                        </div>

                        {/* City — text input normally, dropdown for Dhaka */}
                        <div className="space-y-2">
                            <Label htmlFor="city">City *</Label>
                            {isDhaka ? (
                                <Select
                                    id="city"
                                    value={form.data.city}
                                    onChange={(e) =>
                                        form.setData('city', e.target.value)
                                    }
                                    className="h-12 rounded-xl"
                                    required
                                    error={form.errors.city}
                                >
                                    <option value="" disabled>
                                        Select area…
                                    </option>
                                    {DHAKA_CITY_OPTIONS.map((opt) => (
                                        <option key={opt} value={opt}>
                                            {opt}
                                        </option>
                                    ))}
                                </Select>
                            ) : (
                                <Input
                                    maxLength={60}
                                    id="city"
                                    value={form.data.city}
                                    onChange={(e) =>
                                        form.setData('city', e.target.value)
                                    }
                                    placeholder="e.g. Sylhet"
                                    className="h-12 rounded-xl"
                                    required
                                    disabled={!form.data.state}
                                />
                            )}
                            {form.errors.city && (
                                <p className="text-xs text-red-500">
                                    {form.errors.city}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="postal">Postal Code</Label>
                            <Input
                                maxLength={10}
                                id="postal"
                                value={form.data.postal_code}
                                onChange={(e) =>
                                    form.setData('postal_code', e.target.value)
                                }
                                placeholder="e.g. 1212"
                                className="h-12 rounded-xl"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="country">Country</Label>
                            <Input
                                maxLength={60}
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
                            onCheckedChange={(checked) =>
                                form.setData('is_default', !!checked)
                            }
                        />
                        <Label
                            htmlFor="is_default"
                            className="cursor-pointer text-sm font-medium"
                        >
                            Set as default shipping address
                        </Label>
                    </div>

                    <div className="flex justify-end gap-3 pt-6">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setIsModalOpen(false)}
                            className="rounded-xl font-bold"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="h-12 rounded-xl px-8 font-bold"
                        >
                            {form.processing
                                ? 'Saving…'
                                : editingAddress
                                  ? 'Update Address'
                                  : 'Save Address'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
