/**
 * DISC-65: Bangla for landing pages (and their payment and confirmation
 * pages). The admin turns Bangla on per page and picks the language a
 * visitor sees first; the visitor's own choice is then remembered on their
 * device, per landing page. English text is never changed by any of this —
 * every English string below is the one the pages showed before.
 */
import {
    createElement,
    Fragment,
    useCallback,
    useEffect,
    useSyncExternalStore,
} from 'react';
import type { ReactNode } from 'react';
import { formatCurrency } from '@/lib/utils';

export type LandingLanguage = 'en' | 'bn';

/** What a landing, payment or confirmation page knows about its languages. */
export type LandingLanguageSettings = {
    slug: string;
    bangla_enabled: boolean;
    default_language: LandingLanguage;
};

const DICTIONARY = {
    // Language switch
    language: { en: 'Language', bn: 'ভাষা' },

    // Header, hero, details
    order_now: { en: 'Order Now', bn: 'এখনই অর্ডার করুন' },
    from_prefix: { en: 'From', bn: '' },
    from_suffix: { en: '', bn: 'থেকে শুরু' },
    percent_off: { en: '{n}% OFF', bn: '{n}% ছাড়' },
    show_image: { en: 'Show image {n}', bn: '{n} নম্বর ছবি দেখুন' },
    show_video: { en: 'Show video {n}', bn: '{n} নম্বর ভিডিও দেখুন' },
    choose_your_set: { en: 'Choose your set', bn: 'আপনার সেট বেছে নিন' },
    continue_to_order: {
        en: 'Continue to order ({n})',
        bn: 'অর্ডারে এগিয়ে যান ({n})',
    },

    // Delivery section
    free_delivery_product: {
        en: '✓ Free delivery on this product',
        bn: '✓ এই পণ্যে ফ্রি ডেলিভারি',
    },
    inside_dhaka_rate: { en: 'Inside Dhaka City:', bn: 'ঢাকা সিটির ভিতরে:' },
    outside_dhaka_rate: { en: 'Outside Dhaka:', bn: 'ঢাকার বাইরে:' },

    // Option cards
    combo: { en: 'Combo', bn: 'কম্বো' },
    out_of_stock: { en: 'Out of stock', bn: 'স্টক শেষ' },
    only_left: { en: 'Only {n} left', bn: 'মাত্র {n}টি বাকি' },
    save_amount: { en: 'Save {amount}', bn: '{amount} সাশ্রয়' },
    decrease_item: { en: 'Decrease {name}', bn: '{name} কমান' },
    increase_item: { en: 'Increase {name}', bn: '{name} বাড়ান' },
    option: { en: 'Option', bn: 'অপশন' },

    // Order section
    order_now_eyebrow: { en: 'Order now', bn: 'এখনই অর্ডার করুন' },
    select_your_order: { en: 'Select your order', bn: 'আপনার অর্ডার বেছে নিন' },
    choose_your_options: {
        en: 'Choose your options',
        bn: 'আপনার অপশন বেছে নিন',
    },
    quantity: { en: 'Quantity', bn: 'পরিমাণ' },
    qty_of_max: {
        en: '{qty} of {max} max',
        bn: 'সর্বোচ্চ {max}টির মধ্যে {qty}টি',
    },
    decrease_quantity: { en: 'Decrease quantity', bn: 'পরিমাণ কমান' },
    increase_quantity: { en: 'Increase quantity', bn: 'পরিমাণ বাড়ান' },
    choose_option_error: {
        en: 'Choose at least one option and a quantity.',
        bn: 'অন্তত একটি অপশন ও পরিমাণ বেছে নিন।',
    },
    price_summary: { en: 'Price summary', bn: 'মূল্যের বিবরণ' },
    pick_option_hint: {
        en: 'Pick an option on the left to see your total.',
        bn: 'মোট দাম দেখতে বাঁ দিক থেকে একটি অপশন বেছে নিন।',
    },
    subtotal: { en: 'Subtotal', bn: 'সাবটোটাল' },
    you_save: { en: 'You save', bn: 'আপনার সাশ্রয়' },
    delivery: { en: 'Delivery', bn: 'ডেলিভারি চার্জ' },
    free: { en: 'Free', bn: 'ফ্রি' },
    pick_area_below: {
        en: 'Pick your area below',
        bn: 'নিচে আপনার এলাকা বেছে নিন',
    },
    total: { en: 'Total', bn: 'সর্বমোট' },
    delivery_details: { en: 'Delivery details', bn: 'ডেলিভারির তথ্য' },
    full_name: { en: 'Full Name', bn: 'পুরো নাম' },
    full_name_placeholder: { en: 'Your full name', bn: 'আপনার পুরো নাম' },
    phone_number: { en: 'Phone Number', bn: 'মোবাইল নম্বর' },
    email: { en: 'Email', bn: 'ইমেইল' },
    optional: { en: '(optional)', bn: '(ঐচ্ছিক)' },
    address: { en: 'Address', bn: 'ঠিকানা' },
    address_placeholder: { en: 'House, road, area', bn: 'বাসা, রাস্তা, এলাকা' },
    district: { en: 'District', bn: 'জেলা' },
    select_district: { en: 'Select district', bn: 'জেলা বেছে নিন' },
    area: { en: 'Area', bn: 'এলাকা' },
    select_area: { en: 'Select area', bn: 'এলাকা বেছে নিন' },
    inside_dhaka_city: { en: 'Inside Dhaka City', bn: 'ঢাকা সিটির ভিতরে' },
    outside_dhaka_city: { en: 'Outside Dhaka City', bn: 'ঢাকা সিটির বাইরে' },
    city_upazila: { en: 'City / Upazila', bn: 'শহর / উপজেলা' },
    city_placeholder: { en: 'City or upazila', bn: 'শহর বা উপজেলা' },
    order_notes: { en: 'Order notes', bn: 'অর্ডার নোট' },
    notes_placeholder: {
        en: 'Anything we should know? (optional)',
        bn: 'আমাদের কিছু জানাতে চান? (ঐচ্ছিক)',
    },
    payment_method: { en: 'Payment Method', bn: 'পেমেন্টের ধরন' },
    cash_on_delivery: { en: 'Cash on Delivery', bn: 'ক্যাশ অন ডেলিভারি' },
    mobile_banking: {
        en: 'Mobile Banking (Bkash / Nagad)',
        bn: 'মোবাইল ব্যাংকিং (বিকাশ / নগদ)',
    },
    temporarily_unavailable: {
        en: '— temporarily unavailable',
        bn: '— সাময়িকভাবে বন্ধ',
    },
    placing_order: { en: 'Placing Order…', bn: 'অর্ডার করা হচ্ছে…' },
    place_order: { en: 'Place Order · {amount}', bn: 'অর্ডার করুন · {amount}' },
    details_privacy: {
        en: '🔒 Your details are only used to deliver this order.',
        bn: '🔒 আপনার তথ্য শুধু এই অর্ডারটি পৌঁছে দিতে ব্যবহার করা হবে।',
    },

    // Testimonials, footer
    customer_testimonials: {
        en: 'Customer testimonials',
        bn: 'গ্রাহকদের মতামত',
    },
    contact_us: { en: 'Contact Us', bn: 'যোগাযোগ' },
    all_rights_reserved: {
        en: '. All rights reserved.',
        bn: '। সর্বস্বত্ব সংরক্ষিত।',
    },

    // bKash/Nagad payment (inline and on its own page)
    bkash: { en: 'Bkash', bn: 'বিকাশ' },
    nagad: { en: 'Nagad', bn: 'নগদ' },
    back_to_order_form: {
        en: 'Back to order form',
        bn: 'অর্ডার ফর্মে ফিরে যান',
    },
    complete_payment_via: {
        en: 'Complete Payment via {provider}',
        bn: '{provider}-এ পেমেন্ট সম্পন্ন করুন',
    },
    pay_via: { en: 'Pay via {provider}', bn: '{provider}-এ পেমেন্ট করুন' },
    complete_payment_title: {
        en: 'Complete Payment',
        bn: 'পেমেন্ট সম্পন্ন করুন',
    },
    order_number: { en: 'Order #{number}', bn: 'অর্ডার #{number}' },
    send_exactly: { en: 'Send exactly', bn: 'ঠিক এই পরিমাণ পাঠান' },
    to_this_number: {
        en: 'to this {provider} number',
        bn: 'এই {provider} নম্বরে',
    },
    send_money_personal: {
        en: 'Send Money → Personal',
        bn: 'সেন্ড মানি → পার্সোনাল',
    },
    step_open_app: {
        en: 'Open your {provider} app',
        bn: 'আপনার {provider} অ্যাপ খুলুন',
    },
    step_send_money: {
        en: 'Go to **Send Money** and enter the number above',
        bn: '**সেন্ড মানি**-তে গিয়ে উপরের নম্বরটি দিন',
    },
    step_send_exactly: {
        en: 'Send exactly **{amount}**',
        bn: 'ঠিক **{amount}** পাঠান',
    },
    step_copy_trx: {
        en: 'Copy the **Transaction ID** from the confirmation SMS',
        bn: 'কনফার্মেশন এসএমএস থেকে **ট্রানজেকশন আইডি** কপি করুন',
    },
    step_paste_confirm: {
        en: 'Paste it below and click Confirm',
        bn: 'নিচে পেস্ট করে কনফার্ম চাপুন',
    },
    step_enter_below: { en: 'Enter it below', bn: 'নিচে আইডিটি লিখুন' },
    transaction_id: { en: 'Transaction ID', bn: 'ট্রানজেকশন আইডি' },
    transaction_id_placeholder: {
        en: 'Transaction ID from SMS…',
        bn: 'এসএমএস-এর ট্রানজেকশন আইডি…',
    },
    transaction_id_example: {
        en: 'e.g. 8G3H2K9P...',
        bn: 'যেমন 8G3H2K9P...',
    },
    verifying: { en: 'Verifying…', bn: 'যাচাই করা হচ্ছে…' },
    confirm_payment: { en: 'Confirm Payment', bn: 'পেমেন্ট কনফার্ম করুন' },
    confirmed_once_verified: {
        en: 'Your order will be confirmed once we verify the transaction.',
        bn: 'ট্রানজেকশন যাচাই হলেই আপনার অর্ডার কনফার্ম হবে।',
    },

    // Confirmation page
    order_confirmed_title: {
        en: 'Order Confirmed',
        bn: 'অর্ডার কনফার্ম হয়েছে',
    },
    order_placed: { en: 'Order Placed!', bn: 'অর্ডার সম্পন্ন হয়েছে!' },
    thank_you: {
        en: 'Thank you, {name}! Your order has been received.',
        bn: 'ধন্যবাদ, {name}! আপনার অর্ডারটি আমরা পেয়েছি।',
    },
    payment_confirmed: {
        en: '✓ Payment confirmed',
        bn: '✓ পেমেন্ট কনফার্ম হয়েছে',
    },
    cod_pay_on_receive: {
        en: '⏳ Cash on Delivery — pay when you receive your order',
        bn: '⏳ ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে টাকা দিন',
    },
    order_summary: { en: 'Order Summary', bn: 'অর্ডারের সারসংক্ষেপ' },
    delivery_address: { en: 'Delivery Address', bn: 'ডেলিভারির ঠিকানা' },
    bangladesh: { en: 'Bangladesh', bn: 'বাংলাদেশ' },
    need_help: {
        en: 'Need help with your order?',
        bn: 'অর্ডার নিয়ে সাহায্য দরকার?',
    },
    download_invoice: {
        en: 'Download Invoice (PDF)',
        bn: 'ইনভয়েস ডাউনলোড করুন (PDF)',
    },
    invoice_started: {
        en: "Your invoice download has started automatically. Use the button above if it didn't.",
        bn: 'আপনার ইনভয়েস স্বয়ংক্রিয়ভাবে ডাউনলোড শুরু হয়েছে। না হলে উপরের বাটনটি ব্যবহার করুন।',
    },
} as const satisfies Record<string, Record<LandingLanguage, string>>;

