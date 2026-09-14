<?php

/**
 * READ-ONLY pre-flight check for the inventory migrations.
 *
 *   php scripts/preflight-inventory.php
 *
 * Runs nothing but SELECT / SHOW statements. It never inserts, updates, alters
 * or deletes anything, so it is safe to point at the live database during
 * trading hours. It reports exactly what the migrations will do before you
 * commit to running them.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$warnings = [];
$blockers = [];

function heading(string $text): void
{
    echo "\n".$text."\n".str_repeat('=', strlen($text))."\n";
}

function line(string $label, $value, string $note = ''): void
{
    echo '  '.str_pad($label, 44).str_pad((string) $value, 12, ' ', STR_PAD_LEFT);
    echo $note !== '' ? '   '.$note : '';
    echo "\n";
}

function tableExists(string $table): bool
{
    return Schema::hasTable($table);
}

function rowsIn(string $table): int
{
    return tableExists($table) ? (int) DB::table($table)->count() : 0;
}

/* ------------------------------------------------------------------ */
heading('Environment');

$database = DB::connection()->getDatabaseName();
$version = DB::selectOne('SELECT VERSION() AS v')->v;

line('Database', $database);
line('MySQL version', $version);
line('APP_ENV', config('app.env'));
line('Migrations already applied', rowsIn('migrations'));

$pending = [
    '2026_09_12_100001_create_product_colors_table',
    '2026_09_12_100002_add_inventory_fields_to_products_table',
    '2026_09_12_100003_add_track_inventory_to_categories_table',
    '2026_09_12_100004_add_inventory_enforcement_to_site_settings_table',
    '2026_09_12_100005_rebuild_product_variants_table',
    '2026_09_12_100006_create_stock_movements_table',
    '2026_09_12_100007_add_inventory_fields_to_order_items_table',
    '2026_09_12_100008_backfill_inventory_variants',
];

$alreadyRun = DB::table('migrations')->whereIn('migration', $pending)->pluck('migration')->all();

if ($alreadyRun !== []) {
    $warnings[] = count($alreadyRun).' of the 8 inventory migrations have already run here.';
}
line('Inventory migrations already run', count($alreadyRun).' / 8');

/* ------------------------------------------------------------------ */
heading('Tables the migration will ALTER (lock risk)');

// Approximate size drives how long the ALTER holds a lock.
$sizes = DB::select(
    "SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_est,
            ROUND((DATA_LENGTH + INDEX_LENGTH) / 1048576, 1) AS mb
       FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('products','categories','order_items','site_settings','product_variants','product_sizes','orders')"
);

foreach ($sizes as $row) {
    line($row->name, (int) $row->rows_est.' rows', $row->mb.' MB');

    if ((int) $row->rows_est > 200000) {
        $warnings[] = "`{$row->name}` has roughly {$row->rows_est} rows; the ALTER on it will take noticeably longer.";
    }
}

/* ------------------------------------------------------------------ */
heading('Pre-requisite columns (a missing one aborts the migration)');

// The migrations use ->after('x'); MySQL errors if x does not exist.
$required = [
    'products' => 'status',
    'categories' => 'size_guide_type',
    'site_settings' => 'primary_color',
    'order_items' => 'product_variant_id',
];

foreach ($required as $table => $column) {
    $ok = tableExists($table) && Schema::hasColumn($table, $column);
    line("{$table}.{$column}", $ok ? 'present' : 'MISSING');

    if (! $ok) {
        $blockers[] = "`{$table}.{$column}` is missing — the migration positions new columns after it and will fail.";
    }
}

/* ------------------------------------------------------------------ */
heading('Name collisions');

$legacyExists = tableExists('product_variants_legacy');
line('product_variants_legacy already exists', $legacyExists ? 'YES' : 'no');

if ($legacyExists) {
    $warnings[] = 'product_variants_legacy already exists, so the rename step will be skipped.';
}

foreach (['product_colors', 'stock_movements'] as $table) {
    $exists = tableExists($table);
    line("{$table} already exists", $exists ? 'YES' : 'no');

    if ($exists) {
        $warnings[] = "`{$table}` already exists; its CREATE will be skipped.";
    }
}

$fkCollision = DB::select(
    "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'order_items_variant_fk'"
);
line('order_items_variant_fk exists', $fkCollision !== [] ? 'YES' : 'no');

$indexCollision = DB::select(
    "SELECT INDEX_NAME FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
        AND INDEX_NAME = 'products_track_inventory_status_index'"
);
line('products_track_inventory_status_index exists', $indexCollision !== [] ? 'YES' : 'no');

/* ------------------------------------------------------------------ */
heading('Legacy data that will be touched');

// Before migrating, `product_variants` is the old table. Afterwards it is the
// new one and the old rows live in `product_variants_legacy`.
if ($legacyExists) {
    line('product_variants_legacy rows (archived)', rowsIn('product_variants_legacy'), 'already renamed');
    line('product_variants rows (new table)', rowsIn('product_variants'));
} else {
    line('product_variants rows (to be renamed)', rowsIn('product_variants'), 'preserved, not dropped');
}

$orphanCandidates = 0;

if (tableExists('order_items') && Schema::hasColumn('order_items', 'product_variant_id')) {
    $orphanCandidates = (int) DB::table('order_items')->whereNotNull('product_variant_id')->count();
}

line('order_items with a variant id', $orphanCandidates, 'archived to legacy_product_variant_id');

