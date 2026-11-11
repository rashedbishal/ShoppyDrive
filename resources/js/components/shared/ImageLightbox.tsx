import { ChevronLeft, ChevronRight } from 'lucide-react';
import React from 'react';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

interface ImageLightboxProps {
    images: string[];
    /** Index of the photo being shown, or null when closed. */
    index: number | null;
    onIndexChange: (index: number | null) => void;
    label?: string;
}

/** A simple full-size viewer for a set of photos, with previous / next. */
export function ImageLightbox({
    images,
    index,
    onIndexChange,
    label = 'Photo',
}: ImageLightboxProps): React.JSX.Element {
    const open = index !== null && images[index] !== undefined;
    const go = (step: number) => {
        if (index === null) {
            return;
        }

        onIndexChange((index + step + images.length) % images.length);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => !value && onIndexChange(null)}
        >
            <DialogContent className="max-w-[min(95vw,64rem)] gap-3 p-3 sm:max-w-[min(95vw,64rem)]">
                <DialogTitle className="sr-only">{label}</DialogTitle>
                <DialogDescription className="sr-only">
                    {index !== null
                        ? `${label} ${index + 1} of ${images.length}`
                        : label}
                </DialogDescription>
                {open && (
                    <div className="relative flex items-center justify-center">
                        <img
                            src={images[index as number]}
                            alt={`${label} ${(index as number) + 1} of ${images.length}`}
                            className="max-h-[80vh] w-auto max-w-full rounded-lg object-contain"
                        />
                        {images.length > 1 && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => go(-1)}
                                    aria-label="Previous photo"
                                    className="absolute left-2 flex size-9 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                >
                                    <ChevronLeft className="size-5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => go(1)}
                                    aria-label="Next photo"
                                    className="absolute right-2 flex size-9 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                >
                                    <ChevronRight className="size-5" />
                                </button>
                            </>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