export type LandingTextKey = keyof typeof DICTIONARY;

/** Bangla names for the district values the landing pages use (both spellings the app uses). */
const DISTRICTS_BN: Record<string, string> = {
    Bagerhat: 'বাগেরহাট',
    Bandarban: 'বান্দরবান',
    Barguna: 'বরগুনা',
    Barisal: 'বরিশাল',
    Barishal: 'বরিশাল',
    Bhola: 'ভোলা',
    Bogura: 'বগুড়া',
    Brahmanbaria: 'ব্রাহ্মণবাড়িয়া',
    Chandpur: 'চাঁদপুর',
    Chapainawabganj: 'চাঁপাইনবাবগঞ্জ',
    'Chapai Nawabganj': 'চাঁপাইনবাবগঞ্জ',
    Chattogram: 'চট্টগ্রাম',
    Chuadanga: 'চুয়াডাঙ্গা',
    "Cox's Bazar": 'কক্সবাজার',
    Cumilla: 'কুমিল্লা',
    Dhaka: 'ঢাকা',
    Dinajpur: 'দিনাজপুর',
    Faridpur: 'ফরিদপুর',
    Feni: 'ফেনী',
    Gaibandha: 'গাইবান্ধা',
    Gazipur: 'গাজীপুর',
    Gopalganj: 'গোপালগঞ্জ',
    Habiganj: 'হবিগঞ্জ',
    Jamalpur: 'জামালপুর',
    Jashore: 'যশোর',
    Jhalokathi: 'ঝালকাঠি',
    Jhalokati: 'ঝালকাঠি',
    Jhenaidah: 'ঝিনাইদহ',
    Joypurhat: 'জয়পুরহাট',
    Khagrachhari: 'খাগড়াছড়ি',
    Khulna: 'খুলনা',
    Kishoreganj: 'কিশোরগঞ্জ',
    Kurigram: 'কুড়িগ্রাম',
    Kushtia: 'কুষ্টিয়া',
    Lakshmipur: 'লক্ষ্মীপুর',
    Lalmonirhat: 'লালমনিরহাট',
    Madaripur: 'মাদারীপুর',
    Magura: 'মাগুরা',
    Manikganj: 'মানিকগঞ্জ',
    Meherpur: 'মেহেরপুর',
    Moulvibazar: 'মৌলভীবাজার',
    Munshiganj: 'মুন্সিগঞ্জ',
    Mymensingh: 'ময়মনসিংহ',
    Naogaon: 'নওগাঁ',
    Narail: 'নড়াইল',
    Narayanganj: 'নারায়ণগঞ্জ',
    Narsingdi: 'নরসিংদী',
    Natore: 'নাটোর',
    Netrokona: 'নেত্রকোণা',
    Nilphamari: 'নীলফামারী',
    Noakhali: 'নোয়াখালী',
    Pabna: 'পাবনা',
    Panchagarh: 'পঞ্চগড়',
    Patuakhali: 'পটুয়াখালী',
    Pirojpur: 'পিরোজপুর',
    Rajbari: 'রাজবাড়ী',
    Rajshahi: 'রাজশাহী',
    Rangamati: 'রাঙ্গামাটি',
    Rangpur: 'রংপুর',
    Satkhira: 'সাতক্ষীরা',
    Shariatpur: 'শরীয়তপুর',
    Sherpur: 'শেরপুর',
    Sirajganj: 'সিরাজগঞ্জ',
    Sunamganj: 'সুনামগঞ্জ',
    Sylhet: 'সিলেট',
    Tangail: 'টাঙ্গাইল',
    Thakurgaon: 'ঠাকুরগাঁও',
};

