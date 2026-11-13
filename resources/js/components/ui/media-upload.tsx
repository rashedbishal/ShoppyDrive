import { Film, ImagePlus, X } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { cn, isVideoUrl } from '@/lib/utils';

interface MediaUploadProps {
    value: string | File | null;
    onChange: (file: File | null) => void;
    error?: string;
    hint?: string;
    className?: string;
    previewClassName?: string;
    /** Matches the server-side `max` validation rule (in KB) for this field. */
    maxSizeMb?: number;
}

const DEFAULT_MAX_SIZE_MB = 15;

function isVideoSource(value: string | File): boolean {
    if (value instanceof File) {
        return value.type.startsWith('video/');
    }

    return isVideoUrl(value);
}

export function MediaUpload({
    value,
    onChange,
    error,
    hint,
    className,
    previewClassName,
    maxSizeMb = DEFAULT_MAX_SIZE_MB,
}: MediaUploadProps): React.JSX.Element {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [sizeError, setSizeError] = useState<string | null>(null);
    const isVideo = value ? isVideoSource(value) : false;

    useEffect(() => {
        if (!value) {
            setPreview(null);
            return;
        }

        if (typeof value === 'string') {
            setPreview(value);
            return;
        }

        const objectUrl = URL.createObjectURL(value);
        setPreview(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [value]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        if (file.size > maxSizeMb * 1024 * 1024) {
            setSizeError(`That file is too large — the limit is ${maxSizeMb}MB.`);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }

        setSizeError(null);
        onChange(file);
    };

    const handleRemove = (e: React.MouseEvent) => {
        e.stopPropagation();
        e.preventDefault();
        setSizeError(null);
        onChange(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const displayError = sizeError || error;

    return (
        <div className={cn('w-full', className)}>
            <div
                className={cn(
                    'relative flex w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-all hover:bg-slate-50',
                    displayError ? 'border-red-500 bg-red-50' : 'border-slate-300 bg-white',
                    preview ? 'border-solid p-1' : 'p-6',
                    previewClassName || 'h-48'
                )}
                onClick={() => fileInputRef.current?.click()}
            >
                {preview ? (
                    <>
                        {isVideo ? (
                            <video
                                src={preview}
                                className="h-full w-full rounded-lg object-contain"
                                controls
                                muted
                                playsInline
                            />
                        ) : (
                            <img
                                src={preview}
                                alt="Upload preview"
                                className="h-full w-full rounded-lg object-contain"
                                onError={(e) => {
                                    (e.target as HTMLImageElement).style.display = 'none';
                                }}
                            />
                        )}
                        {isVideo && (
                            <div className="absolute left-2 top-2 rounded-md bg-black/60 p-1 text-white backdrop-blur-sm">
                                <Film className="size-3.5" />
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={handleRemove}
                            className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white backdrop-blur-sm transition-colors hover:bg-red-500"
                        >
                            <X className="size-4" />
                        </button>
                    </>
                ) : (
                    <div className="flex flex-col items-center gap-2 text-slate-600">
                        <div className="rounded-full bg-slate-100 p-3">
                            <ImagePlus className="size-6 text-slate-400" />
                        </div>
                        <p className="text-sm font-medium">Click to upload an image or video</p>
                        <p className="text-xs text-slate-600">JPG, PNG, WEBP, MP4, WEBM (max {maxSizeMb}MB)</p>
                    </div>
                )}

                <input
                    type="file"
                    ref={fileInputRef}
                    className="hidden"
                    accept="image/*,video/*"
                    onChange={handleFileChange}
                />
            </div>
            {hint && <p className="mt-1.5 text-xs text-text-secondary">{hint}</p>}
            {displayError && <p className="mt-1.5 text-xs text-red-500">{displayError}</p>}
        </div>
    );
}
