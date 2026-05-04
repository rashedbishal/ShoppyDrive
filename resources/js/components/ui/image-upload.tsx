import { ImagePlus, X } from 'lucide-react';
import React, { useRef, useState, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface ImageUploadProps {
    value: string | File | null;
    onChange: (file: File | null) => void;
    error?: string;
    className?: string;
    previewClassName?: string;
}

export function ImageUpload({ value, onChange, error, className, previewClassName }: ImageUploadProps): React.JSX.Element {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

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
            onChange(e.target.files[0]);
        }
    };

    const handleRemove = (e: React.MouseEvent) => {
        e.stopPropagation();
        e.preventDefault();
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
                    error ? 'border-red-500 bg-red-50' : 'border-slate-300 bg-white',
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
                    <div className="flex flex-col items-center gap-2 text-slate-500">
                        <div className="rounded-full bg-slate-100 p-3">
                            <ImagePlus className="size-6 text-slate-400" />
                        </div>
                        <p className="text-sm font-medium">Click to upload image</p>
                        <p className="text-xs text-slate-400">JPG, PNG, WEBP (max 2MB)</p>
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
            {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
        </div>
    );
}