if ($orphanCandidates > 0) {
    $warnings[] = "{$orphanCandidates} order_items rows carry an old product_variant_id. The value is copied to "
        .'legacy_product_variant_id and then cleared, because those ids referenced the old attribute table.';
}

line('product_stocks rows (left alone)', rowsIn('product_stocks'));
line('product_sizes rows (left alone)', rowsIn('product_sizes'));

/* ------------------------------------------------------------------ */
heading('What the backfill will create');

$products = DB::table('products')->select('id', 'colors')->get();

$sizeCounts = tableExists('product_sizes')
    ? DB::table('product_sizes')->selectRaw('product_id, COUNT(*) AS c')->groupBy('product_id')->pluck('c', 'product_id')
    : collect();

$sizeStock = tableExists('product_sizes') && Schema::hasColumn('product_sizes', 'stock')
    ? DB::table('product_sizes')->selectRaw('product_id, SUM(stock) AS s')->groupBy('product_id')->pluck('s', 'product_id')
    : collect();

$totalColors = 0;
$estimatedVariants = 0;
$inheritProducts = 0;
$inheritUnits = 0;
$manualProducts = 0;
$bothAxes = 0;
$malformedColors = 0;

foreach ($products as $product) {
    $colors = [];

    if (! empty($product->colors)) {
        $decoded = json_decode($product->colors, true);
        if (is_array($decoded)) {
            $colors = $decoded;
        } else {
            $malformedColors++;
        }
    }

    $colorCount = count($colors);
    $sizeCount = (int) ($sizeCounts[$product->id] ?? 0);

    $totalColors += $colorCount;
    $estimatedVariants += max(1, $colorCount) * max(1, $sizeCount);

    if ($colorCount === 0 && $sizeCount > 0) {
        $inheritProducts++;
        $inheritUnits += (int) ($sizeStock[$product->id] ?? 0);
    } else {
        $manualProducts++;
    }

    if ($colorCount > 0 && $sizeCount > 0) {
        $bothAxes++;
    }
}

line('Products', $products->count());
line('Colour entries in products.colors', $totalColors, 'become product_colors rows');
line('Variant rows to be created', $estimatedVariants);
line('Products inheriting their stock count', $inheritProducts, $inheritUnits.' units carried over');
line('Products needing counts entered by hand', $manualProducts);
line('  of those, colour x size grids', $bothAxes);

if ($malformedColors > 0) {
    $warnings[] = "{$malformedColors} products have a colors value that is not valid JSON; they will be treated as having no colours.";
}

if ($estimatedVariants > 50000) {
    $warnings[] = "The backfill will insert about {$estimatedVariants} rows, which will take a while.";
}

/* ------------------------------------------------------------------ */
heading('Order pipeline at cut-over');

// Orders already placed will not deduct stock, because they were never held.
$reserveStatuses = App\Services\InventoryService::RESERVE_STATUSES;
$commitStatuses = App\Services\InventoryService::COMMIT_STATUSES;

$inFlight = DB::table('orders')->whereIn('status', $reserveStatuses)->count();
line('Orders still in the pipeline', $inFlight, 'these will NOT deduct stock');

$byStatus = DB::table('orders')
    ->select('status', DB::raw('COUNT(*) AS c'))
    ->whereIn('status', array_merge($reserveStatuses, $commitStatuses))
    ->groupBy('status')
    ->orderByDesc('c')
    ->get();

foreach ($byStatus as $row) {
    line('  '.$row->status, $row->c);
}

$pipelineUnits = (int) DB::table('order_items')
    ->join('orders', 'orders.id', '=', 'order_items.order_id')
    ->whereIn('orders.status', $reserveStatuses)
    ->sum('order_items.qty');

line('Units inside those orders', $pipelineUnits);

if ($pipelineUnits > 0) {
    $warnings[] = "{$pipelineUnits} units sit in orders placed before the cut-over. They will ship without reducing "
        .'stock, so counts entered today will read high until that pipeline clears.';
}

/* ------------------------------------------------------------------ */
heading('Safety switches');

if (tableExists('site_settings') && Schema::hasColumn('site_settings', 'inventory_enforcement_enabled')) {
    $enforced = DB::table('site_settings')->value('inventory_enforcement_enabled');
    line('Enforcement currently', $enforced ? 'ON' : 'off');
} else {
    line('Enforcement column', 'not yet added', 'will default to off');
}

$trackedCategories = tableExists('categories') && Schema::hasColumn('categories', 'track_inventory')
    ? DB::table('categories')->where('track_inventory', true)->count()
    : 0;

line('Categories with tracking on', $trackedCategories, 'will default to 0');

/* ------------------------------------------------------------------ */
heading('Result');

if ($blockers !== []) {
    echo "\n  BLOCKERS — do not migrate until these are resolved:\n";
    foreach ($blockers as $item) {
        echo '   x '.wordwrap($item, 96, "\n     ")."\n";
    }
}

if ($warnings !== []) {
    echo "\n  Things to be aware of:\n";
    foreach ($warnings as $item) {
        echo '   ! '.wordwrap($item, 96, "\n     ")."\n";
    }
}

if ($blockers === [] && $warnings === []) {
    echo "\n  Clear. Nothing unexpected in this database.\n";
}

echo "\n  This script made no changes.\n";

exit($blockers === [] ? 0 : 1);
