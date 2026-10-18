<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\CourierOrderStatusMapper;
use App\Services\PathaoCourierService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('pathao:sync-statuses')]
#[Description('Poll Pathao for status updates on orders with an active consignment and sync order status accordingly.')]
class SyncPathaoOrderStatuses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PathaoCourierService $pathao): int
    {
        $orders = Order::query()
            ->whereNotNull('pathao_consignment_id')
            ->whereNotIn('status', [
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value,
                OrderStatus::REFUNDED->value,
            ])
            ->get();

        foreach ($orders as $order) {
            try {
                $info = $pathao->getOrderInfo($order->pathao_consignment_id);
            } catch (RuntimeException $e) {
                $this->warn("Order {$order->order_number}: {$e->getMessage()}");

                continue;
            }

            CourierOrderStatusMapper::sync($order, 'pathao', $info['order_status_slug'] ?? $info['order_status']);
        }

        $this->info("Synced {$orders->count()} Pathao order(s).");

        return self::SUCCESS;
    }
}
