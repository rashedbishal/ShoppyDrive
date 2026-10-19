import { Film, ImagePlus, X } from 'lucide-react';
import React, { useRef, useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface MultiMediaUploadProps {
    value: (string | File)[];
    onChange: (files: (string | File)[]) => void;
    error?: string;
    className?: string;
    /** When provided, renders a small "relates to" select under each tile. */
    tagOptions?: { value: string; label: string }[];
    tags?: (string | null)[];
    onTagChange?: (index: number, tag: string | null) => void;
}

export function MultiMediaUpload({
    value,
    onChange,
    error,
    className,
    tagOptions,
    tags,
    onTagChange,
}: MultiMediaUploadProps): React.JSX.Element {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previews, setPreviews] = useState<{ id: string; url: string; item: string | File; type: 'image' | 'video' }[]>([]);

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
        if (e.target.files && e.target.files.length > 0) {
            const newFiles = Array.from(e.target.files);
            onChange([...value, ...newFiles]);
        }
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleRemove = (indexToRemove: number) => {
        const next = value.filter((_, index) => index !== indexToRemove);
        onChange(next);
    };

    return (
        <div className={cn('w-full', className)}>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                {previews.map((preview, index) => (
                    <div key={preview.id} className="space-y-1.5">
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
                        {index === 0 && (
                            <span className="absolute bottom-0 left-0 right-0 bg-black/60 py-1 text-center text-[10px] font-bold text-white backdrop-blur-sm">
                                PRIMARY
                            </span>
                        )}
                        {preview.type === 'video' && (
                            <div className="absolute left-2 top-2 rounded-md bg-black/60 p-1 text-white backdrop-blur-sm">
                                <Film className="size-3" />
                            </div>
                        )}
                    </div>
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
                        'flex aspect-square cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed transition-colors hover:bg-slate-50',
                        error ? 'border-red-500 bg-red-50' : 'border-slate-300 bg-white'
                    )}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <div className="rounded-full bg-slate-100 p-2.5">
                        <ImagePlus className="size-5 text-slate-400" />
                    </div>
                    <p className="mt-2 text-xs font-medium text-slate-500">Add Media</p>
                    <p className="text-[10px] text-slate-400">Images or Videos</p>
                </div>
            </div>

            <input
                type="file"
                ref={fileInputRef}
                className="hidden"
                accept="image/*,video/*"
                multiple
                onChange={handleFileChange}
            />

            {error && <p className="mt-2 text-xs text-red-500">{error}</p>}
        </div>
    );
}
