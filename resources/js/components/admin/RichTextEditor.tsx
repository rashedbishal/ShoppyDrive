import {
    Bold,
    Heading1,
    Heading2,
    Image,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Strikethrough,
    Underline,
} from 'lucide-react';
import { useCallback, useEffect, useRef } from 'react';

const TOOLBAR_ACTIONS = [
    { label: 'Bold', icon: Bold, command: 'bold' },
    { label: 'Italic', icon: Italic, command: 'italic' },
    { label: 'Underline', icon: Underline, command: 'underline' },
    { label: 'Strikethrough', icon: Strikethrough, command: 'strikeThrough' },
] as const;

export function RichTextEditor({
    value,
    onChange,
    minHeight = 240,
}: {
    value: string;
    onChange: (html: string) => void;
    minHeight?: number;
}) {
    const editorRef = useRef<HTMLDivElement>(null);
    const isInitialized = useRef(false);

    useEffect(() => {
        if (editorRef.current && !isInitialized.current) {
            editorRef.current.innerHTML = value;
            isInitialized.current = true;
        }
    }, [value]);

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

    const insertImage = useCallback(() => {
        const url = prompt('Enter image URL:');

        if (url) {
execCmd('insertImage', url);
}
    }, [execCmd]);

    const insertLink = useCallback(() => {
        const url = prompt('Enter link URL:');

        if (url) {
execCmd('createLink', url);
}
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
                        insertImage();
                    }}
                    className="flex size-7 items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
                    <Image className="size-3.5" />
                </button>

                {/* Font color */}
                <div className="mx-1 h-4 w-px bg-slate-200" />
                <label
                    title="Text color"
                    className="flex size-7 cursor-pointer items-center justify-center rounded text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                >
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
                    if (editorRef.current) {
onChange(editorRef.current.innerHTML);
}
                }}
                style={{ minHeight }}
                className="p-4 text-sm text-slate-800 focus:outline-none [&_a]:text-[#c8102e] [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-slate-300 [&_blockquote]:pl-4 [&_blockquote]:text-slate-500 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_img]:max-w-full [&_img]:rounded [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
            />
        </div>
    );
}