/** The two Dhaka "city" values the order form submits. */
const CITIES_BN: Record<string, string> = {
    'Inside Dhaka City Corporation': 'ঢাকা সিটি কর্পোরেশনের ভিতরে',
    'Outside Dhaka City Corporation': 'ঢাকা সিটি কর্পোরেশনের বাইরে',
};

const BANGLA_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

/** "1,250.00" → "১,২৫০.০০"; every other character is left as it is. */
export function toBanglaDigits(value: string | number): string {
    return String(value).replace(/[0-9]/g, (d) => BANGLA_DIGITS[Number(d)]);
}

/** True when rich text (HTML) has no words in it, e.g. an empty editor's "<p></p>". */
function isBlank(value: unknown): boolean {
    if (typeof value !== 'string') {
        return true;
    }

    return (
        value
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/g, ' ')
            .trim() === '' && !/<img\b/i.test(value)
    );
}

/**
 * The Bangla web font, loaded only on landing pages that offer Bangla and
 * applied only to Bangla characters. A separate family name from the
 * site-wide `Kalpurush` (.ttf) so nothing outside Bangla mode changes.
 */
export const BANGLA_FONT_CSS = `
    @font-face {
        font-family: 'Kalpurush Landing';
        src: url('/fonts/kalpurush.woff2') format('woff2'), url('/fonts/Kalpurush.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
        unicode-range: U+0980-U+09FF;
    }
`;

