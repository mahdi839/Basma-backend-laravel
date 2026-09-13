<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\Size;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventory)
    {
    }

    /** Counters for the dashboard header and the sidebar badge. */
    public function summary()
    {
        $threshold = SiteSetting::lowStockThreshold();

        return response()->json([
            'message' => 'success',
            'data' => [
                'tracked_products' => Product::where('track_inventory', true)->count(),
                'total_variants' => ProductVariant::count(),
                'units_on_hand' => (int) ProductVariant::sum('stock'),
                'units_reserved' => (int) ProductVariant::sum('reserved'),
                'low_stock' => ProductVariant::active()->lowStock($threshold)->count(),
                'out_of_stock' => ProductVariant::active()->outOfStock()->count(),
                'stock_value' => (float) ProductVariant::selectRaw('COALESCE(SUM(stock * purchase_price), 0) AS v')->value('v'),
                'low_stock_threshold' => $threshold,
            ],
        ]);
    }

    /**
     * Variant-level listing. This is the screen that replaces the old
     * product-level inventory page.
     */
    public function index(Request $request)
    {
        $threshold = SiteSetting::lowStockThreshold();

        $query = ProductVariant::query()
            ->with([
                'product:id,title,sku,status,track_inventory',
                'product.firstImage:id,product_id,image',
                'color:id,name,code,image',
                'size:id,size',
            ])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($inner) use ($search) {
                    $inner->where('sku', 'LIKE', "%{$search}%")
                        ->orWhereHas('product', function ($p) use ($search) {
                            $p->where('title', 'LIKE', "%{$search}%")
                                ->orWhere('sku', 'LIKE', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->whereHas('product.category', fn ($c) => $c->where('categories.id', $request->category_id));
            })
            ->when($request->boolean('tracked_only'), function ($q) {
                $q->whereHas('product', fn ($p) => $p->where('track_inventory', true));
            });

        match ($request->query('stock_status')) {
            'low' => $query->lowStock($threshold),
            'out' => $query->outOfStock(),
            'in' => $query->inStock(),
            'preorder' => $query->where('allow_preorder', true),
            default => null,
        };

        $variants = $query
            ->orderBy('product_id')
            ->orderBy('position')
            ->paginate((int) $request->query('per_page', 30));

        $variants->getCollection()->transform(function (ProductVariant $variant) use ($threshold) {
            $variant->setAttribute('label', $variant->label());
            $variant->setAttribute('low_stock', $variant->isLowStock($threshold));

            return $variant;
        });

        return response()->json([
            'message' => 'success',
            'data' => $variants,
        ]);
    }

    /**
     * Everything the matrix editor needs for one product: its colours down the
     * rows, its sizes across the columns, and the existing variant in each cell.
     */
    public function matrix($productId)
    {
        $product = Product::with([
            'productColors:id,product_id,name,code,image,position,legacy_json_id',
            'sizes:id,size',
            'variants.color:id,name',
            'variants.size:id,size',
            'category:id,name,track_inventory',
        ])->findOrFail($productId);

        return response()->json([
            'message' => 'success',
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'status' => $product->status,
                    'track_inventory' => (bool) $product->track_inventory,
                    'inherits_tracking' => $product->tracksInventory() && ! $product->track_inventory,
                    'preorder_mode' => $product->preorder_mode ?? 'off',
                    'preorder_eta_days' => $product->preorder_eta_days,
                    'preorder_note' => $product->preorder_note,
                    'low_stock_threshold' => $product->low_stock_threshold,
                ],
                'colors' => $product->productColors,
                'sizes' => $product->sizes->map(fn ($s) => ['id' => $s->id, 'size' => $s->size])->values(),
                'variants' => $product->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'product_color_id' => $v->product_color_id,
                    'size_id' => $v->size_id,
                    'variant_key' => $v->variant_key,
                    'sku' => $v->sku,
                    'price' => $v->price,
                    'purchase_price' => $v->purchase_price,
                    'stock' => $v->stock,
                    'reserved' => $v->reserved,
                    'available' => $v->available,
                    'low_stock_threshold' => $v->low_stock_threshold,
                    'allow_preorder' => (bool) $v->allow_preorder,
                    'preorder_limit' => $v->preorder_limit,
                    'preorder_count' => $v->preorder_count,
                    'is_active' => (bool) $v->is_active,
                    'label' => $v->label(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Save the matrix. Stock changes go through the ledger rather than a blind
     * write, so the history stays complete.
     */
    public function saveMatrix(Request $request, $productId)
    {
        $validated = $request->validate([
            'track_inventory' => 'nullable|boolean',
            'preorder_mode' => ['nullable', Rule::in(['off', 'always', 'when_out_of_stock'])],
            'preorder_eta_days' => 'nullable|integer|min:0|max:365',
            'preorder_note' => 'nullable|string|max:255',
            'low_stock_threshold' => 'nullable|integer|min:0',

            'cells' => 'required|array',
            'cells.*.product_color_id' => 'nullable|integer|exists:product_colors,id',
            'cells.*.size_id' => 'nullable|integer|exists:sizes,id',
            'cells.*.stock' => 'nullable|integer',
            'cells.*.price' => 'nullable|integer|min:0',
            'cells.*.purchase_price' => 'nullable|numeric|min:0',
            'cells.*.sku' => 'nullable|string|max:191',
            'cells.*.low_stock_threshold' => 'nullable|integer|min:0',
            'cells.*.allow_preorder' => 'nullable|boolean',
            'cells.*.preorder_limit' => 'nullable|integer|min:0',
            'cells.*.is_active' => 'nullable|boolean',
        ]);

        $product = Product::findOrFail($productId);
        $userId = $request->user()?->id;

        DB::transaction(function () use ($product, $validated, $userId) {
            $product->update([
                'track_inventory' => (bool) ($validated['track_inventory'] ?? $product->track_inventory),
                'preorder_mode' => $validated['preorder_mode'] ?? $product->preorder_mode ?? 'off',
                'preorder_eta_days' => $validated['preorder_eta_days'] ?? null,
                'preorder_note' => $validated['preorder_note'] ?? null,
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? null,
            ]);

            $position = 0;

            foreach ($validated['cells'] as $cell) {
                $variant = $this->inventory->ensureVariant(
                    (int) $product->id,
                    isset($cell['product_color_id']) ? (int) $cell['product_color_id'] : null,
                    isset($cell['size_id']) ? (int) $cell['size_id'] : null
                );

                $variant->fill([
                    'price' => $cell['price'] ?? null,
                    'purchase_price' => $cell['purchase_price'] ?? null,
                    'sku' => $cell['sku'] ?: null,
                    'low_stock_threshold' => $cell['low_stock_threshold'] ?? null,
                    'allow_preorder' => (bool) ($cell['allow_preorder'] ?? false),
                    'preorder_limit' => $cell['preorder_limit'] ?? null,
                    'is_active' => (bool) ($cell['is_active'] ?? true),
                    'position' => $position++,
                ])->save();

                // Absolute figure from the form -> recorded as an adjustment.
                if (array_key_exists('stock', $cell) && $cell['stock'] !== null) {
                    $this->inventory->adjust(
                        (int) $variant->id,
                        (int) $cell['stock'],
                        StockMovement::TYPE_ADJUSTMENT,
                        'Set from product form',
                        $userId
                    );
                }
            }
        });

        return response()->json([
            'message' => 'Inventory saved successfully',
        ]);
    }

    /** Receive a shipment against many variants at once. */
    public function stockIn(Request $request)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.product_variant_id' => 'required|integer|exists:product_variants,id',
            'lines.*.qty' => 'required|integer|min:1',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $userId = $request->user()?->id;

        DB::transaction(function () use ($validated, $userId) {
            foreach ($validated['lines'] as $line) {
                $this->inventory->stockIn(
                    (int) $line['product_variant_id'],
                    (int) $line['qty'],
                    isset($line['unit_cost']) ? (float) $line['unit_cost'] : null,
                    $validated['note'] ?? null,
                    $userId
                );
            }
        });

        return response()->json([
            'message' => 'Stock received successfully',
            'received_lines' => count($validated['lines']),
        ]);
    }

    /** Absolute on-hand correction with a mandatory reason. */
    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'stock' => 'required|integer',
            'type' => ['nullable', Rule::in([
                StockMovement::TYPE_ADJUSTMENT,
                StockMovement::TYPE_DAMAGE,
                StockMovement::TYPE_PURCHASE,
            ])],
            'note' => 'required|string|max:255',
        ]);

        $variant = $this->inventory->adjust(
            (int) $validated['product_variant_id'],
            (int) $validated['stock'],
            $validated['type'] ?? StockMovement::TYPE_ADJUSTMENT,
            $validated['note'],
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Stock updated successfully',
            'data' => [
                'stock' => $variant->stock,
                'reserved' => $variant->reserved,
                'available' => $variant->available,
            ],
        ]);
    }

    /** The ledger. */
    public function movements(Request $request)
    {
        $movements = StockMovement::query()
            ->with([
                'product:id,title,sku',
                'variant:id,product_id,product_color_id,size_id',
                'variant.color:id,name',
                'variant.size:id,size',
                'user:id,name',
            ])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->filled('product_variant_id'), fn ($q) => $q->where('product_variant_id', $request->product_variant_id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->order_id))
            ->when($request->filled('start_date'), fn ($q) => $q->where('created_at', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn ($q) => $q->where('created_at', '<=', $request->end_date.' 23:59:59'))
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 40));

        return response()->json([
            'message' => 'success',
            'data' => $movements,
        ]);
    }

    /** Products a user can pick from in the Stock In screen. */
    public function trackedProducts(Request $request)
    {
        $products = Product::query()
            ->select('id', 'title', 'sku', 'track_inventory')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('title', 'LIKE', "%{$request->search}%")
                    ->orWhere('sku', 'LIKE', "%{$request->search}%");
            })
            ->orderBy('title')
            ->limit(50)
            ->get();

        return response()->json(['message' => 'success', 'data' => $products]);
    }

    /** Size list, used by the matrix editor for its columns. */
    public function sizes()
    {
        return response()->json([
            'message' => 'success',
            'data' => Size::select('id', 'size')->orderBy('id')->get(),
        ]);
    }
}
