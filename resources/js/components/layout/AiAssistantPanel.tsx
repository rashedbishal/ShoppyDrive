import { AnimatePresence, motion } from 'framer-motion';
import { Bot, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { getXsrfToken } from '@/lib/csrf';
import { cn } from '@/lib/utils';

type ChatMessage = {
    role: 'user' | 'assistant';
    text: string;
};

const STORAGE_KEY = 'shoppydrive-ai-assistant-v2';

// Sent with every request so the (stateless) model has prior context —
// trimmed to the most recent turns so the payload doesn't grow without
// bound over a long conversation.
const MAX_HISTORY_MESSAGES = 20;

type StoredState = {
    messages: ChatMessage[];
};

function loadStoredState(): StoredState {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);

        return raw ? JSON.parse(raw) : { messages: [] };
    } catch {
        return { messages: [] };
    }
}

function saveStoredState(state: StoredState): void {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // Per-viewer convenience only — fine if it can't be saved.
    }
}

const WELCOME: ChatMessage = {
    role: 'assistant',
    text: 'Hi! I can help with product questions and order status. What can I help you with?',
};

export default function AiAssistantPanel({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}): React.JSX.Element | null {
    const [messages, setMessages] = useState<ChatMessage[]>(() => {
        const stored = loadStoredState();

        return stored.messages.length > 0 ? stored.messages : [WELCOME];
    });
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const listRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        listRef.current?.scrollTo({ top: listRef.current.scrollHeight });
    }, [messages, open]);

    const sendMessage = async () => {
        const text = input.trim();

        if (text === '' || sending) {
            return;
        }

        // Only real prior turns — not the local-only welcome message, which
        // the model was never a party to.
        const priorHistory = messages
            .filter((m) => m !== WELCOME)
            .slice(-MAX_HISTORY_MESSAGES)
            .map((m) => ({ role: m.role, content: m.text }));

        const nextMessages: ChatMessage[] = [
            ...messages,
            { role: 'user', text },
        ];
        setMessages(nextMessages);
        setInput('');
        setSending(true);

        try {
            const response = await fetch(route('assistant.chat'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    message: text,
                    history: priorHistory,
                }),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            const data = (await response.json()) as { reply: string };

            const withReply: ChatMessage[] = [
                ...nextMessages,
                { role: 'assistant', text: data.reply },
            ];
            setMessages(withReply);
            saveStoredState({ messages: withReply });
        } catch {
            setMessages([
                ...nextMessages,
                {
                    role: 'assistant',
                    text: "Sorry, I couldn't reach the assistant just now. Please try again in a moment.",
                },
            ]);
        } finally {
            setSending(false);
        }
    };

    return (
        <AnimatePresence>
            {open && (
                <motion.div
                    initial={{ opacity: 0, y: 24, scale: 0.95 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: 24, scale: 0.95 }}
                    transition={{
                        type: 'spring',
                        stiffness: 420,
                        damping: 32,
                    }}
                    className="fixed right-5 bottom-24 z-[80] flex h-[min(32rem,70vh)] w-[min(22rem,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200"
                >
                    <div className="flex items-center justify-between gap-2 bg-[var(--color-primary)] px-4 py-3 text-white">
                        <div className="flex items-center gap-2">
                            <Bot className="size-5" />
                            <span className="text-sm font-semibold">
                                Ask us anything
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Close assistant"
                            className="rounded-full p-1 transition-colors hover:bg-white/15"
                        >
                            <X className="size-4" />
                        </button>
                    </div>

                    <div
                        ref={listRef}
                        className="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-4 py-4"
                    >
                        {messages.map((message, index) => (
                            <div
                                key={index}
                                className={cn(
                                    'flex',
                                    message.role === 'user'
                                        ? 'justify-end'
                                        : 'justify-start',
                                )}
                            >
                                <p
                                    className={cn(
                                        'max-w-[85%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap',
                                        message.role === 'user'
                                            ? 'rounded-br-sm bg-[var(--color-primary)] text-white'
                                            : 'rounded-bl-sm bg-white text-slate-700 shadow-sm ring-1 ring-slate-200',
                                    )}
                                >
                                    {message.text}
                                </p>
                            </div>
                        ))}
                        {sending && (
                            <div className="flex justify-start">
                                <p className="rounded-2xl rounded-bl-sm bg-white px-3.5 py-2 text-sm text-slate-400 shadow-sm ring-1 ring-slate-200">
                                    Typing…
                                </p>
                            </div>
                        )}
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            void sendMessage();
                        }}
                        className="flex items-center gap-2 border-t border-slate-200 bg-white p-3"
                    >
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Ask about a product or order…"
                            disabled={sending}
                            className="h-10 flex-1 rounded-full border border-slate-200 px-4 text-sm focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                        />
                        <button
                            type="submit"
                            disabled={sending || input.trim() === ''}
                            aria-label="Send"
                            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary)] text-white disabled:opacity-40"
                        >
                            <Send className="size-4" />
                        </button>
                    </form>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