/** Bangla mode's font stack: Kalpurush first for Bangla characters, then the page's usual fonts. */
export const BANGLA_FONT_FAMILY =
    "'Kalpurush Landing', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";

function storageKey(slug: string): string {
    return `lp-lang:${slug}`;
}

/**
 * This tab's choices, so switching still works where browser storage is
 * blocked (it is then just not remembered after the tab closes).
 */
const chosenThisTab = new Map<string, LandingLanguage>();
const languageListeners = new Set<() => void>();

function readStoredLanguage(slug: string): LandingLanguage | null {
    try {
        const stored = window.localStorage.getItem(storageKey(slug));

        if (stored === 'en' || stored === 'bn') {
            return stored;
        }
    } catch {
        // Blocked storage: fall back to this tab's choice below.
    }

    return chosenThisTab.get(slug) ?? null;
}

function storeLanguage(slug: string, language: LandingLanguage): void {
    chosenThisTab.set(slug, language);

    try {
        window.localStorage.setItem(storageKey(slug), language);
    } catch {
        // Private mode or blocked storage: the choice just isn't remembered.
    }

    languageListeners.forEach((listener) => listener());
}

/** Also follows a switch made in another tab of the same landing page. */
function subscribeToLanguage(listener: () => void): () => void {
    languageListeners.add(listener);
    window.addEventListener('storage', listener);

    return () => {
        languageListeners.delete(listener);
        window.removeEventListener('storage', listener);
    };
}

