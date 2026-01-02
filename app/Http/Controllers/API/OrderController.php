<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\AbandonedCheckout;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use App\Services\FacebookConversionService;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

    protected $facebookService;

    public function __construct(FacebookConversionService $facebookService)
    {
        $this->facebookService = $facebookService;
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

        $orders = Order::with('orderItems.size')
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
            ->when($start_date, function ($q) use ($start_date) {
                $q->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($q) use ($end_date) {
                $q->where('created_at', '<=', $end_date);
            })
            ->when($product_title, function ($q) use ($product_title) {
                $q->whereHas('orderItems', function ($query) use ($product_title) {
                    $query->where('title', $product_title);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            })
            ->latest()
            ->paginate(10);
        $orders->getCollection()->transform(function ($order) {
            $orderCount = Order::where('phone', $order->phone)->count();
            $order->customer_type = $orderCount > 1 ? 'Repeat Customer' : 'New';
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
            ->when($start_date, function ($q) use ($start_date) {
                $q->where('created_at', '>=', $start_date);
            })
            ->when($end_date, function ($q) use ($end_date) {
                $q->where('created_at', '<=', $end_date);
            })
            ->when($product_title, function ($q) use ($product_title) {
                $q->whereHas('orderItems', function ($query) use ($product_title) {
                    $query->where('title', $product_title);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%");
                });
            });


        $date = Date('Y-m-d');
        $fileName = "orders_{$date}.csv";

        $header = [
            'content-type' => 'text/csv',
            'content-disposition' => "attachment; filename={$fileName}",
        ];
        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // CSV header
            fputcsv($file, [
                "Order Date",
                "Customer Name",
                "Phone",
                "Address",
                "District",
                "Customer Type",
                "Product Title",
                "Quantity",
                "Unit Price",
                "Total Price",
                "Variant",
                "Size",
                "Color Image",
                "Shipping Cost",
                "Payment Method",
                "Order Total",
                "Status"
            ]);

            $query->chunk(100, function ($orders) use ($file) {

                foreach ($orders as $order) {

                    $orderCount = \App\Models\Order::where('phone', $order->phone)->count();
                    $customerType = $orderCount > 1 ? 'Repeat Customer' : 'New';

                    foreach ($order->orderItems as $item) {

                        $variant = $item->selected_variant
                            ? ($item->selected_variant['attribute'] . ': ' . $item->selected_variant['value'])
                            : '';

                        fputcsv($file, [
                            $order->created_at,
                            $order->name,
                            $order->phone,
                            $order->address,
                            $order->district,
                            $customerType,
                            $item->title,
                            $item->qty,
                            $item->unitPrice,
                            $item->totalPrice,
                            $variant,
                            optional($item->size)->size,
                            $item->colorImage,
                            $order->shipping_cost,
                            $order->payment_method,
                            $order->total,
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
            ]
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
            'total_amount' => 'required|numeric',
            // Facebook tracking data
            'fbp' => 'nullable|string',
            'fbc' => 'nullable|string',
            'event_source_url' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Calculate totals
            $subtotal = collect($request->cart)->sum('totalPrice');
            $total = $request->total_amount;

            // Create order
            $order = Order::create([
                'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid()),
                'name' => $request->name,
                'phone' => $request->phone,
                'user_id' => $request->user_id ?? null,
                'address' => $request->address,
                'district' => $request->district,
                'subtotal' => $subtotal,
                'total' => $total,
                'shipping_cost' => $request->shipping_cost,
                'delivery_notes' => $request->delivery_notes,
                'status' => 'placed',
                'payment_method' => $request->payment_method,
            ]);

            // Create order items
            $contentIds = [];
            $contents = [];

            foreach ($request->cart as $item) {
                // ✅ If size is selected → stock-based product
                if ($item['size']) {
                    
                    $productSize = ProductSize::where('product_id', $item['id'])
                        ->where('size_id', $item['size'])
                        ->lockForUpdate() // 🔒 IMPORTANT
                        ->first();
                    if (!$productSize) {
                        throw new \Exception("Product size not found.");
                    }

                    if ($productSize->stock < $item['qty']) {
                        throw new \Exception(
                            "Insufficient stock for {$item['title']} ({$item['size']})"
                        );
                    }

                    // 🔻 Reduce stock
                    $productSize->decrement('stock', $item['qty']);
                }
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'title' => $item['title'],
                    'selected_size' => $item['size'],
                    'unitPrice' => $item['unitPrice'],
                    'qty' => $item['qty'],
                    'totalPrice' => $item['totalPrice'],
                    'colorImage' => $item['colorImage'] ?? "",
                ]);

                // Prepare Facebook data
                $contentIds[] = (string)$item['id'];
                $contents[] = [
                    'id' => (string)$item['id'],
                    'quantity' => $item['qty'],
                    'item_price' => $item['unitPrice']
                ];
            }

            DB::commit();

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
                    'event_id' => 'order_' . $order->order_number, // For deduplication
                ],
                $request->event_source_url ?? null
            );

            return response()->json([
                'message' => 'Order created successfully',
                'order_number' => $order->order_number
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function order_status(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $order->update([
            'status' => $request->status,
        ]);
        return response()->json([
            'message' => 'Status Updated Successfully!'
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

        return response()->json([
            'order' => $order
        ]);
    }


    public function myOrders(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
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
                'status',
                'created_at',
                'total'
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
                        'totalPrice'
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
            'data' => $orders
        ], 200);
    }

    /**
     * Get single order details for authenticated user
     */
    public function myOrderDetails($orderNumber)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $order = Order::with(['orderItems.size'])
            ->where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $order
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
