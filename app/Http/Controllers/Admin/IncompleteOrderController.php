<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderPricingPreviewRequest;
use App\Http\Requests\ConvertIncompleteOrderRequest;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\ManualOrderPricing;
use App\Support\CartManager;
use App\Support\OrderNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IncompleteOrderController extends Controller
{
    public function __construct(
        private readonly CartManager $cartManager,
    ) {}

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
                // BIZ-017: the live price checkout would charge (flash sales included).
                'price' => $available ? $this->cartManager->resolvePrice((int) $product->id, $variant?->id) : $item['price'],
                'image' => $variant?->image ?? $product?->primaryImagePath() ?? $item['image'] ?? null,
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

    /**
     * BIZ-017: what the conversion will charge (live line prices, delivery
     * charge for the entered address), so the screen shows it before the
     * order is created. Same calculation as the manual-order preview.
     */
    public function pricingPreview(OrderPricingPreviewRequest $request, ManualOrderPricing $pricing): JsonResponse
    {
        $data = $request->validated();

        return response()->json($pricing->calculate($data['items'], $data['state'] ?? null, $data['city'] ?? null));
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
                'image' => $product->primaryImagePath(),
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

    public function placeOrder(ConvertIncompleteOrderRequest $request, IncompleteOrder $incompleteOrder, ManualOrderPricing $pricing, InventoryService $inventory): RedirectResponse
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

                // BIZ-017: same live price as checkout and manual orders
                // (flash sales included), so InventoryService also counts
                // the unit against the flash-sale stock limit.
                $price = $this->cartManager->resolvePrice((int) $product->id, $variant?->id);
                $quantity = (int) $item['quantity'];

                return [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                    'free_delivery' => $product->free_delivery,
                    'free_delivery_min_qty' => $product->free_delivery_min_qty,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $price,
                        'image' => $variant?->image ?? $product->primaryImagePath(),
                        'variant_attributes' => $variant?->attributes,
                    ],
                ];
            });

        $subtotal = $resolvedItems->sum('subtotal');
        $shippingCost = $pricing->shippingCost($shippingAddress, $resolvedItems, $subtotal);
        $total = max(0, $subtotal + $shippingCost);

        try {
            $order = DB::transaction(function () use ($shippingAddress, $resolvedItems, $subtotal, $shippingCost, $total, $incompleteOrder, $inventory): Order {
                $order = Order::create([
                    'order_number' => OrderNumberGenerator::generate(),
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

                $inventory->lockStockInOrder($resolvedItems);

                foreach ($resolvedItems as $item) {
                    // BIZ-004: this was the one order-creation path that never
                    // called InventoryService at all — stock was never actually
                    // taken, yet cancelling the resulting order still restocked
                    // it via Order::booted(), inflating stock that was never
                    // decremented in the first place.
                    $flashSaleItemId = $inventory->decrementForOrderItem($order, $item['product_id'], $item['variant_id'], $item['quantity'], $item['price']);

                    $order->items()->create([
                        ...collect($item)->except(['free_delivery', 'free_delivery_min_qty'])->all(),
                        'flash_sale_item_id' => $flashSaleItemId,
                    ]);
                }

                $incompleteOrder->delete();

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order {$order->order_number} created from the incomplete order.");
    }
}