export type LandingText = {
    lang: LandingLanguage;
    isBangla: boolean;
    /** Whether this page offers Bangla at all (shows the switch). */
    banglaEnabled: boolean;
    setLang: (language: LandingLanguage) => void;
    /** A built-in text, with `{name}` placeholders filled in. */
    t: (
        key: LandingTextKey,
        params?: Record<string, string | number>,
    ) => string;
    /** Like t(), but `**bold**` parts become <strong>. */
    rich: (
        key: LandingTextKey,
        params?: Record<string, string | number>,
    ) => ReactNode;
    /** The admin's Bangla text in Bangla mode when there is some, otherwise the English. */
    pick: <T extends string | null | undefined>(
        english: T,
        bangla: unknown,
    ) => T | string;
    /** ৳ amount, with Bangla digits in Bangla mode. */
    money: (amount: number) => string;
    /** A number, with Bangla digits in Bangla mode. */
    num: (value: string | number) => string;
    district: (value: string) => string;
    city: (value: string) => string;
};

/**
 * The visitor's language for one landing page. The first render always uses
 * the admin's default (the server renders it too); a choice the visitor made
 * earlier on this device is applied straight after.
 */
export function useLandingLanguage(
    settings: LandingLanguageSettings | null | undefined,
): LandingText {
    const enabled = Boolean(settings?.bangla_enabled);
    const initial: LandingLanguage =
        enabled && settings?.default_language === 'bn' ? 'bn' : 'en';
    const slug = settings?.slug ?? '';
    // The server (and hydration) always use the admin's default; the
    // visitor's remembered choice takes over straight after.
    const stored = useSyncExternalStore(
        subscribeToLanguage,
        () => (enabled && slug !== '' ? readStoredLanguage(slug) : null),
        () => null,
    );
    const lang: LandingLanguage = stored ?? initial;

    // The document's language follows the page, for screen readers and fonts.
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const previous = document.documentElement.lang;
        document.documentElement.lang = lang;

        return () => {
            document.documentElement.lang = previous;
        };
    }, [enabled, lang]);

    const setLang = useCallback(
        (language: LandingLanguage) => {
            if (slug !== '') {
                storeLanguage(slug, language);
            }
        },
        [slug],
    );

    const isBangla = enabled && lang === 'bn';
    const num = (value: string | number): string =>
        isBangla ? toBanglaDigits(value) : String(value);

    const t = (
        key: LandingTextKey,
        params: Record<string, string | number> = {},
    ): string => {
        let text: string = DICTIONARY[key][isBangla ? 'bn' : 'en'];

        for (const [name, value] of Object.entries(params)) {
            text = text.replace(
                `{${name}}`,
                typeof value === 'number' ? num(value) : value,
            );
        }

        return text;
    };

    return {
        lang: isBangla ? 'bn' : 'en',
        isBangla,
        banglaEnabled: enabled,
        setLang,
        t,
        rich: (key, params) =>
            createElement(
                Fragment,
                null,
                ...t(key, params)
                    .split('**')
                    .map((part, i) =>
                        i % 2 === 1
                            ? createElement('strong', { key: i }, part)
                            : part,
                    ),
            ),
        pick: (english, bangla) =>
            isBangla && !isBlank(bangla) ? (bangla as string) : english,
        money: (amount) =>
            isBangla
                ? toBanglaDigits(formatCurrency(amount))
                : formatCurrency(amount),
        num,
        district: (value) =>
            isBangla ? (DISTRICTS_BN[value] ?? value) : value,
        city: (value) => (isBangla ? (CITIES_BN[value] ?? value) : value),
    };
}
