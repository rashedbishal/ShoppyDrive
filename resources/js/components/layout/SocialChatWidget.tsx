import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { MessageCircle, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/stores/uiStore';
import type { SharedPageProps } from '@/types';

function WhatsAppIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
        </svg>
    );
}

function MessengerIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
            <path d="M12 0C5.373 0 0 4.975 0 11.111c0 3.498 1.744 6.614 4.469 8.652V24l4.088-2.242c1.092.3 2.246.464 3.443.464 6.627 0 12-4.975 12-11.111S18.627 0 12 0zm1.191 14.963-3.055-3.26-5.963 3.26L10.732 8.1l3.131 3.26L19.752 8.1l-6.561 6.863z" />
        </svg>
    );
}

function buildWhatsAppUrl(number: string): string {
    const digits = number.replace(/\D/g, '');
    return `https://wa.me/${digits}`;
}

function buildMessengerUrl(link: string): string {
    if (link.startsWith('http://') || link.startsWith('https://')) {
        return link;
    }
    return `https://m.me/${link}`;
}

// Particles that burst outward when the widget opens
const BURST_PARTICLES = [
    { angle: 0,   dist: 44, size: 7,  color: '#c8102e' },
    { angle: 52,  dist: 36, size: 5,  color: '#ff6b35' },
    { angle: 108, dist: 46, size: 8,  color: '#c8102e' },
    { angle: 165, dist: 38, size: 5,  color: '#e8305a' },
    { angle: 220, dist: 44, size: 7,  color: '#c8102e' },
    { angle: 278, dist: 36, size: 5,  color: '#ff6b35' },
    { angle: 330, dist: 42, size: 6,  color: '#e8305a' },
];

function toXY(angle: number, dist: number) {
    const rad = (angle * Math.PI) / 180;
    return { x: Math.cos(rad) * dist, y: -Math.sin(rad) * dist };
}

type ChatItem = {
    key: string;
    label: string;
    href: string;
    bg: string;
    glow: string;
    shadow: string;
    icon: React.ReactNode;
};

