<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InsufficientStockException;
use App\Models\LandingPage;
use Illuminate\Http\Request;

/**
 * DISC-65: Bangla for a landing page's order and payment flow. The landing
 * page sends the visitor's chosen language as `lang`; a page with Bangla
 * turned off always answers in English. Only the words the customer reads
 * change — validation, pricing, stock and order creation are untouched.
 */
class LandingPageLanguage
{
    public const string ENGLISH = 'en';

    public const string BANGLA = 'bn';

    /** The language this request's customer-facing messages should use. */
    public static function fromRequest(Request $request, ?LandingPage $page): string
    {
        return $page?->hasBangla() === true && $request->input('lang') === self::BANGLA
            ? self::BANGLA
            : self::ENGLISH;
    }

    public static function isBangla(string $language): bool
    {
        return $language === self::BANGLA;
    }

    /** "123" → "১২৩"; every other character is left as it is. */
    public static function digits(string|int|float $value): string
    {
        return strtr((string) $value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }

    /** ৳ amount in Bangla digits, e.g. ৳১,২৫০.০০. */
    public static function money(float $amount): string
    {
        return '৳'.self::digits(number_format($amount, 2));
    }

    public static function providerName(string $provider): string
    {
        return $provider === 'bkash' ? 'বিকাশ' : 'নগদ';
    }

    /**
     * Bangla messages for the landing-page order form's validation, one per
     * field (every rule on that field shares it).
     *
     * @return array<string, string>
     */
    public static function orderValidationMessages(int $maxUnits): array
    {
        $quantity = 'পরিমাণ ১ থেকে '.self::digits($maxUnits).'-এর মধ্যে হতে হবে।';

        return [
            'name' => 'আপনার পুরো নাম লিখুন (সর্বোচ্চ ১০০ অক্ষর)।',
            'phone' => 'সঠিক ১১ সংখ্যার বাংলাদেশি মোবাইল নম্বর লিখুন (যেমন 01712345678)।',
            'email' => 'সঠিক ইমেইল ঠিকানা লিখুন।',
            'address_line1' => 'আপনার ঠিকানা লিখুন (সর্বোচ্চ ২০০ অক্ষর)।',
            'address_line2' => 'ঠিকানা সর্বোচ্চ ২০০ অক্ষরের হতে পারে।',
            'city' => 'আপনার এলাকা, শহর বা উপজেলা দিন।',
            'state' => 'তালিকা থেকে আপনার জেলা বেছে নিন।',
            'postal_code' => 'পোস্ট কোড সর্বোচ্চ ১০ অক্ষরের হতে পারে।',
            'notes' => 'অর্ডার নোট সর্বোচ্চ ৫০০ অক্ষরের হতে পারে।',
            'quantity' => $quantity,
            'items' => self::message('choose_option'),
            'items.*.variant_id' => 'বেছে নেওয়া একটি অপশন আর পাওয়া যাচ্ছে না। পেজটি রিফ্রেশ করুন।',
            'items.*.quantity' => $quantity,
            'combos' => 'বেছে নেওয়া একটি কম্বো আর পাওয়া যাচ্ছে না। পেজটি রিফ্রেশ করুন।',
            'combos.*.combo_id' => 'বেছে নেওয়া একটি কম্বো আর পাওয়া যাচ্ছে না। পেজটি রিফ্রেশ করুন।',
            'combos.*.quantity' => $quantity,
            'payment_method' => 'পেমেন্টের ধরন বেছে নিন।',
            'provider' => 'বিকাশ অথবা নগদ বেছে নিন।',
            'order_token' => 'পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।',
            'sd_confirm_ref' => 'অর্ডারটি করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।',
        ];
    }

    /** @return array<string, string> */
    public static function paymentValidationMessages(): array
    {
        return [
            'transaction_id' => 'এসএমএস থেকে সঠিক ট্রানজেকশন আইডি (TrxID) দিন।',
        ];
    }

    /**
     * A fixed Bangla message. `:name` placeholders are replaced from $params.
     *
     * @param  array<string, string>  $params
     */
    public static function message(string $key, array $params = []): string
    {
        $message = match ($key) {
            'choose_option' => 'অন্তত একটি অপশন ও পরিমাণ বেছে নিন।',
            'max_units' => 'একবারে সর্বোচ্চ :max টি পণ্য অর্ডার করা যাবে।',
            'mobile_banking_unavailable' => 'মোবাইল পেমেন্ট যাচাই সাময়িকভাবে বন্ধ আছে। অনুগ্রহ করে ক্যাশ অন ডেলিভারি বেছে নিন।',
            'still_processing' => 'আপনার অর্ডারটি এখনও প্রসেস হচ্ছে। একটু অপেক্ষা করে আবার চেষ্টা করুন।',
            'session_expired' => 'পেমেন্টের সময় শেষ হয়ে গেছে। আবার চেষ্টা করুন।',
            'session_expired_order_again' => 'পেমেন্টের সময় শেষ হয়ে গেছে। অনুগ্রহ করে আবার অর্ডার করুন।',
            'trx_not_found' => 'আমাদের :provider রেকর্ডে এই ট্রানজেকশন আইডি পাওয়া যায়নি। এসএমএস থেকে আইডিটি আবার মিলিয়ে দেখুন।',
            'trx_wrong_provider' => 'এই ট্রানজেকশন আইডিটি অন্য পেমেন্ট মাধ্যমের।',
            'trx_cannot_use' => 'এই ট্রানজেকশন আইডি দিয়ে এই অর্ডারের পেমেন্ট করা যাবে না। আপনি যদি এই অর্ডারের জন্য টাকা পাঠিয়ে থাকেন, তাহলে ট্রানজেকশন আইডিসহ আমাদের সাথে যোগাযোগ করুন।',
            'trx_amount_unavailable' => 'এই ট্রানজেকশনের টাকার পরিমাণ স্বয়ংক্রিয়ভাবে যাচাই করা যায়নি। ট্রানজেকশন আইডিসহ আমাদের সাথে যোগাযোগ করুন, আমরা নিজে যাচাই করে দেব।',
            'trx_amount_mismatch' => 'এই পেমেন্টের পরিমাণ আপনার অর্ডারের মোট :amount-এর সাথে মিলছে না। ট্রানজেকশন আইডিটি আবার দেখুন অথবা আমাদের সাথে যোগাযোগ করুন।',
            'something_wrong' => 'কিছু একটা সমস্যা হয়েছে। অনুগ্রহ করে আমাদের সাথে যোগাযোগ করুন।',
            'paid_out_of_stock' => 'আমরা আপনার :amount পেমেন্ট পেয়েছি (TrxID: :trx), কিন্তু দুঃখিত, অর্ডারটি কনফার্ম হওয়ার আগেই :item স্টক শেষ হয়ে গেছে। আমাদের টিম বিষয়টি জেনেছে এবং পরবর্তী পদক্ষেপের জন্য শীঘ্রই আপনার সাথে যোগাযোগ করবে। অসুবিধার জন্য আমরা দুঃখিত।',
            default => throw new \InvalidArgumentException("Unknown landing page message [{$key}]."),
        };

        foreach ($params as $name => $value) {
            $message = str_replace(':'.$name, $value, $message);
        }

        return $message;
    }

    /**
     * The Bangla version of a stock problem reported while placing or paying
     * for an order. The exception's English wording is matched to say the
     * same thing in Bangla; anything unrecognised gets a general message.
     * $productNameBn replaces the product's English name when the admin gave
     * one on this landing page.
     */
    public static function stockMessage(InsufficientStockException $exception, ?string $productNameBn = null): string
    {
        $message = $exception->getMessage();
        $name = fn (string $english): string => '"'.(filled($productNameBn) ? $productNameBn : $english).'"';

        return match (true) {
            (bool) preg_match('/^Only (\d+) unit\(s\) of "(.+)" left in stock\.$/s', $message, $m) => $name($m[2]).' স্টকে মাত্র '.self::digits($m[1]).' টি আছে।',
            (bool) preg_match('/^"this item" is out of stock\.$/s', $message) => 'এই পণ্যটি স্টকে নেই।',
            (bool) preg_match('/^"(.+)" is out of stock\.$/s', $message, $m) => $name($m[1]).' স্টকে নেই।',
            (bool) preg_match('/^The flash-sale stock limit for "(.+)" has been reached\.$/s', $message, $m) => $name($m[1]).'-এর ফ্ল্যাশ সেলের স্টক শেষ হয়ে গেছে।',
            (bool) preg_match('/^Cash on delivery allows at most (\d+) of "(.+)" per phone number until earlier orders are dispatched\.(?: You already have (\d+) in orders waiting to be dispatched\.)?/s', $message, $m) => 'আগের অর্ডারগুলো পাঠানো না হওয়া পর্যন্ত একটি ফোন নম্বর থেকে ক্যাশ অন ডেলিভারিতে '.$name($m[2]).' সর্বোচ্চ '.self::digits($m[1]).' টি অর্ডার করা যায়।'
                .(isset($m[3]) && $m[3] !== '' ? ' আপনার '.self::digits($m[3]).' টি ইতিমধ্যে পাঠানোর অপেক্ষায় আছে।' : '')
                .' অনুগ্রহ করে পরিমাণ কমান, বিকাশ/নগদে পেমেন্ট করুন, অথবা আমাদের সাথে যোগাযোগ করুন।',
            $message === 'One of the selected product options is not available.' => 'বেছে নেওয়া একটি অপশন এখন পাওয়া যাচ্ছে না।',
            default => 'দুঃখিত, আপনার বেছে নেওয়া পণ্যের পর্যাপ্ত স্টক নেই। পরিমাণ কমিয়ে আবার চেষ্টা করুন।',
        };
    }

    /**
     * The Bangla labels for one confirmation-page order line, from the
     * landing page's own Bangla product name, attribute/option names and
     * combo names. Each is null when the admin gave no Bangla for it.
     *
     * @param  array<string, mixed>  $snapshot  the order item's product_snapshot
     * @return array{product_name: string|null, variant_label: string|null, combo_name: string|null}
     */
    public static function orderItemLabels(LandingPage $page, ?int $productId, array $snapshot): array
    {
        $bangla = $page->banglaSections();
        $product = $bangla['product'] ?? [];

        $attributeNames = collect($product['attribute_names'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['bn'] ?? null))
            ->mapWithKeys(fn (array $row): array => [(string) $row['en'] => (string) $row['bn']]);
        $valueNames = collect($product['value_names'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['bn'] ?? null))
            ->mapWithKeys(fn (array $row): array => [$row['attribute']."\0".$row['en'] => (string) $row['bn']]);

        $attributes = is_array($snapshot['variant_attributes'] ?? null) ? $snapshot['variant_attributes'] : [];
        $variantLabel = collect($attributes)
            ->map(fn ($value, $name): string => ($attributeNames[$name] ?? $name).': '.($valueNames[$name."\0".$value] ?? $value))
            ->implode(', ');
        $comboId = $snapshot['combo']['id'] ?? null;
        $comboName = is_string($comboId) ? ($bangla['combos'][$comboId]['name'] ?? null) : null;

        return [
            'product_name' => $productId === $page->product_id && filled($product['name'] ?? null) ? (string) $product['name'] : null,
            'variant_label' => $variantLabel !== '' ? $variantLabel : null,
            'combo_name' => filled($comboName) ? (string) $comboName : null,
        ];
    }
}
