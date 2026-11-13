import { ImagePlus, X } from 'lucide-react';
import React, { useRef, useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface ImageUploadProps {
    value: string | File | null;
    onChange: (file: File | null) => void;
    error?: string;
    hint?: string;
    className?: string;
    previewClassName?: string;
    /**
     * When set, files over this size are rejected here (before upload) and
     * the label shows the limit. Unset keeps the legacy "max 2MB" label,
     * which nothing enforces — the matching server rule lives per form.
     */
    maxSizeMb?: number;
}

export function ImageUpload({ value, onChange, error, hint, className, previewClassName, maxSizeMb }: ImageUploadProps): React.JSX.Element {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [sizeError, setSizeError] = useState<string | null>(null);
    const shownError = sizeError ?? error;

    useEffect(() => {
        if (!value) {
            setPreview(null);
            return;
        }

        if (typeof value === 'string') {
            setPreview(value);
        } else if (value instanceof File) {
            const objectUrl = URL.createObjectURL(value);
            setPreview(objectUrl);
            return () => URL.revokeObjectURL(objectUrl);
        }
    }, [value]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];

            if (maxSizeMb !== undefined && file.size > maxSizeMb * 1024 * 1024) {
                setSizeError(`That file is ${(file.size / 1024 / 1024).toFixed(1)}MB — the limit is ${maxSizeMb}MB.`);
                e.target.value = '';
                return;
            }

            setSizeError(null);
            onChange(file);
        }
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

    return (
        <div className={cn('w-full', className)}>
            <div
                className={cn(
                    'relative flex w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-all hover:bg-slate-50',
                    shownError ? 'border-red-500 bg-red-50' : 'border-slate-300 bg-white',
                    preview ? 'border-solid p-1' : 'p-6',
                    previewClassName || 'h-48'
                )}
                onClick={() => fileInputRef.current?.click()}
            >
                {preview ? (
                    <>
                        <img
                            src={preview}
                            alt="Upload preview"
                            className="h-full w-full rounded-lg object-contain"
                            onError={(e) => {
                                (e.target as HTMLImageElement).style.display = 'none';
                            }}
                        />
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
                        <p className="text-sm font-medium">Click to upload image</p>
                        <p className="text-xs text-slate-600">JPG, PNG, WEBP (max {maxSizeMb ?? 2}MB)</p>
                    </div>
                )}
                
                <input
                    type="file"
                    ref={fileInputRef}
                    className="hidden"
                    accept="image/*"
                    onChange={handleFileChange}
                />
            </div>
            {hint && <p className="mt-1.5 text-xs text-text-secondary">{hint}</p>}
            {shownError && <p className="mt-1.5 text-xs text-red-500">{shownError}</p>}
        </div>
    );
}