export default function SocialChatWidget(): React.JSX.Element | null {
    const { settings } = usePage<SharedPageProps>().props;
    const [open, setOpen] = useState(false);
    const [burstKey, setBurstKey] = useState(0);
    // Step aside while the mobile menu or cart panel is open — it would sit on top of them.
    const isPanelOpen = useUiStore((state) => state.cartOpen || state.mobileMenuOpen);

    const items: ChatItem[] = [];

    if (settings.whatsapp_number) {
        items.push({
            key: 'whatsapp',
            label: 'WhatsApp',
            href: buildWhatsAppUrl(settings.whatsapp_number),
            bg: 'bg-[#25d366]',
            glow: 'rgba(37,211,102,0.55)',
            shadow: '0 0 28px 6px rgba(37,211,102,0.45)',
            icon: <WhatsAppIcon className="size-6 text-white" />,
        });
    }

    if (settings.messenger_link) {
        items.push({
            key: 'messenger',
            label: 'Messenger',
            href: buildMessengerUrl(settings.messenger_link),
            bg: 'bg-[#0084ff]',
            glow: 'rgba(0,132,255,0.55)',
            shadow: '0 0 28px 6px rgba(0,132,255,0.45)',
            icon: <MessengerIcon className="size-5 text-white" />,
        });
    }

    if (items.length === 0) return null;

    // Render reversed so bottom item (index 0 in reversed) appears first
    const displayItems = [...items].reverse();

    const handleToggle = () => {
        if (!open) setBurstKey((k) => k + 1);
        setOpen((v) => !v);
    };

    return (
        <div
            className={cn(
                'fixed bottom-6 right-5 z-[70] flex flex-col items-end gap-3 transition-[opacity,transform] duration-200 ease-out',
                isPanelOpen && 'pointer-events-none translate-y-3 opacity-0',
            )}
            aria-hidden={isPanelOpen || undefined}
            inert={isPanelOpen}
        >
            {/* Chat option rows */}
            <AnimatePresence>
                {open &&
                    displayItems.map((item, i) => (
                        <motion.div
                            key={item.key}
                            className="flex items-center gap-3"
                            initial={{ opacity: 0, x: 30, scale: 0.3, rotate: -80, filter: 'blur(12px)' }}
                            animate={{
                                opacity: 1,
                                x: 0,
                                scale: 1,
                                rotate: 0,
                                filter: 'blur(0px)',
                                transition: {
                                    delay: i * 0.11,
                                    scale:  { type: 'spring', stiffness: 520, damping: 13, delay: i * 0.11 },
                                    rotate: { type: 'spring', stiffness: 420, damping: 14, delay: i * 0.11 },
                                    x:      { type: 'spring', stiffness: 480, damping: 16, delay: i * 0.11 },
                                    opacity: { duration: 0.18, delay: i * 0.11 },
                                    filter:  { duration: 0.22, delay: i * 0.11 },
                                },
                            }}
                            exit={{
                                opacity: 0,
                                x: 24,
                                scale: 0.1,
                                rotate: 70,
                                filter: 'blur(10px)',
                                transition: {
                                    delay: (displayItems.length - 1 - i) * 0.07,
                                    duration: 0.22,
                                    ease: [0.4, 0, 1, 1],
                                },
                            }}
                        >
                            {/* Label */}
                            <motion.span
                                initial={{ opacity: 0, x: 18, scale: 0.8 }}
                                animate={{ opacity: 1, x: 0, scale: 1 }}
                                exit={{ opacity: 0, x: 12, scale: 0.85 }}
                                transition={{
                                    delay: i * 0.11 + 0.18,
                                    type: 'spring',
                                    stiffness: 480,
                                    damping: 20,
                                }}
                                className="select-none rounded-full bg-white px-3.5 py-1.5 text-xs font-semibold tracking-wide text-slate-700 shadow-lg ring-1 ring-slate-200"
                            >
                                {item.label}
                            </motion.span>

                            {/* Button + glow ring wrapper */}
                            <div className="relative flex items-center justify-center">
                                {/* Impact glow ring — plays once on mount */}
                                <motion.span
                                    className="absolute inset-0 rounded-full"
                                    style={{ backgroundColor: item.glow }}
                                    initial={{ scale: 0.7, opacity: 0 }}
                                    animate={{ scale: 3, opacity: [0, 0.9, 0] }}
                                    transition={{
                                        delay: i * 0.11 + 0.22,
                                        duration: 0.55,
                                        ease: 'easeOut',
                                    }}
                                />

                                <motion.a
                                    href={item.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label={`Chat on ${item.label}`}
                                    whileHover={{
                                        scale: 1.18,
                                        boxShadow: item.shadow,
                                        transition: { type: 'spring', stiffness: 500, damping: 18 },
                                    }}
                                    whileTap={{ scale: 0.88 }}
                                    className={cn(
                                        'relative flex size-12 items-center justify-center rounded-full shadow-xl',
                                        item.bg,
                                    )}
                                >
                                    {item.icon}
                                </motion.a>
                            </div>
                        </motion.div>
                    ))}
            </AnimatePresence>

            {/* Main toggle button */}
            <div className="relative flex items-center justify-center">
                {/* Sonar pulse rings — only when closed */}
                <AnimatePresence>
                    {!open && (
                        <>
                            {[0, 0.9, 1.8].map((delay, i) => (
                                <motion.span
                                    key={`sonar-${i}`}
                                    className="absolute inset-0 rounded-full bg-[var(--color-primary)]"
                                    animate={{
                                        scale:   [1, 1.95],
                                        opacity: [0, 0.28, 0],
                                    }}
                                    transition={{
                                        duration: 2.7,
                                        delay,
                                        repeat: Infinity,
                                        ease: [0.25, 0.46, 0.45, 0.94],
                                        times: [0, 0.18, 1],
                                    }}
                                />
                            ))}
                        </>
                    )}
                </AnimatePresence>

                {/* Burst particles — fire once per open */}
                <AnimatePresence>
                    {open &&
                        BURST_PARTICLES.map((p, i) => {
                            const { x, y } = toXY(p.angle, p.dist);
                            return (
                                <motion.span
                                    key={`p-${burstKey}-${i}`}
                                    className="pointer-events-none absolute rounded-full"
                                    style={{
                                        width: p.size,
                                        height: p.size,
                                        backgroundColor: p.color,
                                        left: '50%',
                                        top: '50%',
                                        marginLeft: -p.size / 2,
                                        marginTop: -p.size / 2,
                                    }}
                                    initial={{ x: 0, y: 0, opacity: 1, scale: 1 }}
                                    animate={{ x, y, opacity: 0, scale: 0 }}
                                    transition={{
                                        duration: 0.45,
                                        delay: i * 0.028,
                                        ease: [0.2, 0.8, 0.4, 1],
                                    }}
                                />
                            );
                        })}
                </AnimatePresence>

                {/* The button itself */}
                <motion.button
                    type="button"
                    onClick={handleToggle}
                    aria-label={open ? 'Close chat options' : 'Open chat options'}
                    animate={{
                        rotate: open ? 180 : 0,
                        scale: open ? 1 : [1, 1.045, 1],
                    }}
                    transition={{
                        rotate: { type: 'spring', stiffness: 380, damping: 22 },
                        scale: open
                            ? { duration: 0.15 }
                            : { duration: 2.8, repeat: Infinity, ease: 'easeInOut' },
                    }}
                    whileHover={{ scale: 1.1, transition: { type: 'spring', stiffness: 500, damping: 18 } }}
                    whileTap={{ scale: 0.88 }}
                    className={cn(
                        'relative flex size-14 items-center justify-center rounded-full shadow-2xl transition-colors duration-300',
                        open ? 'bg-slate-800' : 'bg-[var(--color-primary)]',
                    )}
                >
                    <AnimatePresence mode="wait" initial={false}>
                        {open ? (
                            <motion.span
                                key="x-icon"
                                initial={{ opacity: 0, rotate: -90, scale: 0.4 }}
                                animate={{ opacity: 1, rotate: 0, scale: 1 }}
                                exit={{ opacity: 0, rotate: 90, scale: 0.3 }}
                                transition={{ type: 'spring', stiffness: 480, damping: 20 }}
                                className="flex items-center justify-center"
                            >
                                <X className="size-6 text-white" />
                            </motion.span>
                        ) : (
                            <motion.span
                                key="chat-icon"
                                initial={{ opacity: 0, rotate: 90, scale: 0.4 }}
                                animate={{ opacity: 1, rotate: 0, scale: 1 }}
                                exit={{ opacity: 0, rotate: -90, scale: 0.3 }}
                                transition={{ type: 'spring', stiffness: 480, damping: 20 }}
                                className="flex items-center justify-center"
                            >
                                <MessageCircle className="size-6 text-white" />
                            </motion.span>
                        )}
                    </AnimatePresence>
                </motion.button>
            </div>
        </div>
    );
}
