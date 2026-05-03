import { Head, useForm, usePage } from '@inertiajs/react';
import { 
    User, 
    Mail, 
    Phone, 
    Camera,
    Save,
    ShieldCheck,
    Facebook,
    Chrome
} from 'lucide-react';
import React, { useRef, useState } from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/lib/utils';
import type { SharedPageProps, User as UserType } from '@/types';

export default function Profile(): React.JSX.Element {
    const { auth } = usePage<SharedPageProps>().props;
    const user = auth.user as UserType;
    
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        name: user.name,
        email: user.email,
        phone: user.phone || '',
        avatar: null as File | null,
    });

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            form.setData('avatar', file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setAvatarPreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Use post with _method=PUT for file uploads in Inertia
        form.post(route('account.profile.update'), {
            forceFormData: true,
            onSuccess: () => {
                setAvatarPreview(null);
            }
        });
    };

    return (
        <AccountLayout>
            <Head title="My Profile" />
            
            <div className="space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-serif font-bold text-[var(--color-text-primary)] mb-1">Profile Information</h1>
                    <p className="text-[var(--color-text-secondary)]">Update your account details and manage how we communicate with you.</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    {/* Left: Form */}
                    <div className="lg:col-span-8">
                        <form onSubmit={handleSubmit} className="bg-white rounded-3xl p-8 md:p-10 shadow-sm border border-[var(--color-border)] space-y-8">
                            {/* Avatar Upload */}
                            <div className="flex flex-col md:flex-row items-center gap-8 pb-8 border-b border-[var(--color-border)]">
                                <div className="relative group">
                                    <Avatar className="h-32 w-32 border-4 border-white shadow-xl">
                                        <AvatarImage src={avatarPreview || user.avatar || undefined} />
                                        <AvatarFallback className="bg-[var(--color-primary-light)] text-[var(--color-primary)] text-3xl font-bold">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <button 
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="absolute inset-0 bg-black/40 text-white rounded-full flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-[2px]"
                                    >
                                        <Camera size={24} className="mb-1" />
                                        <span className="text-[10px] font-bold uppercase tracking-widest">Change Photo</span>
                                    </button>
                                    <input 
                                        type="file" 
                                        ref={fileInputRef} 
                                        onChange={handleAvatarChange} 
                                        className="hidden" 
                                        accept="image/*"
                                    />
                                </div>
                                <div className="text-center md:text-left">
                                    <h3 className="text-lg font-bold text-[var(--color-text-primary)] mb-1">Your Profile Photo</h3>
                                    <p className="text-sm text-[var(--color-text-secondary)] mb-4">Upload a clear photo to help us identify you.</p>
                                    <div className="flex gap-2 justify-center md:justify-start">
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            size="sm" 
                                            className="rounded-xl font-bold h-10 border-[var(--color-border)]"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            Upload New
                                        </Button>
                                        {user.avatar && (
                                            <Button type="button" variant="ghost" size="sm" className="rounded-xl font-bold h-10 text-[var(--color-error)]">
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Full Name</Label>
                                    <div className="relative">
                                        <Input 
                                            id="name" 
                                            value={form.data.name} 
                                            onChange={e => form.setData('name', e.target.value)} 
                                            className="h-12 pl-12 rounded-xl"
                                            required
                                        />
                                        <User size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email Address</Label>
                                    <div className="relative">
                                        <Input 
                                            id="email" 
                                            type="email"
                                            value={form.data.email} 
                                            readOnly
                                            className="h-12 pl-12 rounded-xl bg-[var(--color-surface-alt)] text-[var(--color-text-muted)] cursor-not-allowed"
                                        />
                                        <Mail size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                    </div>
                                    <p className="text-[10px] text-[var(--color-text-muted)] font-medium italic">* Contact support to change your email</p>
                                </div>
                            </div>

                            <div className="space-y-2 max-w-md">
                                <Label htmlFor="phone">Phone Number</Label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-[var(--color-text-muted)]">+880</span>
                                    <Input 
                                        id="phone" 
                                        value={form.data.phone} 
                                        onChange={e => form.setData('phone', e.target.value)} 
                                        placeholder="1XXXXXXXXX"
                                        className="h-12 pl-16 rounded-xl"
                                    />
                                    <Phone size={18} className="absolute right-4 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                </div>
                            </div>

                            <div className="pt-6 border-t border-[var(--color-border)] flex justify-end">
                                <Button type="submit" disabled={form.processing} size="lg" className="rounded-xl px-10 h-14 font-bold shadow-lg shadow-[var(--color-primary)]/20">
                                    <Save size={20} className="mr-2" />
                                    {form.processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </div>

                    {/* Right: Social & Verification */}
                    <div className="lg:col-span-4 space-y-8">
                        {/* Email Verification */}
                        <div className="bg-white rounded-3xl p-8 shadow-sm border border-[var(--color-border)]">
                            <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-6 flex items-center gap-2">
                                <ShieldCheck size={18} className="text-[var(--color-success)]" />
                                Trust & Safety
                            </h3>
                            <div className="p-4 rounded-2xl bg-green-50 border border-green-100 mb-4">
                                <div className="flex justify-between items-center mb-2">
                                    <span className="text-xs font-bold text-green-800">Email Verified</span>
                                    <ShieldCheck size={16} className="text-green-600" />
                                </div>
                                <p className="text-[10px] text-green-700 leading-relaxed">Your email address is verified. This helps us ensure your account's security.</p>
                            </div>
                            {user.phone ? (
                                <div className="p-4 rounded-2xl bg-green-50 border border-green-100">
                                    <div className="flex justify-between items-center mb-2">
                                        <span className="text-xs font-bold text-green-800">Phone Linked</span>
                                        <ShieldCheck size={16} className="text-green-600" />
                                    </div>
                                    <p className="text-[10px] text-green-700 leading-relaxed">Mobile number +880 {user.phone} is linked to your account.</p>
                                </div>
                            ) : (
                                <div className="p-4 rounded-2xl bg-amber-50 border border-amber-100">
                                    <p className="text-xs font-bold text-amber-800 mb-2">Link Phone Number</p>
                                    <p className="text-[10px] text-amber-700 leading-relaxed mb-4">Link your phone number to receive order updates via SMS.</p>
                                    <Button variant="outline" size="sm" className="w-full rounded-lg border-amber-200 text-amber-800 hover:bg-amber-100 font-bold h-9">Link Now</Button>
                                </div>
                            )}
                        </div>

                        {/* Social Accounts */}
                        <div className="bg-white rounded-3xl p-8 shadow-sm border border-[var(--color-border)]">
                            <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-6">Connected Accounts</h3>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between p-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-white border border-[var(--color-border)] flex items-center justify-center text-blue-600">
                                            <Chrome size={20} />
                                        </div>
                                        <div>
                                            <p className="text-xs font-bold text-[var(--color-text-primary)]">Google</p>
                                            <p className="text-[10px] text-[var(--color-text-muted)] font-medium">Connected</p>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm" className="h-8 px-3 rounded-lg font-bold text-[var(--color-error)]">Disconnect</Button>
                                </div>
                                
                                <div className="flex items-center justify-between p-4 rounded-2xl border border-dashed border-[var(--color-border)]">
                                    <div className="flex items-center gap-3 opacity-60">
                                        <div className="w-10 h-10 rounded-xl bg-white border border-[var(--color-border)] flex items-center justify-center text-blue-800">
                                            <Facebook size={20} />
                                        </div>
                                        <div>
                                            <p className="text-xs font-bold text-[var(--color-text-primary)]">Facebook</p>
                                            <p className="text-[10px] text-[var(--color-text-muted)] font-medium">Not Connected</p>
                                        </div>
                                    </div>
                                    <Button variant="outline" size="sm" className="h-8 px-3 rounded-lg font-bold border-[var(--color-border)]">Connect</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AccountLayout>
    );
}
