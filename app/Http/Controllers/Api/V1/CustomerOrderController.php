<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->latest('ordered_at')
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return new OrderResource($order->load('items'));
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Hanya pesanan berstatus pending yang dapat dibatalkan oleh pelanggan.',
            ], 409);
        }

        DB::transaction(function () use ($request, $order) {
            $order->load('items');

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if (! $product) {
                    continue;
                }

                $product->increment('stock', $item->quantity);

                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'IN',
                    'quantity' => $item->quantity,
                    'reference' => 'CUST-CANCEL-' . $order->code,
                    'note' => 'Pengembalian stok karena pesanan dibatalkan oleh pelanggan via API',
                    'movement_at' => now(),
                ]);
            }

            $order->update([
                'status' => 'cancelled',
            ]);
        });

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan dan stok produk telah dikembalikan.',
            'data' => new OrderResource($order->fresh()->load('items')),
        ]);
    }
}