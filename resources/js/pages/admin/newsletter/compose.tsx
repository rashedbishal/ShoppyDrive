import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Bold,
    ChevronDown,
    Heading1,
    Heading2,
    Image,
    Italic,
    Link2,
    List,
    ListOrdered,
    Mail,
    Package,
    Paperclip,
    Quote,
    Send,
    Strikethrough,
    Underline,
    Users,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { Card } from '@/components/ui/card';

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

const TOOLBAR_ACTIONS = [
    { label: 'Bold', icon: Bold, command: 'bold', value: undefined },
    { label: 'Italic', icon: Italic, command: 'italic', value: undefined },
    { label: 'Underline', icon: Underline, command: 'underline', value: undefined },
    { label: 'Strikethrough', icon: Strikethrough, command: 'strikeThrough', value: undefined },
] as const;

function RichTextEditor({
    value,
    onChange,
}: {
    value: string;
    onChange: (html: string) => void;
}) {
    const editorRef = useRef<HTMLDivElement>(null);
    const isInitialized = useRef(false);

    useEffect(() => {
        if (editorRef.current && !isInitialized.current) {
            editorRef.current.innerHTML = value;
            isInitialized.current = true;
        }
    }, []);

    const execCmd = useCallback((command: string, val?: string) => {
        document.execCommand(command, false, val);
        editorRef.current?.focus();
        if (editorRef.current) {
            onChange(editorRef.current.innerHTML);
        }
    }, [onChange]);

    const insertImage = useCallback(() => {
        const url = prompt('Enter image URL:');
        if (url) execCmd('insertImage', url);
    }, [execCmd]);

    const insertLink = useCallback(() => {
        const url = prompt('Enter link URL:');
        if (url) execCmd('createLink', url);
    }, [execCmd]);

    return (
        <div className="overflow-hidden rounded-lg border border-slate-200 focus-within:border-[#c8102e] focus-within:ring-1 focus-within:ring-[#c8102e]">
            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-0.5 border-b border-slate-100 bg-slate-50 p-1.5">
                {/* Formatting */}
                {TOOLBAR_ACTIONS.map(({ label, icon: Icon, command }) => (
                    <button
                        key={label}
                        type="button"
                        title={label}
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd(command);
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Icon className="size-3.5" />
                    </button>
                ))}

                <div className="mx-1 h-4 w-px bg-slate-200" />

                {/* Headings */}
                <button
                    type="button"
                    title="Heading 1"
                    onMouseDown={(e) => { e.preventDefault(); execCmd('formatBlock', 'h1'); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <Heading1 className="size-3.5" />
                </button>
                <button
                    type="button"
                    title="Heading 2"
                    onMouseDown={(e) => { e.preventDefault(); execCmd('formatBlock', 'h2'); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <Heading2 className="size-3.5" />
                </button>
                <button
                    type="button"
                    title="Quote"
                    onMouseDown={(e) => { e.preventDefault(); execCmd('formatBlock', 'blockquote'); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <Quote className="size-3.5" />
                </button>

                <div className="mx-1 h-4 w-px bg-slate-200" />

                {/* Lists */}
                <button
                    type="button"
                    title="Bullet list"
                    onMouseDown={(e) => { e.preventDefault(); execCmd('insertUnorderedList'); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <List className="size-3.5" />
                </button>
                <button
                    type="button"
                    title="Numbered list"
                    onMouseDown={(e) => { e.preventDefault(); execCmd('insertOrderedList'); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <ListOrdered className="size-3.5" />
                </button>

                <div className="mx-1 h-4 w-px bg-slate-200" />

                {/* Link & Image */}
                <button
                    type="button"
                    title="Insert link"
                    onMouseDown={(e) => { e.preventDefault(); insertLink(); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <Link2 className="size-3.5" />
                </button>
                <button
                    type="button"
                    title="Insert image"
                    onMouseDown={(e) => { e.preventDefault(); insertImage(); }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <Image className="size-3.5" />
                </button>

                {/* Font color */}
                <div className="mx-1 h-4 w-px bg-slate-200" />
                <label title="Text color" className="flex size-7 cursor-pointer items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900">
                    <span className="text-xs font-bold">A</span>
                    <input
                        type="color"
                        className="sr-only"
                        onChange={(e) => execCmd('foreColor', e.target.value)}
                    />
                </label>
            </div>

            {/* Editable content */}
            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                onInput={() => {
                    if (editorRef.current) onChange(editorRef.current.innerHTML);
                }}
                className="min-h-[240px] p-4 text-sm text-slate-800 focus:outline-none [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_blockquote]:border-l-2 [&_blockquote]:border-slate-300 [&_blockquote]:pl-4 [&_blockquote]:text-slate-500 [&_a]:text-[#c8102e] [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_img]:max-w-full [&_img]:rounded"
            />
        </div>
    );
}

export default function NewsletterCompose({ products, active_subscribers_count }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string; error?: string } | undefined;

    const [emailType, setEmailType] = useState<EmailType>(null);
    const [showConfirm, setShowConfirm] = useState(false);
    const [selectedProductId, setSelectedProductId] = useState<number | null>(null);
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
    }>({ type: 'product_promotion', subject: '', product_id: '', selected_image: '' });

    const selectedProduct = products.find((p) => p.id === selectedProductId) ?? null;

    const handleProductSelect = (id: number) => {
        setSelectedProductId(id);
        setSelectedImage(null);
        productForm.setData('product_id', String(id));
        const product = products.find((p) => p.id === id);
        if (product) {
            const primary = product.media.find((m) => m.is_primary) ?? product.media[0];
            if (primary) {
                setSelectedImage(primary.path);
                productForm.setData('selected_image', primary.path);
            }
        }
    };

    const handleSend = () => {
        if (emailType === 'custom') {
            if (!customForm.data.subject.trim() || !customForm.data.body.trim()) return;
        } else {
            if (!productForm.data.subject.trim() || !productForm.data.product_id) return;
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
            attachmentFiles.forEach((file) => formData.append('attachments[]', file));

            router.post(route('admin.newsletter.send'), formData, {
                forceFormData: true,
            });
        } else {
            productForm.post(route('admin.newsletter.send'));
        }
    };

    const removeAttachment = (index: number) => {
        setAttachmentFiles((prev) => prev.filter((_, i) => i !== index));
    };

    if (emailType === null) {
        return (
            <>
                <Head title="Send Newsletter — Admin" />
                <div className="space-y-5">
                    <div className="flex items-center gap-3">
                        <Link href={route('admin.newsletter.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                            <ArrowLeft className="size-4" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">Send Newsletter</h1>
                            <p className="text-sm text-slate-500">
                                Will be sent to <strong>{active_subscribers_count}</strong> active subscribers via BCC
                            </p>
                        </div>
                    </div>

                    {active_subscribers_count === 0 && (
                        <div className="flex items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            No active subscribers. Add subscribers before sending.
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
                                <p className="font-semibold text-slate-900">Custom Email</p>
                                <p className="mt-1 text-sm text-slate-500">
                                    Write a custom message with rich text formatting, images, and file attachments.
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
                                <p className="font-semibold text-slate-900">Product Promotion</p>
                                <p className="mt-1 text-sm text-slate-500">
                                    Promote a specific product with its details, price, and a professional layout.
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
            {showConfirm && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-full bg-[#c8102e]/10">
                                <Send className="size-5 text-[#c8102e]" />
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900">Confirm Send</p>
                                <p className="text-sm text-slate-500">
                                    This will send to <strong>{active_subscribers_count}</strong> subscribers via BCC.
                                </p>
                            </div>
                        </div>
                        <div className="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <p>
                                <strong>Type:</strong>{' '}
                                {emailType === 'custom' ? 'Custom Email' : 'Product Promotion'}
                            </p>
                            <p>
                                <strong>Subject:</strong>{' '}
                                {emailType === 'custom' ? customForm.data.subject : productForm.data.subject}
                            </p>
                        </div>
                        <div className="mt-5 flex gap-3">
                            <button
                                type="button"
                                onClick={() => setShowConfirm(false)}
                                className="flex-1 rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
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
                        </div>
                    </div>
                </div>
            )}

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
                            {emailType === 'custom' ? 'Custom Email' : 'Product Promotion'}
                        </h1>
                        <p className="text-sm text-slate-500">
                            Sending to <strong>{active_subscribers_count}</strong> active subscribers via BCC
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* CUSTOM EMAIL FORM */}
                {emailType === 'custom' && (
                    <Card className="overflow-hidden p-0 shadow-sm">
                        <div className="space-y-5 p-6">
                            {/* Subject */}
                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Subject
                                </label>
                                <input
                                    type="text"
                                    placeholder="Enter email subject…"
                                    value={customForm.data.subject}
                                    onChange={(e) => customForm.setData('subject', e.target.value)}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-[#c8102e] focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                />
                                {customForm.errors.subject && (
                                    <p className="mt-1 text-xs text-red-600">{customForm.errors.subject}</p>
                                )}
                            </div>

                            {/* Body */}
                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Email Body
                                </label>
                                <RichTextEditor
                                    value={customForm.data.body}
                                    onChange={(html) => customForm.setData('body', html)}
                                />
                                {customForm.errors.body && (
                                    <p className="mt-1 text-xs text-red-600">{customForm.errors.body}</p>
                                )}
                            </div>

                            {/* Attachments */}
                            <div>
                                <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Attachments <span className="font-normal text-slate-400">(optional)</span>
                                </label>
                                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-500 transition hover:border-[#c8102e] hover:text-[#c8102e]">
                                    <Paperclip className="size-4" />
                                    <span>Click to attach files</span>
                                    <input
                                        type="file"
                                        multiple
                                        className="sr-only"
                                        onChange={(e) => {
                                            if (e.target.files) {
                                                setAttachmentFiles((prev) => [...prev, ...Array.from(e.target.files!)]);
                                            }
                                        }}
                                    />
                                </label>
                                {attachmentFiles.length > 0 && (
                                    <div className="mt-2 space-y-1.5">
                                        {attachmentFiles.map((file, index) => (
                                            <div key={index} className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                                <span className="truncate text-slate-700">{file.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => removeAttachment(index)}
                                                    className="ml-2 shrink-0 text-slate-400 hover:text-red-500"
                                                >
                                                    <X className="size-4" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
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
                                disabled={!customForm.data.subject.trim() || !customForm.data.body.trim() || customForm.processing}
                                className="flex items-center gap-2 rounded-lg bg-[#c8102e] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-50"
                            >
                                <Send className="size-4" />
                                {customForm.processing ? 'Sending…' : 'Review & Send'}
                            </button>
                        </div>
                    </Card>
                )}

                {/* PRODUCT PROMOTION FORM */}
                {emailType === 'product_promotion' && (
                    <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                        <Card className="overflow-hidden p-0 shadow-sm">
                            <div className="space-y-5 p-6">
                                {/* Subject */}
                                <div>
                                    <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                        Email Subject
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="e.g. Check out our latest product!"
                                        value={productForm.data.subject}
                                        onChange={(e) => productForm.setData('subject', e.target.value)}
                                        className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-[#c8102e] focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                    />
                                </div>

                                {/* Product picker */}
                                <div>
                                    <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                        Select Product
                                    </label>
                                    <div className="relative">
                                        <select
                                            value={selectedProductId ?? ''}
                                            onChange={(e) => handleProductSelect(Number(e.target.value))}
                                            className="w-full appearance-none rounded-lg border border-slate-200 px-3 py-2.5 pr-10 text-sm text-slate-800 focus:border-[#c8102e] focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                        >
                                            <option value="">-- Choose a product --</option>
                                            {products.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name} — ৳{p.price.toLocaleString()}
                                                </option>
                                            ))}
                                        </select>
                                        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                    </div>
                                </div>

                                {/* Image picker */}
                                {selectedProduct && selectedProduct.media.length > 0 && (
                                    <div>
                                        <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                            Choose Image for Email
                                        </label>
                                        <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                            {selectedProduct.media.map((media) => (
                                                <button
                                                    key={media.id}
                                                    type="button"
                                                    onClick={() => {
                                                        setSelectedImage(media.path);
                                                        productForm.setData('selected_image', media.path);
                                                    }}
                                                    className={`overflow-hidden rounded-lg border-2 transition ${
                                                        selectedImage === media.path
                                                            ? 'border-[#c8102e] shadow-md'
                                                            : 'border-transparent hover:border-slate-300'
                                                    }`}
                                                >
                                                    <img
                                                        src={media.path}
                                                        alt=""
                                                        className="aspect-square w-full object-cover"
                                                    />
                                                </button>
                                            ))}
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
                                    disabled={!productForm.data.subject.trim() || !productForm.data.product_id || productForm.processing}
                                    className="flex items-center gap-2 rounded-lg bg-[#c8102e] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#a50d25] disabled:opacity-50"
                                >
                                    <Send className="size-4" />
                                    {productForm.processing ? 'Sending…' : 'Review & Send'}
                                </button>
                            </div>
                        </Card>

                        {/* Email preview */}
                        {selectedProduct && (
                            <Card className="overflow-hidden p-0 shadow-sm">
                                <div className="border-b border-slate-100 px-4 py-3">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        Preview
                                    </p>
                                </div>
                                <div className="overflow-hidden rounded-b-lg bg-[#f4f5f7] p-3">
                                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                                        {/* Mini header */}
                                        <div className="bg-[#c8102e] px-4 py-3 text-center">
                                            <p className="text-sm font-bold text-white">ShoppyDrive</p>
                                            <p className="mt-0.5 inline-block rounded-full bg-white/20 px-2 py-0.5 text-[10px] text-white/80">
                                                Featured Product
                                            </p>
                                        </div>

                                        {/* Product image */}
                                        {selectedImage && (
                                            <img
                                                src={selectedImage}
                                                alt={selectedProduct.name}
                                                className="w-full object-cover"
                                                style={{ maxHeight: 160 }}
                                            />
                                        )}

                                        {/* Details */}
                                        <div className="p-4">
                                            <p className="text-xs font-bold text-slate-800">{selectedProduct.name}</p>
                                            {selectedProduct.short_description && (
                                                <p className="mt-1 text-[10px] leading-relaxed text-slate-500 line-clamp-2">
                                                    {selectedProduct.short_description}
                                                </p>
                                            )}
                                            <div className="mt-2 flex items-baseline gap-2">
                                                <span className="text-sm font-black text-[#c8102e]">
                                                    ৳{selectedProduct.price.toLocaleString()}
                                                </span>
                                                {selectedProduct.compare_price && (
                                                    <span className="text-xs text-slate-400 line-through">
                                                        ৳{selectedProduct.compare_price.toLocaleString()}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-3 rounded-lg bg-[#1a1a2e] py-1.5 text-center text-[10px] font-bold uppercase tracking-widest text-white">
                                                View Product
                                            </div>
                                        </div>

                                        <div className="border-t border-slate-100 bg-slate-50 px-4 py-3 text-center">
                                            <p className="text-[10px] text-slate-400">
                                                Sent via ShoppyDrive Newsletter
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
