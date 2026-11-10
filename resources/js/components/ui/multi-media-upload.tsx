import { Film, ImagePlus, MousePointer2, Star, X } from 'lucide-react';
import React, { useRef, useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

// FSA-20: mirrors the backend limits in ProductRequest.php exactly (image
// and video caps differ because they're the same mixed-type field with very
// different reasonable size expectations). This is advisory UX only — a
// browser-reported File.type/size is trivially spoofable, so the backend's
// own content-sniffed, size-checked validation remains the real boundary;
// this only saves a legitimate admin an upload-then-rejection round trip.
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_VIDEO_BYTES = 50 * 1024 * 1024;
const MAX_NEW_UPLOADS = 10;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

interface MultiMediaUploadProps {
    value: (string | File)[];
    onChange: (files: (string | File)[]) => void;
    error?: string;
    className?: string;
    /** When provided, renders a small "relates to" select under each tile. */
    tagOptions?: { value: string; label: string }[];
    tags?: (string | null)[];
    onTagChange?: (index: number, tag: string | null) => void;
    /** The item shown as the product's default picture. */
    primaryIndex?: number | null;
    /** The image a product card swaps to on hover; null for no swap. */
    hoverIndex?: number | null;
    onPrimaryChange?: (index: number) => void;
    onHoverChange?: (index: number | null) => void;
}

export function MultiMediaUpload({
    value,
    onChange,
    error,
    className,
    tagOptions,
    tags,
    onTagChange,
    primaryIndex,
    hoverIndex,
    onPrimaryChange,
    onHoverChange,
}: MultiMediaUploadProps): React.JSX.Element {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previews, setPreviews] = useState<{ id: string; url: string; item: string | File; type: 'image' | 'video' }[]>([]);
    const [clientError, setClientError] = useState<string | null>(null);
    const displayedError = clientError ?? error;

    useEffect(() => {
        const nextPreviews = value.map((item, index) => {
            let url: string;
            let type: 'image' | 'video' = 'image';

            if (typeof item === 'string') {
                url = item;
                // Basic check for video extension if it's a URL
                const isVideo = /\.(mp4|webm|ogg|mov)$/i.test(item);
                type = isVideo ? 'video' : 'image';
                return { id: `string-${index}-${item}`, url, item, type };
            } else {
                url = URL.createObjectURL(item);
                type = item.type.startsWith('video/') ? 'video' : 'image';
                return { id: `file-${index}-${item.name}`, url, item, type };
            }
        });

        setPreviews(nextPreviews);

        return () => {
            nextPreviews.forEach((preview) => {
                if (preview.item instanceof File) {
                    URL.revokeObjectURL(preview.url);
                }
            });
        };
    }, [value]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setClientError(null);

        if (!e.target.files || e.target.files.length === 0) {
            return;
        }

        const newFiles = Array.from(e.target.files);
        const resetInput = () => {
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        };

        const existingNewUploadCount = value.filter((item) => item instanceof File).length;

        if (existingNewUploadCount + newFiles.length > MAX_NEW_UPLOADS) {
            setClientError(`You can upload at most ${MAX_NEW_UPLOADS} new images or videos at once.`);
            resetInput();

            return;
        }

        for (const file of newFiles) {
            const isVideo = file.type.startsWith('video/');
            const isImage = ALLOWED_IMAGE_TYPES.includes(file.type);

            if (!isImage && !isVideo) {
                setClientError(`"${file.name}" isn't a supported image or video type.`);
                resetInput();

                return;
            }

            const maxBytes = isImage ? MAX_IMAGE_BYTES : MAX_VIDEO_BYTES;

            if (file.size > maxBytes) {
                setClientError(
                    isImage
                        ? `"${file.name}" is larger than 5MB. Please choose a smaller image.`
                        : `"${file.name}" is larger than 50MB. Please choose a smaller video.`,
                );
                resetInput();

                return;
            }
        }

        onChange([...value, ...newFiles]);
        resetInput();
    };

    const handleRemove = (indexToRemove: number) => {
        setClientError(null);
        const next = value.filter((_, index) => index !== indexToRemove);
        onChange(next);
    };

    return (
        <div className={cn('w-full', className)}>
            <div className="grid grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] items-start gap-3">
                {previews.map((preview, index) => (
                    <div key={preview.id} className="min-w-0 space-y-1.5">
                    <div
                        className="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        {preview.type === 'video' ? (
                            <video
                                src={preview.url}
                                className="h-full w-full object-cover"
                                muted
                                onMouseOver={(e) => (e.target as HTMLVideoElement).play()}
                                onMouseOut={(e) => {
                                    const v = e.target as HTMLVideoElement;
                                    v.pause();
                                    v.currentTime = 0;
                                }}
                            />
                        ) : (
                            <img
                                src={preview.url}
                                alt={`Preview ${index}`}
                                className="h-full w-full object-cover"
                                onError={(e) => {
                                    (e.target as HTMLImageElement).style.display = 'none';
                                }}
                            />
                        )}
                        <button
                            type="button"
                            onClick={() => handleRemove(index)}
                            className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white opacity-0 backdrop-blur-sm transition-all hover:bg-red-500 group-hover:opacity-100"
                        >
                            <X className="size-3.5" />
                        </button>
                        {index === (primaryIndex ?? 0) && (
                            <span className="absolute bottom-0 left-0 right-0 bg-black/60 py-1 text-center text-[10px] font-bold text-white backdrop-blur-sm">
                                DEFAULT
                            </span>
                        )}
                        {index === hoverIndex && (
                            <span className="absolute bottom-0 left-0 right-0 bg-black/60 py-1 text-center text-[10px] font-bold text-white backdrop-blur-sm">
                                HOVER
                            </span>
                        )}
                        {preview.type === 'video' && (
                            <div className="absolute left-2 top-2 rounded-md bg-black/60 p-1 text-white backdrop-blur-sm">
                                <Film className="size-3" />
                            </div>
                        )}
                    </div>
                    {onPrimaryChange && preview.type === 'image' && (
                        <div className="grid gap-1">
                            <button
                                type="button"
                                aria-pressed={index === (primaryIndex ?? 0)}
                                title="Show this picture first: on cards, in search, in the cart and on the product page"
                                onClick={() => onPrimaryChange(index)}
                                className={cn(
                                    'flex w-full items-center justify-center gap-1 rounded-lg border px-1 py-1 text-[10px] font-semibold transition-colors',
                                    index === (primaryIndex ?? 0)
                                        ? 'border-amber-400 bg-amber-50 text-amber-700'
                                        : 'border-slate-200 text-slate-500 hover:border-slate-300',
                                )}
                            >
                                <Star className="size-3" /> Default
                            </button>
                            {index !== (primaryIndex ?? 0) && (
                                <button
                                    type="button"
                                    aria-pressed={index === hoverIndex}
                                    title="Show this picture when a shopper hovers over the product card. Click again to turn the swap off"
                                    onClick={() =>
                                        onHoverChange?.(
                                            index === hoverIndex ? null : index,
                                        )
                                    }
                                    className={cn(
                                        'flex w-full items-center justify-center gap-1 rounded-lg border px-1 py-1 text-[10px] font-semibold transition-colors',
                                        index === hoverIndex
                                            ? 'border-[#c8102e] bg-[#c8102e]/10 text-[#c8102e]'
                                            : 'border-slate-200 text-slate-500 hover:border-slate-300',
                                    )}
                                >
                                    <MousePointer2 className="size-3" /> Hover
                                </button>
                            )}
                        </div>
                    )}
                    {tagOptions && (
                        <select
                            value={tags?.[index] ?? ''}
                            onChange={(e) =>
                                onTagChange?.(index, e.target.value || null)
                            }
                            className="w-full rounded-lg border border-slate-200 bg-white px-1.5 py-1 text-[11px] text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                        >
                            <option value="">General</option>
                            {tagOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    )}
                    </div>
                ))}

                <div
                    className={cn(
                        'flex aspect-square cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-2 text-center transition-colors hover:bg-slate-50',
                        displayedError ? 'border-red-500 bg-red-50' : 'border-slate-300 bg-white'
                    )}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <div className="rounded-full bg-slate-100 p-2.5">
                        <ImagePlus className="size-5 text-slate-400" />
                    </div>
                    <p className="mt-1.5 text-xs leading-tight font-medium text-slate-500">Add Media</p>
                    <p className="text-[10px] leading-tight text-slate-400">Images or Videos</p>
                </div>
            </div>

            {onPrimaryChange && previews.length > 0 && (
                <p className="mt-2 text-xs text-slate-400">
                    Default is the main picture shoppers see. Hover is the
                    picture a product card swaps to when hovered: choose one, or
                    leave it unset for no swap.
                </p>
            )}

            <input
                type="file"
                ref={fileInputRef}
                className="hidden"
                accept="image/*,video/*"
                multiple
                onChange={handleFileChange}
            />

            {displayedError && <p className="mt-2 text-xs text-red-500">{displayedError}</p>}
        </div>
    );
}
