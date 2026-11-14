import { useHttp } from '@inertiajs/react';
import {
    Bold,
    Heading1,
    Heading2,
    Heading3,
    Image,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Strikethrough,
    Underline,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { CharCount } from '@/components/ui/char-count';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ImageUpload } from '@/components/ui/image-upload';
import { imageRecommendation } from '@/lib/upload-recommendations';

const TOOLBAR_ACTIONS = [
    { label: 'Bold', icon: Bold, command: 'bold' },
    { label: 'Italic', icon: Italic, command: 'italic' },
    { label: 'Underline', icon: Underline, command: 'underline' },
    { label: 'Strikethrough', icon: Strikethrough, command: 'strikeThrough' },
] as const;

/** The text a visitor would read, without the HTML markup. */
function visibleText(html: string): string {
    return (
        new DOMParser().parseFromString(html, 'text/html').body.textContent ??
        ''
    );
}

function InsertImageDialog({
    open,
    onOpenChange,
    onInsert,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onInsert: (url: string) => void;
}) {
    const [urlInput, setUrlInput] = useState('');
    const { data, setData, post, processing, errors } = useHttp({
        image: null as File | null,
    });

    const reset = (): void => {
        setData('image', null);
        setUrlInput('');
    };

    const handleOpenChange = (nextOpen: boolean): void => {
        if (!nextOpen) {
            reset();
        }

        onOpenChange(nextOpen);
    };

    const handleInsert = (): void => {
        if (data.image) {
            post(route('admin.content-images.store'), {
                onSuccess: (response) => {
                    onInsert((response as { url: string }).url);
                    reset();
                },
            });
        } else if (urlInput.trim()) {
            onInsert(urlInput.trim());
            reset();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent appearance="premium" className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Insert Image</DialogTitle>
                    <DialogDescription>
                        Upload an image, or paste a link to one that's already
                        online.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <ImageUpload
                        value={data.image}
                        onChange={(nextFile) => {
                            setData('image', nextFile);

                            if (nextFile) {
                                setUrlInput('');
                            }
                        }}
                        error={errors.image}
                        previewClassName="h-40 object-contain"
                        hint={imageRecommendation('up to 1200 px wide')}
                    />

                    <div className="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <div className="h-px flex-1 bg-slate-200" />
                        OR
                        <div className="h-px flex-1 bg-slate-200" />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">
                            Image URL
                        </label>
                        <input
                            maxLength={500}
                            type="url"
                            value={urlInput}
                            onChange={(e) => {
                                setUrlInput(e.target.value);

                                if (e.target.value) {
                                    setData('image', null);
                                }
                            }}
                            placeholder="https://example.com/image.jpg"
                            className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleInsert}
                        disabled={
                            processing || (!data.image && !urlInput.trim())
                        }
                        className="bg-brand text-brand-foreground hover:bg-brand-hover"
                    >
                        {processing ? 'Uploading…' : 'Insert Image'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function RichTextEditor({
    value,
    onChange,
    minHeight = 240,
    maxHeight = 480,
    maxLength,
    maxTextLength,
}: {
    value: string;
    onChange: (html: string) => void;
    minHeight?: number;
    /**
     * Height (px) the editing area grows to before it scrolls internally,
     * so a very long text doesn't stretch the page. The toolbar and the
     * counter stay in view outside the scrolling area.
     */
    maxHeight?: number;
    /**
     * Largest size of the saved HTML (markup included, as the server counts
     * it). Typing or pasting past it is undone, and a counter shows how much
     * is left.
     */
    maxLength?: number;
    /**
     * Largest number of visible characters (markup not counted), for short
     * rich text such as a section blurb. Typing or pasting past it is undone.
     */
    maxTextLength?: number;
}) {
    const editorRef = useRef<HTMLDivElement>(null);
    const isInitialized = useRef(false);
    const savedRangeRef = useRef<Range | null>(null);
    const [isImageDialogOpen, setIsImageDialogOpen] = useState(false);

    useEffect(() => {
        if (editorRef.current && !isInitialized.current) {
            editorRef.current.innerHTML = value;
            isInitialized.current = true;
        }
    }, [value]);

    /**
     * Toolbar controls like the native color picker and the image dialog
     * steal focus (and with it, the browser's text selection) away from the
     * contentEditable area. `execCommand` silently no-ops once that happens,
     * so we snapshot the selection before focus moves and restore it right
     * before issuing the command.
     */
    const saveSelection = useCallback(() => {
        const selection = window.getSelection();

        if (
            selection &&
            selection.rangeCount > 0 &&
            editorRef.current?.contains(selection.anchorNode)
        ) {
            savedRangeRef.current = selection.getRangeAt(0).cloneRange();
        } else {
            savedRangeRef.current = null;
        }
    }, []);

    const restoreSelection = useCallback(() => {
        const selection = window.getSelection();

        if (!selection) {
            return;
        }

        if (savedRangeRef.current) {
            selection.removeAllRanges();
            selection.addRange(savedRangeRef.current);
        } else if (editorRef.current) {
            const range = document.createRange();
            range.selectNodeContents(editorRef.current);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }, []);

    const execCmd = useCallback(
        (command: string, val?: string) => {
            document.execCommand(command, false, val);
            editorRef.current?.focus();

            if (editorRef.current) {
                onChange(editorRef.current.innerHTML);
            }
        },
        [onChange],
    );

    const applyColor = useCallback(
        (color: string) => {
            editorRef.current?.focus();
            restoreSelection();
            document.execCommand('foreColor', false, color);

            // `execCommand` just wrapped the selection in a new element,
            // which invalidates the `Range` we restored above — re-save the
            // (now valid) live selection so the next color pick in this same
            // picker session (still open, still dragging) has something
            // correct to restore instead of a stale, pre-mutation range.
            saveSelection();

            if (editorRef.current) {
                onChange(editorRef.current.innerHTML);
            }
        },
        [onChange, restoreSelection, saveSelection],
    );

    const insertImageUrl = useCallback(
        (url: string) => {
            editorRef.current?.focus();
            restoreSelection();
            document.execCommand('insertImage', false, url);

            if (editorRef.current) {
                onChange(editorRef.current.innerHTML);
            }

            setIsImageDialogOpen(false);
        },
        [onChange, restoreSelection],
    );

    const insertLink = useCallback(() => {
        const url = prompt('Enter link URL:');

        if (url) {
            execCmd('createLink', url);
        }
    }, [execCmd]);

    return (
        <>
            <div className="overflow-hidden rounded-lg border border-slate-200 focus-within:border-brand focus-within:ring-1 focus-within:ring-brand">
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
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd('formatBlock', 'h1');
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Heading1 className="size-3.5" />
                    </button>
                    <button
                        type="button"
                        title="Heading 2"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd('formatBlock', 'h2');
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Heading2 className="size-3.5" />
                    </button>
                    {/* DISC-05: needed for the FAQ (H2 = group, H3 = question). Sections whose purifier profile lacks h3 still turn it into a paragraph on save, as before. */}
                    <button
                        type="button"
                        title="Heading 3"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd('formatBlock', 'h3');
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Heading3 className="size-3.5" />
                    </button>
                    <button
                        type="button"
                        title="Quote"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd('formatBlock', 'blockquote');
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Quote className="size-3.5" />
                    </button>

                    <div className="mx-1 h-4 w-px bg-slate-200" />

                    {/* Lists */}
                    <button
                        type="button"
                        title="Bullet list"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd('insertUnorderedList');
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <List className="size-3.5" />
                    </button>
                    <button
                        type="button"
                        title="Numbered list"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            execCmd('insertOrderedList');
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <ListOrdered className="size-3.5" />
                    </button>

                    <div className="mx-1 h-4 w-px bg-slate-200" />

                    {/* Link & Image */}
                    <button
                        type="button"
                        title="Insert link"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            insertLink();
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Link2 className="size-3.5" />
                    </button>
                    <button
                        type="button"
                        title="Insert image"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            saveSelection();
                            setIsImageDialogOpen(true);
                        }}
                        className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <Image className="size-3.5" />
                    </button>

                    {/* Font color */}
                    <div className="mx-1 h-4 w-px bg-slate-200" />
                    <label
                        title="Text color"
                        onMouseDown={() => saveSelection()}
                        className="flex size-7 cursor-pointer items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                    >
                        <span className="text-xs font-bold">A</span>
                        <input
                            type="color"
                            className="sr-only"
                            onChange={(e) => applyColor(e.target.value)}
                        />
                    </label>
                </div>

                {/* Editable content */}
                <div
                    ref={editorRef}
                    contentEditable
                    suppressContentEditableWarning
                    onInput={() => {
                        if (editorRef.current) {
                            if (
                                maxLength !== undefined &&
                                editorRef.current.innerHTML.length > maxLength
                            ) {
                                document.execCommand('undo');

                                return;
                            }

                            if (
                                maxTextLength !== undefined &&
                                (editorRef.current.textContent ?? '').length >
                                    maxTextLength
                            ) {
                                document.execCommand('undo');

                                return;
                            }

                            onChange(editorRef.current.innerHTML);
                        }
                    }}
                    style={{
                        minHeight,
                        maxHeight: Math.max(minHeight, maxHeight),
                    }}
                    className="overflow-y-auto p-4 text-sm text-slate-800 focus:outline-none [&_a]:text-brand [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-slate-300 [&_blockquote]:pl-4 [&_blockquote]:text-slate-600 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_h3]:text-lg [&_h3]:font-semibold [&_img]:max-w-full [&_img]:rounded [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                />
                {maxTextLength !== undefined ? (
                    <div className="flex justify-end border-t border-slate-100 bg-slate-50 px-3 py-1">
                        <CharCount
                            value={visibleText(value)}
                            max={maxTextLength}
                        />
                    </div>
                ) : maxLength !== undefined ? (
                    <div className="flex justify-end border-t border-slate-100 bg-slate-50 px-3 py-1">
                        <CharCount value={value} max={maxLength} />
                    </div>
                ) : null}
            </div>

            <InsertImageDialog
                open={isImageDialogOpen}
                onOpenChange={setIsImageDialogOpen}
                onInsert={insertImageUrl}
            />
        </>
    );
}
