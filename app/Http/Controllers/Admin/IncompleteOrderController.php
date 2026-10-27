<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertIncompleteOrderRequest;
use App\Models\HomepageSetting;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IncompleteOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $incompleteOrders = IncompleteOrder::query()
            ->with('user:id,name,email')
            ->when($request->search, fn ($q) => $q
                ->where('phone', 'like', "%{$request->search}%")
                ->orWhere('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/orders/incomplete/index', [
            'incompleteOrders' => $incompleteOrders,
            'filters' => $request->only(['search']),
        ]);
    }

    public function destroy(IncompleteOrder $incompleteOrder): RedirectResponse
    {
        $incompleteOrder->delete();

        return back()->with('success', 'Incomplete order deleted.');
    }

    public function destroyAll(): RedirectResponse
    {
        $count = IncompleteOrder::query()->count();
        IncompleteOrder::query()->delete();

        return back()->with('success', "Deleted {$count} incomplete order(s).");
    }

    /**
     * Show the "convert to order" form, pre-filled with the incomplete
     * order's name/phone/items. Items are re-resolved against the live
     * product/variant so price and availability are current, not whatever
     * they were when the customer abandoned checkout.
     */
    public function convert(IncompleteOrder $incompleteOrder): Response
    {
        $items = collect($incompleteOrder->items)->map(function (array $item): array {
            $product = Product::with('images')->find($item['product_id']);
            $variant = $item['variant_id'] ?? null
                ? ProductVariant::find($item['variant_id'])
                : null;

            $available = $product !== null && ($item['variant_id'] === null || $variant !== null);

            return [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
                'available' => $available,
                'product_name' => $product?->name ?? $item['product_name'],
                'sku' => $variant?->sku ?? $product?->sku ?? $item['sku'],
                'price' => $available ? (float) ($variant?->price ?? $product->price) : $item['price'],
                'image' => $variant?->image ?? $product?->images->first()?->path ?? $item['image'] ?? null,
                'variant_attributes' => $variant?->attributes ?? $item['variant_attributes'] ?? null,
            ];
        });

        return Inertia::render('admin/orders/incomplete/convert', [
            'incompleteOrder' => [
                'id' => $incompleteOrder->id,
                'name' => $incompleteOrder->name,
                'phone' => $incompleteOrder->phone,
            ],
            'items' => $items->values(),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        $products = Product::with(['images', 'variants'])
            ->when($query !== '', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'image' => $product->images->first()?->path,
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => (float) $variant->price,
                    'stock_quantity' => $variant->stock_quantity,
                    'attributes' => $variant->attributes,
                ])->values(),
            ]);

        return response()->json($products);
    }

    public function placeOrder(ConvertIncompleteOrderRequest $request, IncompleteOrder $incompleteOrder): RedirectResponse
    {
        $shippingAddress = [
            'name' => $request->name,
            'email' => null,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => 'Bangladesh',
        ];

        $resolvedItems = collect($request->validated('items'))
            ->map(function (array $item): array {
                $product = Product::with('images')->findOrFail($item['product_id']);
                $variant = $item['variant_id'] ?? null
                    ? ProductVariant::findOrFail($item['variant_id'])
                    : null;

                $price = (float) ($variant?->price ?? $product->price);
                $quantity = (int) $item['quantity'];

                return [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $price,
                        'image' => $variant?->image ?? $product->images->first()?->path,
                        'variant_attributes' => $variant?->attributes,
                    ],
                ];
            });

        $subtotal = $resolvedItems->sum('subtotal');
        $shippingCost = $this->calculateShippingCost($shippingAddress, $subtotal);
        $total = max(0, $subtotal + $shippingCost);

        $order = DB::transaction(function () use ($shippingAddress, $resolvedItems, $subtotal, $shippingCost, $total, $incompleteOrder): Order {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $incompleteOrder->user_id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => PaymentMethod::COD->value,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => 0,
                'tax' => 0,
                'total' => $total,
                'shipping_address' => $shippingAddress,
                'billing_address' => $shippingAddress,
            ]);

            $order->statusHistories()->create([
                'status' => 'pending',
                'notes' => 'Order placed manually by an admin from an incomplete order.',
            ]);

            foreach ($resolvedItems as $item) {
                $order->items()->create($item);
            }

            $incompleteOrder->delete();

            return $order;
        });

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order {$order->order_number} created from the incomplete order.");
    }

    private function calculateShippingCost(array $shippingAddress, float $subtotal): float
    {
        $threshold = (float) (HomepageSetting::where('section_key', 'announcement_bar')->first()?->metadata['free_delivery_amount'] ?? 0);

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        return Setting::shippingRateFor($shippingAddress['state'] ?? null, $shippingAddress['city'] ?? null);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
