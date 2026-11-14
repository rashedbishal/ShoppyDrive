<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * DISC-38: the single place for the order invoice PDF — every email
 * attachment, customer/guest download and admin download goes through here,
 * so they can never disagree on when an invoice exists or what it says.
 *
 * Owner decisions (2026-11-14):
 *  - Every order has an invoice from the moment it is placed (Cash on
 *    Delivery included), except a cancelled or refunded one.
 *  - The amount is labelled "Paid amount" once the order is paid (bKash/
 *    Nagad, or COD after delivery), otherwise "Payment amount".
 *  - The parcel copy (admin only) adds the courier and consignment ID, and
 *    exists only once the order has been booked with Steadfast or Pathao.
 */
final class OrderInvoice
{
    public const string CUSTOMER_COPY = 'customer';

    public const string PARCEL_COPY = 'parcel';

    public static function isAvailable(Order $order): bool
    {
        return ! $order->trashed()
            && ! in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true);
    }

    public static function isPaid(Order $order): bool
    {
        return $order->payment_status === PaymentStatus::PAID;
    }

    public static function amountLabel(Order $order): string
    {
        return self::isPaid($order) ? 'Paid Amount' : 'Payment Amount';
    }

    /**
     * The booked consignment, or null before the order was sent to a courier.
     *
     * @return array{courier: string, consignment_id: string, tracking_code: string|null}|null
     */
    public static function parcelDetails(Order $order): ?array
    {
        if (filled($order->steadfast_consignment_id)) {
            return [
                'courier' => 'Steadfast',
                'consignment_id' => (string) $order->steadfast_consignment_id,
                'tracking_code' => filled($order->steadfast_tracking_code) ? (string) $order->steadfast_tracking_code : null,
            ];
        }

        if (filled($order->pathao_consignment_id)) {
            return [
                'courier' => 'Pathao',
                'consignment_id' => (string) $order->pathao_consignment_id,
                'tracking_code' => null,
            ];
        }

        return null;
    }

    public static function hasParcelCopy(Order $order): bool
    {
        return self::isAvailable($order) && self::parcelDetails($order) !== null;
    }

    /**
     * The PDF bytes, A4 portrait. Callers check isAvailable() first.
     *
     * DISC-40: rendered with mPDF rather than dompdf, because dompdf cannot
     * shape Bangla (vowel signs land after their consonant and conjuncts fall
     * apart). Bangla runs are drawn in the bundled Noto Sans Bengali, every
     * other run in DejaVu Sans, as before.
     */
    public static function content(Order $order, string $copy = self::CUSTOMER_COPY): string
    {
        $order->loadMissing(['items.product', 'items.variant', 'user']);

        $html = view('invoices.order', [
            'order' => $order,
            'amountLabel' => self::amountLabel($order),
            'parcel' => $copy === self::PARCEL_COPY ? self::parcelDetails($order) : null,
        ])->render();

        $mpdf = new Mpdf(self::mpdfConfig());
        $mpdf->SetTitle('Invoice '.$order->order_number);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    public static function filename(Order $order, string $copy = self::CUSTOMER_COPY): string
    {
        return ($copy === self::PARCEL_COPY ? 'parcel-invoice-' : 'invoice-').$order->order_number.'.pdf';
    }

    /**
     * Download response for a caller that has already proven access to the
     * order. Cancelled/refunded orders, and a parcel copy before the courier
     * booking, are refused.
     */
    public static function download(Order $order, string $copy = self::CUSTOMER_COPY): Response
    {
        abort_unless(self::isAvailable($order), 403, 'Invoice is not available for cancelled or refunded orders.');
        abort_if($copy === self::PARCEL_COPY && self::parcelDetails($order) === null, 404);

        return response(self::content($order, $copy), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, self::filename($order, $copy)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mpdfConfig(): array
    {
        // Font metrics mPDF caches between renders; storage is writable on
        // the host, unlike vendor/.
        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        return [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $tempDir,
            'fontDir' => [
                ...(new ConfigVariables)->getDefaults()['fontDir'],
                resource_path('fonts/noto-sans-bengali'),
            ],
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                InvoiceLanguageToFont::BANGLA_FONT => [
                    'R' => 'NotoSansBengali-Regular.ttf',
                    'B' => 'NotoSansBengali-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'languageToFont' => new InvoiceLanguageToFont,
        ];
    }
}
