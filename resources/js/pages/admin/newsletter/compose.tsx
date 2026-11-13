import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion, MotionConfig } from 'framer-motion';
import {
    AlertTriangle,
    ArrowLeft,
    Mail,
    Package,
    Paperclip,
    Send,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { AdminReveal } from '@/components/admin/admin-motion';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import { Card } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { Select } from '@/components/ui/select';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import { useRowKeys } from '@/hooks/use-row-keys';
import {
    dialogItem,
    dialogOverlayMotion,
    dialogPanel,
} from '@/lib/dialog-motion';
import { maxLength, required, validate } from '@/lib/validate';

type ProductMedia = {
    id: number;
    path: string;
    is_primary: boolean;
};

type Product = {
    id: number;
    name: string;
    slug: string;
    price: number;
    compare_price: number | null;
    short_description: string | null;
    media: ProductMedia[];
};

type Props = {
    products: Product[];
    active_subscribers_count: number;
};

type EmailType = 'custom' | 'product_promotion' | null;

export default function NewsletterCompose({
    products,
    active_subscribers_count,
}: Props) {
    const listUrl = useAdminListUrl('admin.newsletter.index');
    const [emailType, setEmailType] = useState<EmailType>(null);
    const [showConfirm, setShowConfirm] = useState(false);
    const [selectedProductId, setSelectedProductId] = useState<number | null>(
        null,
    );
    const [selectedImage, setSelectedImage] = useState<string | null>(null);
    const [attachmentFiles, setAttachmentFiles] = useState<File[]>([]);

    const customForm = useForm<{
        type: 'custom';
        subject: string;
        body: string;
    }>({ type: 'custom', subject: '', body: '' });

    const productForm = useForm<{
        type: 'product_promotion';
        subject: string;
        product_id: string;
        selected_image: string;
    }>({
        type: 'product_promotion',
        subject: '',
        product_id: '',
        selected_image: '',
    });

    const selectedProduct =
        products.find((p) => p.id === selectedProductId) ?? null;

    const handleProductSelect = (id: number) => {
        setSelectedProductId(id);
        setSelectedImage(null);
        productForm.setData('product_id', String(id));
        const product = products.find((p) => p.id === id);

        if (product) {
            const primary =
                product.media.find((m) => m.is_primary) ?? product.media[0];

            if (primary) {
                setSelectedImage(primary.path);
                productForm.setData('selected_image', primary.path);
            }
        }
    };

    const handleSend = () => {
        if (emailType === 'custom') {
            customForm.clearErrors();
            const clientErrors = validate(customForm.data, {
                subject: [required(), maxLength(150)],
                body: [required()],
            });

            if (Object.keys(clientErrors).length > 0) {
                customForm.setError(clientErrors);

                return;
            }
        } else {
            productForm.clearErrors();
            const clientErrors = validate(productForm.data, {
                subject: [required(), maxLength(150)],
                product_id: [required()],
            });

            if (Object.keys(clientErrors).length > 0) {
                productForm.setError(clientErrors);

                return;
            }
        }

        setShowConfirm(true);
    };

    const confirmSend = () => {
        setShowConfirm(false);

        if (emailType === 'custom') {
            const formData = new FormData();
            formData.append('type', 'custom');
            formData.append('subject', customForm.data.subject);
            formData.append('body', customForm.data.body);
            attachmentFiles.forEach((file) =>
                formData.append('attachments[]', file),
            );

            router.post(route('admin.newsletter.send'), formData, {
                forceFormData: true,
            });
        } else {
            productForm.post(route('admin.newsletter.send'));
        }
    };

    const attachmentRowKeys = useRowKeys(attachmentFiles.length);

    const removeAttachment = (index: number) => {
        attachmentRowKeys.removeKey(index);
        setAttachmentFiles((prev) => prev.filter((_, i) => i !== index));
    };

    if (emailType === null) {
        return (
            <>
                <Head title="Send Newsletter — Admin" />
                <div className="space-y-5">
                    <div className="flex items-center gap-3">
                        <Link
                            href={listUrl}
                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">
                                Send Newsletter
                            </h1>
                            <p className="text-sm text-slate-600">
                                Will be sent to{' '}
                                <strong>{active_subscribers_count}</strong>{' '}
                                active subscribers via BCC
                            </p>
                        </div>
                    </div>

                    {active_subscribers_count === 0 && (
                        <div className="flex items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            No active subscribers. Add subscribers before
                            sending.
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        {/* Custom email */}
                        <button
                            type="button"
                            onClick={() => setEmailType('custom')}
                            className="group flex flex-col items-start gap-3 rounded-xl border-2 border-slate-200 bg-white p-6 text-left transition hover:border-[#c8102e] hover:shadow-md"
                        >
                            <div className="flex size-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:bg-[#c8102e]/10 group-hover:text-[#c8102e]">
                                <Mail className="size-6" />
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900">
                                    Custom Email
                                </p>
                                <p className="mt-1 text-sm text-slate-600">
                                    Write a custom message with rich text
                                    formatting, images, and file attachments.
                                </p>
                            </div>
                            <span className="mt-auto text-xs font-semibold text-[#c8102e] opacity-0 transition group-hover:opacity-100">
                                Select →
                            </span>
                        </button>

                        {/* Product promotion */}
                        <button
                            type="button"
                            onClick={() => setEmailType('product_promotion')}
                            className="group flex flex-col items-start gap-3 rounded-xl border-2 border-slate-200 bg-white p-6 text-left transition hover:border-[#c8102e] hover:shadow-md"
                        >
                            <div className="flex size-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition group-hover:bg-[#c8102e]/10 group-hover:text-[#c8102e]">
                                <Package className="size-6" />
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900">
                                    Product Promotion
                                </p>
                                <p className="mt-1 text-sm text-slate-600">
                                    Promote a specific product with its details,
                                    price, and a professional layout.
                                </p>
                            </div>
                            <span className="mt-auto text-xs font-semibold text-[#c8102e] opacity-0 transition group-hover:opacity-100">
                                Select →
                            </span>
                        </button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Compose Newsletter — Admin" />

            {/* Confirmation modal */}
            <MotionConfig reducedMotion="user">
                <AnimatePresence>
                    {showConfirm && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <motion.div
                                className="absolute inset-0 bg-slate-950/40 backdrop-blur-[3px]"
                                {...dialogOverlayMotion}
                            />
                            <motion.div
                                variants={dialogPanel}
                                initial="hidden"
                                animate="visible"
                                exit="exit"
                                className="relative w-full max-w-sm rounded-2xl bg-white p-5 shadow-[0_24px_64px_-16px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5"
                            >
                                <motion.div
                                    variants={dialogItem}
                                    className="flex items-center gap-3"
                                >
                                    <div className="flex size-10 items-center justify-center rounded-full bg-[#c8102e]/10">
                                        <Send className="size-5 text-[#c8102e]" />
                                    </div>
                                    <div>
                                        <p className="text-[15px] font-semibold text-slate-900">
                                            Confirm Send
                                        </p>
                                        <p className="text-[13px] text-slate-600">
                                            This will send to{' '}
                                            <strong>
                                                {active_subscribers_count}
                                            </strong>{' '}
                                            subscribers via BCC.
                                        </p>
                                    </div>
                                </motion.div>
                                <motion.div
                                    variants={dialogItem}
                                    className="mt-3.5 rounded-lg bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700"
                                >
                                    <p>
                                        <strong>Type:</strong>{' '}
                                        {emailType === 'custom'
                                            ? 'Custom Email'
                                            : 'Product Promotion'}
                                    </p>
                                    <p>
                                        <strong>Subject:</strong>{' '}
                                        {emailType === 'custom'
                                            ? customForm.data.subject
                                            : productForm.data.subject}
                                    </p>
                                </motion.div>
                                <motion.div
                                    variants={dialogItem}
                                    className="-mx-5 mt-4 -mb-5 flex gap-2 rounded-b-2xl border-t border-slate-100 bg-slate-50/80 px-5 py-3"
                                >
                                    <button
                                        type="button"
                                        onClick={() => setShowConfirm(false)}
                                        className="flex-1 rounded-lg border border-slate-200 bg-white py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={confirmSend}
                                        className="flex-1 rounded-lg bg-[#c8102e] py-2 text-sm font-semibold text-white transition hover:bg-[#a50d25]"
                                    >
                                        Send Now
                                    </button>
                                </motion.div>
                            </motion.div>
                        </div>
                    )}
                </AnimatePresence>
            </MotionConfig>

            <div className="space-y-5">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => setEmailType(null)}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeft className="size-4" />
                    </button>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            {emailType === 'custom'
                                ? 'Custom Email'
                                : 'Product Promotion'}
                        </h1>
                        <p className="text-sm text-slate-600">
                            Sending to{' '}
                            <strong>{active_subscribers_count}</strong> active
                            subscribers via BCC
                        </p>
                    </div>
                </div>

                {/* CUSTOM EMAIL FORM */}
                <AnimatePresence initial={false}>
                    {emailType === 'custom' && (
                        <AdminReveal key="custom-email">
                            <Card className="overflow-hidden p-0 shadow-sm">
                                <div className="space-y-5 p-6">
                                    {/* Subject */}
                                    <div>
                                        <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                            Subject
                                        </label>
                                        <input
                                            maxLength={150}
                                            type="text"
                                            placeholder="Enter email subject…"
                                            value={customForm.data.subject}
                                            onChange={(e) =>
                                                customForm.setData(
                                                    'subject',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-[#c8102e] focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                        />
                                        <CharCount
                                            value={customForm.data.subject}
                                            max={150}
                                            className="mt-1 block text-right"
                                        />

                                        {customForm.errors.subject && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {customForm.errors.subject}
                                            </p>
                                        )}
                                    </div>

                                    {/* Body */}
                                    <div>
                                        <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                            Email Body
                                        </label>
                                        <RichTextEditor
                                            value={customForm.data.body}
                                            maxLength={20000}
                                            onChange={(html) =>
                                                customForm.setData('body', html)
                                            }
                                        />
                                        {customForm.errors.body && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {customForm.errors.body}
                                            </p>
                                        )}
                                    </div>

                                    {/* Attachments */}
                                    <div>
                                        <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                            Attachments{' '}
                                            <span className="font-normal text-slate-600">
                                                (optional)
                                            </span>
                                        </label>
                                        <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600 transition hover:border-[#c8102e] hover:text-[#c8102e]">
                                            <Paperclip className="size-4" />
                                            <span>Click to attach files</span>
                                            <input
                                                type="file"
                                                multiple
                                                className="sr-only"
                                                onChange={(e) => {
                                                    if (e.target.files) {
                                                        setAttachmentFiles(
                                                            (prev) => [
                                                                ...prev,
                                                                ...Array.from(
                                                                    e.target
                                                                        .files!,
                                                                ),
                                                            ],
                                                        );
                                                    }
                                                }}
                                            />
                                        </label>
                                        <AnimatePresence initial={false}>
                                            {attachmentFiles.length > 0 && (
                                                <AdminReveal key="attachments">
                                                    <div className="mt-2 space-y-1.5">
                                                        <AnimatePresence
                                                            initial={false}
                                                        >
                                                            {attachmentFiles.map(
                                                                (
                                                                    file,
                                                                    index,
                                                                ) => (
                                                                    <AdminReveal
                                                                        key={
                                                                            attachmentRowKeys
                                                                                .keys[
                                                                                index
                                                                            ]
                                                                        }
                                                                    >
                                                                        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                                                            <span className="truncate text-slate-700">
                                                                                {
                                                                                    file.name
                                                                                }
                                                                            </span>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    removeAttachment(
                                                                                        index,
                                                                                    )
                                                                                }
                                                                                className="ml-2 shrink-0 text-slate-400 hover:text-red-500"
                                                                            >
                                                                                <X className="size-4" />
                                                                            </button>
                                                                        </div>
                                                                    </AdminReveal>
                                                                ),
                                                            )}
                                                        </AnimatePresence>
                                                    </div>
                                                </AdminReveal>
                                            )}
                                        </AnimatePresence>
                                    </div>
                                </div>

                                <div className="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                                    <button
                                        type="button"
                                        onClick={() => setEmailType(null)}
                                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Back
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleSend}
                                        disabled={
                                            !customForm.data.subject.trim() ||
                                            !customForm.data.body.trim() ||
                                            customForm.processing
                                        }
                                        className="flex items-center gap-2 rounded-lg bg-[#c8102e] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-50"
                                    >
                                        <Send className="size-4" />
                                        {customForm.processing
                                            ? 'Sending…'
                                            : 'Review & Send'}
                                    </button>
                                </div>
                            </Card>
                        </AdminReveal>
                    )}
                </AnimatePresence>

                {/* PRODUCT PROMOTION FORM */}
                <AnimatePresence initial={false}>
                    {emailType === 'product_promotion' && (
                        <AdminReveal key="promotion-email">
                            <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                                <Card className="overflow-hidden p-0 shadow-sm">
                                    <div className="space-y-5 p-6">
                                        {/* Subject */}
                                        <div>
                                            <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                                Email Subject
                                            </label>
                                            <input
                                                maxLength={150}
                                                type="text"
                                                placeholder="e.g. Check out our latest product!"
                                                value={productForm.data.subject}
                                                onChange={(e) =>
                                                    productForm.setData(
                                                        'subject',
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-[#c8102e] focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                            />
                                            <CharCount
                                                value={productForm.data.subject}
                                                max={150}
                                                className="mt-1 block text-right"
                                            />

                                            {productForm.errors.subject && (
                                                <p className="mt-1 text-xs text-red-600">
                                                    {productForm.errors.subject}
                                                </p>
                                            )}
                                        </div>

                                        {/* Product picker */}
                                        <div>
                                            <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                                Select Product
                                            </label>
                                            <div className="relative">
                                                <Select
                                                    value={
                                                        selectedProductId ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        handleProductSelect(
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    selectClassName="w-full appearance-none rounded-lg border border-slate-200 px-3 py-2.5 pr-10 text-sm text-slate-800 focus:border-[#c8102e] focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                                >
                                                    <option value="">
                                                        -- Choose a product --
                                                    </option>
                                                    {products.map((p) => (
                                                        <option
                                                            key={p.id}
                                                            value={p.id}
                                                        >
                                                            {p.name} — ৳
                                                            {p.price.toLocaleString()}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </div>
                                            {productForm.errors.product_id && (
                                                <p className="mt-1 text-xs text-red-600">
                                                    {
                                                        productForm.errors
                                                            .product_id
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        {/* Image picker */}
                                        {selectedProduct &&
                                            selectedProduct.media.length >
                                                0 && (
                                                <div>
                                                    <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                                        Choose Image for Email
                                                    </label>
                                                    <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                                        {selectedProduct.media.map(
                                                            (media) => (
                                                                <button
                                                                    key={
                                                                        media.id
                                                                    }
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setSelectedImage(
                                                                            media.path,
                                                                        );
                                                                        productForm.setData(
                                                                            'selected_image',
                                                                            media.path,
                                                                        );
                                                                    }}
                                                                    className={`overflow-hidden rounded-lg border-2 transition ${
                                                                        selectedImage ===
                                                                        media.path
                                                                            ? 'border-[#c8102e] shadow-md'
                                                                            : 'border-transparent hover:border-slate-300'
                                                                    }`}
                                                                >
                                                                    <img
                                                                        src={
                                                                            media.path
                                                                        }
                                                                        alt=""
                                                                        className="aspect-square w-full object-cover"
                                                                    />
                                                                </button>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                    </div>

                                    <div className="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                                        <button
                                            type="button"
                                            onClick={() => setEmailType(null)}
                                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Back
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleSend}
                                            disabled={
                                                !productForm.data.subject.trim() ||
                                                !productForm.data.product_id ||
                                                productForm.processing
                                            }
                                            className="flex items-center gap-2 rounded-lg bg-[#c8102e] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-50"
                                        >
                                            <Send className="size-4" />
                                            {productForm.processing
                                                ? 'Sending…'
                                                : 'Review & Send'}
                                        </button>
                                    </div>
                                </Card>

                                {/* Email preview */}
                                <AnimatePresence initial={false}>
                                    {selectedProduct && (
                                        <AdminReveal key="selected-product">
                                            <Card className="overflow-hidden p-0 shadow-sm">
                                                <div className="border-b border-slate-100 px-4 py-3">
                                                    <p className="text-xs font-semibold tracking-wider text-slate-600 uppercase">
                                                        Preview
                                                    </p>
                                                </div>
                                                <div className="overflow-hidden rounded-b-lg bg-[#f4f5f7] p-3">
                                                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                                                        {/* Mini header */}
                                                        <div className="bg-[#c8102e] px-4 py-3 text-center">
                                                            <p className="text-sm font-bold text-white">
                                                                ShoppyDrive
                                                            </p>
                                                            <p className="mt-0.5 inline-block rounded-full bg-white/20 px-2 py-0.5 text-[10px] text-white/80">
                                                                Featured Product
                                                            </p>
                                                        </div>

                                                        {/* Product image */}
                                                        {selectedImage && (
                                                            <img
                                                                src={
                                                                    selectedImage
                                                                }
                                                                alt={
                                                                    selectedProduct.name
                                                                }
                                                                className="w-full object-cover"
                                                                style={{
                                                                    maxHeight: 160,
                                                                }}
                                                            />
                                                        )}

                                                        {/* Details */}
                                                        <div className="p-4">
                                                            <p className="text-xs font-bold text-slate-800">
                                                                {
                                                                    selectedProduct.name
                                                                }
                                                            </p>
                                                            {selectedProduct.short_description && (
                                                                <p className="mt-1 line-clamp-2 text-[10px] leading-relaxed text-slate-600">
                                                                    {
                                                                        selectedProduct.short_description
                                                                    }
                                                                </p>
                                                            )}
                                                            <div className="mt-2 flex items-baseline gap-2">
                                                                <span className="text-sm font-black text-[#c8102e]">
                                                                    ৳
                                                                    {selectedProduct.price.toLocaleString()}
                                                                </span>
                                                                {selectedProduct.compare_price && (
                                                                    <span className="text-xs text-slate-400 line-through">
                                                                        ৳
                                                                        {selectedProduct.compare_price.toLocaleString()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {/* Opens this product in the CMS, in a new tab. */}
                                                            <a
                                                                href={route(
                                                                    'admin.products.edit',
                                                                    selectedProduct.id,
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="mt-3 block rounded-lg bg-[#1a1a2e] py-1.5 text-center text-[10px] font-bold tracking-widest text-white uppercase transition hover:bg-[#2a2a4e]"
                                                            >
                                                                View Product
                                                            </a>
                                                        </div>

                                                        <div className="border-t border-slate-100 bg-slate-50 px-4 py-3 text-center">
                                                            <p className="text-[10px] text-slate-600">
                                                                Sent via
                                                                ShoppyDrive
                                                                Newsletter
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </Card>
                                        </AdminReveal>
                                    )}
                                </AnimatePresence>
                            </div>
                        </AdminReveal>
                    )}
                </AnimatePresence>
            </div>
        </>
    );
}
