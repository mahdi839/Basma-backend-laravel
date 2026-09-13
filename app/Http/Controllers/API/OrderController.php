<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckout;
use App\Models\Customer;
use App\Models\CustomerBadge;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\FacebookConversionService;
use App\Services\InventoryService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** Mirrors the orders.status enum in the database. */
    public const ORDER_STATUSES = [
        'pending',
        'placed',
        'delivered',
        'cancel',
        'completed',
        'cancelled',
        'processing',
        'returned',
        'first_call',
        'second_call',
        'third_call',
        'stock_sold',
        'shipped_to_you',
        'received_in_bd',
        'order_sent_to_china',
        'file_completed',
        'order_confirmed',
    ];

    protected $facebookService;
    protected $smsService;
    protected $customerService;
    protected InventoryService $inventory;

    public function __construct(
        FacebookConversionService $facebookService,
        SmsService $smsService,
        CustomerService $customerService,
        InventoryService $inventory
    ) {
        $this->facebookService = $facebookService;
        $this->smsService = $smsService;
        $this->customerService = $customerService;
        $this->inventory = $inventory;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', '');
        $district = $request->query('district', '');
        $search = $request->input('search', '');
        $min = $request->query('min', '');
        $max = $request->query('max', '');
        $start_date = $request->query('start_date', '');
        $end_date = $request->query('end_date', '');
        $product_title = $request->query('product_title', '');
        $product_id = $request->query('product_id', '');
        [$startAt, $endAt] = $this->orderDateBounds($start_date, $end_date);

        $orders = Order::with(['orderItems.size', 'orderItems.variant.color', 'orderItems.variant.size'])
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($district, function ($q) use ($district) {
                $q->where('district', $district);
            })
            ->when($min, function ($q) use ($min) {
                $q->where('total', '>=', $min);
            })
            ->when($max, function ($q) use ($max) {
                $q->where('total', '<=', $max);
            })
            ->when($startAt, function ($q) use ($startAt) {
                $q->where('created_at', '>=', $startAt);
            })
            ->when($endAt, function ($q) use ($endAt) {
                $q->where('created_at', '<=', $endAt);
            })
            ->when($product_title, function ($q) use ($product_title) {
                $q->whereHas('orderItems', function ($query) use ($product_title) {
                    $query->where('title', $product_title);
                });
            })
            ->when($product_id, function ($q) use ($product_id) {
                $q->whereHas('orderItems', function ($query) use ($product_id) {
                    $query->where('product_id', $product_id);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            })
            ->latest()
            ->paginate(50);

        $badgeMap = $this->badgeMapForPhones($orders->getCollection()->pluck('phone'));

        $orders->getCollection()->transform(function ($order) use ($badgeMap) {
            $orderCount = Order::where('phone', $order->phone)->count();
            $order->customer_type = $orderCount > 1 ? 'Repeat Customer' : 'New';
            $order->assigned_badge = $badgeMap[$order->phone] ?? null;

            return $order;
        });

        return response()->json($orders);
    }

    public function downloadCSV(Request $request)
    {
        $status = $request->query('status', '');
        $district = $request->query('district', '');
        $search = $request->input('search', '');
        $min = $request->query('min', '');
        $max = $request->query('max', '');
        $start_date = $request->query('start_date', '');
        $end_date = $request->query('end_date', '');
        $product_title = $request->query('product_title', '');
        $product_id = $request->query('product_id', '');
        [$startAt, $endAt] = $this->orderDateBounds($start_date, $end_date);
        $query = Order::with('orderItems.size')
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($district, function ($q) use ($district) {
                $q->where('district', $district);
            })
            ->when($min, function ($q) use ($min) {
                $q->where('total', '>=', $min);
            })
            ->when($max, function ($q) use ($max) {
                $q->where('total', '<=', $max);
            })
            ->when($startAt, function ($q) use ($startAt) {
                $q->where('created_at', '>=', $startAt);
            })
            ->when($endAt, function ($q) use ($endAt) {
                $q->where('created_at', '<=', $endAt);
            })
            ->when($product_title, function ($q) use ($product_title) {
                $q->whereHas('orderItems', function ($query) use ($product_title) {
                    $query->where('title', $product_title);
                });
            })
            ->when($product_id, function ($q) use ($product_id) {
                $q->whereHas('orderItems', function ($query) use ($product_id) {
                    $query->where('product_id', $product_id);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            });

        $date = date('Y-m-d');
        $fileName = "orders_{$date}.csv";

        $header = [
            'content-type' => 'text/csv',
            'content-disposition' => "attachment; filename={$fileName}",
        ];
        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // CSV header
            fputcsv($file, [
                'Order Date',
                'Customer Name',
                'Phone',
                'Address',
                'District',
                'Customer Type',
                'Product Info',
                'Quantity',
                'Total Price',
                'Variant',
                'Color Image',
                'Shipping Cost',
                'Payment Method',
                'Order Total',
                'Advance Payment',
                'Status',
            ]);

            $query->chunk(100, function ($orders) use ($file) {

                foreach ($orders as $order) {

                    $orderCount = \App\Models\Order::where('phone', $order->phone)->count();
                    $customerType = $orderCount > 1 ? 'Repeat Customer' : 'New';

                    foreach ($order->orderItems as $item) {

                        $variant = $item->selected_variant
                            ? ($item->selected_variant['attribute'].': '.$item->selected_variant['value'])
                            : '';

                        fputcsv($file, [
                            $order->created_at,
                            $order->name,
                            $order->phone,
                            $order->address,
                            $order->district,
                            $customerType,
                            "Product Name".": ".$item->title . ", " ." Price".": ". $item->unitPrice. ", "  . " Color Name".": ". $item->color_name .", "  . " Size".": ". optional($item->size)->size,
                            $item->qty,                            
                            $item->totalPrice,
                            $variant,                            
                            $item->colorImage,
                            $order->shipping_cost,
                            $order->payment_method,
                            $order->total,
                            $order->advance_payment,
                            $order->status,
                        ]);
                    }
                }
            });
            fclose($file);
        };

        return response()->stream($callback, 200, $header);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::with('sizes')->select(['id', 'title', 'price'])->get();

        return response()->json([
            'data' => [
                'products' => $products,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'shipping_cost' => 'required|numeric',
            'district' => 'required|string',
            'address' => 'required|string',
            'delivery_notes' => 'nullable|string',
            'payment_method' => 'required|string',
            'cart' => 'required|array',
            'cart.*.id' => 'required|integer',
            'cart.*.title' => 'required|string',
            'cart.*.size' => 'nullable',
            'cart.*.unitPrice' => 'required|numeric',
            'cart.*.qty' => 'required|integer',
            'cart.*.totalPrice' => 'required|numeric',
            'cart.*.colorImage' => 'sometimes|nullable',
            'cart.*.color_name' => 'sometimes|nullable',
            // Inventory hints. Older storefront builds omit these and are
            // resolved from colorImage / color_name instead.
            'cart.*.variant_id' => 'sometimes|nullable|integer',
            'cart.*.product_color_id' => 'sometimes|nullable|integer',
            'cart.*.color_id' => 'sometimes|nullable|integer',
            'total_amount' => 'required|numeric',
            'advance_payment' => 'nullable|numeric|min:0',
            // Facebook tracking data
            'fbp' => 'nullable|string',
            'fbc' => 'nullable|string',
            'event_source_url' => 'nullable|string',
            'checkout_session_id' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Resolve every line to a stock row and lock it before anything is
            // written, so two simultaneous checkouts cannot both take the last unit.
            $plan = $this->planCartInventory($request->cart);

            if ($plan['shortfalls'] !== [] && SiteSetting::inventoryEnforced()) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Some items are no longer available in the requested quantity.',
                    'errors' => ['cart' => ['Stock changed while you were checking out.']],
                    'shortfalls' => $plan['shortfalls'],
                ], 422);
            }

            // Calculate totals
            $subtotal = collect($request->cart)->sum('totalPrice');
            $total = $request->total_amount;

            // Create order
            $customer = $this->customerService->upsertFromOrder($request->name, $request->phone);

            $order = Order::create([
                'order_number' => 'ORD-'.rand(10000, 99999),
                'name' => $request->name,
                'phone' => $request->phone,
                'user_id' => $request->user_id ?? $customer?->user_id,
                'address' => $request->address,
                'district' => $request->district,
                'subtotal' => $subtotal,
                'total' => $total,
                'advance_payment' => $request->advance_payment ?? 0,
                'shipping_cost' => $request->shipping_cost,
                'delivery_notes' => $request->delivery_notes,
                'status' => 'placed',
                'payment_method' => $request->payment_method,
            ]);

            // Create order items
            $contentIds = [];
            $contents = [];

            foreach ($request->cart as $index => $item) {
                $line = $plan['lines'][$index] ?? null;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_variant_id' => $line['variant_id'] ?? null,
                    'product_color_id' => $line['product_color_id'] ?? null,
                    'title' => $item['title'],
                    'selected_size' => $item['size'],
                    'unitPrice' => $item['unitPrice'],
                    'qty' => $item['qty'],
                    'is_preorder' => $line['preorder'] ?? false,
                    'totalPrice' => $item['totalPrice'],
                    'colorImage' => $item['colorImage'] ?? '',
                    'color_name' => $item['color_name'] ?? '',
                ]);

                // Hold the units. Physical stock only drops once an admin sets
                // the order to confirmed.
                if (! empty($line['tracks']) && ! empty($line['variant_id'])) {
                    $this->inventory->reserve($orderItem);
                }

                // Prepare Facebook data
                $contentIds[] = (string) $item['id'];
                $contents[] = [
                    'id' => (string) $item['id'],
                    'quantity' => $item['qty'],
                    'item_price' => $item['unitPrice'],
                ];
            }

            $checkoutSessionId = $request->header('X-Session-ID') ?: $request->checkout_session_id;

            if ($checkoutSessionId || $request->phone) {
                AbandonedCheckout::whereNull('converted_order_id')
                    ->where(function ($query) use ($checkoutSessionId, $request) {
                        if ($checkoutSessionId) {
                            $query->where('session_id', $checkoutSessionId);

                            return;
                        }

                        if ($request->phone) {
                            $query->where('phone', $request->phone);
                        }
                    })
                    ->update([
                        'is_recovered' => true,
                        'status' => 'placed',
                        'converted_order_id' => $order->id,
                        'converted_at' => now(),
                    ]);
            }

            DB::commit();

            $smsResult = null;

            if (!empty($order->phone)) {
                $smsResult = $this->smsService->sendOrderConfirmation(
                    $order->phone,
                    $order->name,
                    $order->order_number
                );
            }

            // Track Facebook Purchase Event
            $this->facebookService->sendEvent(
                'Purchase',
                [
                    'email' => $request->email ?? null,
                    'phone' => $request->phone ?? null,
                    'fbp' => $request->fbp ?? null,
                    'fbc' => $request->fbc ?? null,
                ],
                [
                    'value' => $total,
                    'currency' => 'BDT',
                    'content_ids' => $contentIds,
                    'contents' => $contents,
                    'content_type' => 'product',
                    'num_items' => count($request->cart),
                    'event_id' => 'order_'.$order->order_number, // For deduplication
                ],
                $request->event_source_url ?? null
            );

            return response()->json([
                'message' => 'Order created successfully',
                'order_number' => $order->order_number,
                'sms' => $smsResult,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Order creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

  public function order_status(Request $request, $id)
 {
    $request->validate([
        'status' => ['required', 'string', Rule::in(self::ORDER_STATUSES)],
    ]);

    $order = Order::findOrFail($id);

    $order->update([
        'status' => $request->status,
    ]);

    // Confirming deducts stock for real; cancelling or returning hands it back.
    // Gated on each line's inventory_state, so repeating a status is a no-op.
    $this->inventory->applyOrderStatus($order->id, $request->status, $request->user()?->id);

    if ($request->status === 'order_confirmed' || $request->status === 'cancelled') {
        if (!empty($order->phone)) {
            $statusText = $request->status === 'order_confirmed' ? 'confirmed' : 'cancelled';

            $customMessage = "Dear {$order->name}, your order {$order->order_number} has been {$statusText} successfully. Thank you for shopping with us.";

            $this->smsService->sendOrderConfirmation(
                $order->phone,
                $order->name,
                $order->order_number,
                $customMessage
            );
        }
    }

    return response()->json([
        'message' => 'Status Updated Successfully!',
    ]);
 }

    /**
     * Display the specified resource.
     */
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $order = Order::with(['orderItems.size'])->findOrFail($id);
        $badgeMap = $this->badgeMapForPhones(collect([$order->phone]));
        $order->assigned_badge = $badgeMap[$order->phone] ?? null;

        return response()->json([
            'order' => $order,
        ]);
    }

    public function myOrders(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $status = $request->query('status', '');
        $start_date = $request->query('start_date', '');
        $end_date = $request->query('end_date', '');

        $orders = Order::query()
            ->select([
                'id',
                'order_number',
                'user_id',
                'advance_payment',
                'status',
                'created_at',
                'total',
            ])
            ->with([
                'orderItems' => function ($q) {
                    $q->select([
                        'id',
                        'order_id',   // REQUIRED
                        'product_id', // REQUIRED
                        'qty',
                        'unitPrice',
                        'title',
                        'totalPrice',
                    ]);
                },
                'orderItems.product' => function ($q) {
                    $q->select([
                        'id',
                    ]);
                },
                'orderItems.product.images' => function ($q) {
                    $q->select([
                        'id',
                        'product_id', // REQUIRED
                        'image',
                    ]);
                },
            ])
            ->where('user_id', $user->id)
            ->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($start_date, function ($q) use ($start_date) {
                $q->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($q) use ($end_date) {
                $q->where('created_at', '<=', $end_date);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $orders,
        ], 200);
    }

    /**
     * Get single order details for authenticated user
     */
    public function myOrderDetails($orderNumber)
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $order = Order::with(['orderItems.size'])
            ->where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->first();

        if (! $order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $order,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $order = Order::with(['orderItems.size', 'orderItems.product.images'])
            ->findOrFail($id);

        // Get all products for dropdown
        $products = Product::with(['images', 'sizes'])
            ->select(['id', 'title', 'price', 'colors'])
            ->whereIn('status', ['in-stock', 'prebook'])
            ->get();

        // Get all sizes
        $sizes = Size::select(['id', 'size'])->get();

        return response()->json([
            'message' => 'success',
            'data' => [
                'order' => $order,
                'products' => $products,
                'sizes' => $sizes,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'address' => 'required|string',
            'district' => 'required|string',
            'shipping_cost' => 'required|numeric',
            'delivery_notes' => 'nullable|string',
            'payment_method' => 'required|string',
            'advance_payment' => 'nullable|numeric|min:0',
            'status' => 'required|string',

            // Order items
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'required|integer',
            'items.*.title' => 'required|string',
            'items.*.size_id' => 'nullable|integer',
            'items.*.color' => 'nullable',
            'items.*.color.id' => 'nullable',
            'items.*.color.code' => 'nullable|string',
            'items.*.color.image' => 'nullable|string',
            'items.*.color.name' => 'nullable|string',
            'items.*.unitPrice' => 'required|numeric',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.totalPrice' => 'required|numeric',

            // Items to delete
            'deleted_items' => 'nullable|array',
            'deleted_items.*' => 'integer|exists:order_items,id',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::with('orderItems')->findOrFail($id);

            // Calculate new totals
            $subtotal = collect($request->items)->sum('totalPrice');
            $total = $subtotal + $request->shipping_cost;

            $this->customerService->upsertFromOrder($request->name, $request->phone);

            // Update order basic info
            $order->update([
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address,
                'district' => $request->district,
                'shipping_cost' => $request->shipping_cost,
                'delivery_notes' => $request->delivery_notes,
                'payment_method' => $request->payment_method,
                'advance_payment' => $request->advance_payment ?? 0,
                'status' => $request->status,
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            // Delete removed items, handing whatever they were holding back first.
            if (! empty($validated['deleted_items'])) {
                foreach ($validated['deleted_items'] as $itemId) {
                    $item = OrderItem::find($itemId);
                    if ($item && $item->order_id == $order->id) {
                        $this->inventory->releaseForEdit($item, $request->user()?->id);
                        $item->delete();
                    }
                }
            }

            // Track existing item IDs to know which are new
            $existingItemIds = $order->orderItems->pluck('id')->toArray();

            foreach ($request->items as $item) {
                $colorImage = null;
                if (isset($item['color']) && isset($item['color']['image'])) {
                    $colorImage = $item['color']['image'];
                    $colorName = $item['color']['name'];
                }

                $variant = $this->inventory->resolveVariant((int) $item['product_id'], [
                    'product_color_id' => $item['color']['product_color_id'] ?? null,
                    'color_id' => $item['color']['id'] ?? null,
                    'color_image' => $colorImage,
                    'color_name' => $colorName,
                    'size_id' => $item['size_id'] ?? null,
                ]);

                // If item has ID and exists -> UPDATE
                if (isset($item['id']) && in_array($item['id'], $existingItemIds)) {
                    $orderItem = OrderItem::find($item['id']);

                    // Hand back whatever the line was holding, then re-hold at the
                    // new variant and quantity. Keeps edits balanced instead of the
                    // old one-sided restore.
                    $this->inventory->releaseForEdit($orderItem, $request->user()?->id);

                    $orderItem->update([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $variant?->id,
                        'product_color_id' => $variant?->product_color_id,
                        'title' => $item['title'],
                        'selected_size' => $item['size_id'] ?? null,
                        'unitPrice' => $item['unitPrice'],
                        'qty' => $item['qty'],
                        'totalPrice' => $item['totalPrice'],
                        'colorImage' => $colorImage ? url($colorImage) : '',
                        'color_name' => $colorName ?? '',
                        'inventory_state' => OrderItem::INV_NONE,
                    ]);

                    $this->reserveOrCommit($order, $orderItem->fresh(), $request->user()?->id);
                }
                // No ID or doesn't exist -> CREATE NEW
                else {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $variant?->id,
                        'product_color_id' => $variant?->product_color_id,
                        'title' => $item['title'],
                        'selected_size' => $item['size_id'] ?? null,
                        'unitPrice' => $item['unitPrice'],
                        'qty' => $item['qty'],
                        'totalPrice' => $item['totalPrice'],
                        'colorImage' => $colorImage ? url($colorImage) : '',
                        'color_name' => $colorName ?? '',
                    ]);

                    $this->reserveOrCommit($order, $orderItem, $request->user()?->id);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Order updated successfully',
                'order' => $order->fresh()->load('orderItems.size'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Order update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }

    /**
     * Put a line into the inventory state its order status already implies, so an
     * admin adding an item to an order that is already confirmed deducts stock
     * immediately instead of only reserving it.
     */
    private function reserveOrCommit(Order $order, OrderItem $item, ?int $userId): void
    {
        if (! $item->product_variant_id) {
            return;
        }

        $product = Product::with('category:id,track_inventory')->find($item->product_id);

        if (! $product || ! $product->tracksInventory()) {
            return;
        }

        $this->inventory->reserve($item, $userId);

        if (in_array($order->status, InventoryService::COMMIT_STATUSES, true)) {
            $this->inventory->commit($item->fresh(), $userId);
        }
    }

    /**
     * Resolve each cart line to its stock row, lock it, and check availability.
     *
     * Must be called inside a transaction — the locks it takes are what stop two
     * checkouts from selling the same last unit.
     *
     * @return array{lines: array<int, array>, shortfalls: array<int, array>}
     */
    private function planCartInventory(array $cart): array
    {
        $lines = [];
        $shortfalls = [];

        $products = Product::with('category:id,track_inventory')
            ->whereIn('id', collect($cart)->pluck('id')->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($cart as $index => $item) {
            $product = $products->get($item['id']);
            $tracks = $product ? $product->tracksInventory() : false;

            $variant = $this->inventory->resolveVariant((int) $item['id'], [
                'variant_id' => $item['variant_id'] ?? null,
                'product_color_id' => $item['product_color_id'] ?? null,
                'color_id' => $item['color_id'] ?? null,
                'color_image' => $item['colorImage'] ?? null,
                'color_name' => $item['color_name'] ?? null,
                'size_id' => $item['size'] ?? null,
            ]);

            $line = [
                'tracks' => $tracks,
                'variant_id' => $variant?->id,
                'product_color_id' => $variant?->product_color_id,
                'preorder' => false,
            ];

            if (! $tracks || ! $variant) {
                $lines[$index] = $line;

                continue;
            }

            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->first();
            $check = $this->inventory->checkAvailability($locked, (int) $item['qty']);

            $line['preorder'] = $check['preorder'];
            $lines[$index] = $line;

            if (! $check['ok']) {
                $shortfalls[] = [
                    'index' => $index,
                    'product_id' => (int) $item['id'],
                    'variant_id' => $variant->id,
                    'title' => $item['title'],
                    'variant_label' => $locked->label(),
                    'requested' => (int) $item['qty'],
                    'available' => $check['available'],
                    'reason' => $check['reason'],
                ];
            }
        }

        return ['lines' => $lines, 'shortfalls' => $shortfalls];
    }

    /**
     * Convert Bangladesh-local calendar dates to UTC database boundaries.
     */
    private function orderDateBounds(?string $startDate, ?string $endDate): array
    {
        $timezone = config('app.business_timezone', 'Asia/Dhaka');

        return [
            $startDate
                ? Carbon::parse($startDate, $timezone)->startOfDay()->utc()
                : null,
            $endDate
                ? Carbon::parse($endDate, $timezone)->endOfDay()->utc()
                : null,
        ];
    }

    private function badgeMapForPhones($phones): array
    {
        $phones = collect($phones)->filter()->unique()->values();
        if ($phones->isEmpty()) {
            return [];
        }

        return Customer::with('badge')
            ->whereIn('phone', $phones)
            ->get()
            ->mapWithKeys(function (Customer $customer) {
                return [$customer->phone => CustomerBadge::payload($customer->badge?->badge_title)];
            })
            ->all();
    }
}
