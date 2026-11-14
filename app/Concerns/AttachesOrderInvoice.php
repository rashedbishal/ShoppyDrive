<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Order;
use App\Support\OrderInvoice;
use Illuminate\Mail\Mailables\Attachment;
use Throwable;

/**
 * DISC-38: the order's PDF invoice (customer copy) as a mail attachment.
 * Built when the queued mail is sent, from the order as it is then, so a
 * cancelled/refunded order gets none. If the PDF can't be built the error is
 * reported and the email still goes out without it, so an invoice problem
 * never stops an order email.
 */
trait AttachesOrderInvoice
{
    /**
     * @return list<Attachment>
     */
    protected function orderInvoiceAttachments(Order $order): array
    {
        if (! OrderInvoice::isAvailable($order)) {
            return [];
        }

        try {
            $pdf = OrderInvoice::content($order);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        return [
            Attachment::fromData(fn (): string => $pdf, OrderInvoice::filename($order))
                ->withMime('application/pdf'),
        ];
    }
}
