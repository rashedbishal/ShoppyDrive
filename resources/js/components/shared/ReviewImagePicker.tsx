import { ImagePlus, X } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';

import {
    REVIEW_IMAGE_ACCEPT,
    REVIEW_IMAGE_STORED_MAX_SIDE,
    reviewImageProblem,
    reviewImageRulesText,
} from '@/lib/review-images';

interface ReviewImagePickerProps {
    files: File[];
    onChange: (files: File[]) => void;
    max: number;
    /** An error from the server for the photos, shown under the picker. */
    error?: string;
    disabled?: boolean;
}

/** Lets a customer choose photos for a review, with previews and the rules stated. */
export function ReviewImagePicker({
    files,
    onChange,
    max,
    error,
    disabled = false,
}: ReviewImagePickerProps): React.JSX.Element {
    const inputRef = useRef<HTMLInputElement>(null);
    const [problem, setProblem] = useState<string | null>(null);

    // One preview address per chosen file, released when the list changes.
    const previews = useMemo(
        () => files.map((file) => URL.createObjectURL(file)),
        [files],
    );

    useEffect(
        () => () => previews.forEach((url) => URL.revokeObjectURL(url)),
        [previews],
    );

    const choose = (event: React.ChangeEvent<HTMLInputElement>) => {
        const chosen = Array.from(event.target.files ?? []);

        // Allow choosing the same file again after removing it.
        event.target.value = '';

        const accepted: File[] = [];
        let message: string | null = null;

        for (const file of chosen) {
            const fileProblem = reviewImageProblem(file);

            if (fileProblem) {
                message = fileProblem;
                continue;
            }

            if (files.length + accepted.length >= max) {
                message = `You can add up to ${max} ${max === 1 ? 'photo' : 'photos'}.`;
                break;
            }

            accepted.push(file);
        }

        setProblem(message);

        if (accepted.length > 0) {
            onChange([...files, ...accepted]);
        }
    };

    const remove = (index: number) => {
        setProblem(null);
        onChange(files.filter((_, i) => i !== index));
    };

    const full = files.length >= max;

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs font-semibold tracking-wider text-[var(--color-text-muted)] uppercase">
                    Photos{' '}
                    <span className="font-normal normal-case">(optional)</span>
                </span>
                <span className="text-xs text-[var(--color-text-muted)]">
                    {files.length} / {max}
                </span>
            </div>

            <div className="flex flex-wrap gap-2">
                {previews.map((url, index) => (
                    <div
                        key={url}
                        className="relative h-20 w-20 overflow-hidden rounded-lg border border-[var(--color-border)]"
                    >
                        <img
                            src={url}
                            alt={`Selected photo ${index + 1}`}
                            className="h-full w-full object-cover"
                        />
                        <button
                            type="button"
                            onClick={() => remove(index)}
                            disabled={disabled}
                            aria-label={`Remove photo ${index + 1}`}
                            className="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80"
                        >
                            <X className="size-3" />
                        </button>
                    </div>
                ))}

                {!full && (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        disabled={disabled}
                        className="flex h-20 w-20 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-[var(--color-border)] text-[var(--color-text-secondary)] transition-colors hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
                    >
                        <ImagePlus className="size-5" />
                        <span className="text-[10px] font-semibold">
                            Add photo
                        </span>
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept={REVIEW_IMAGE_ACCEPT}
                multiple={max > 1}
                onChange={choose}
                className="hidden"
                aria-label="Add review photos"
            />

            <p className="text-[11px] leading-relaxed text-[var(--color-text-muted)]">
                {reviewImageRulesText(max)}. Large photos are resized to{' '}
                {REVIEW_IMAGE_STORED_MAX_SIDE} px on the longest side.
            </p>

            {(problem || error) && (
                <p className="text-xs text-red-500">{problem ?? error}</p>
            )}
        </div>
    );
}
