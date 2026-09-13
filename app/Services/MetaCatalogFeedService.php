<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MetaCatalogFeedService
{
    private const HEADERS = [
        'id',
        'title',
        'description',
        'availability',
        'inventory',
        'condition',
        'price',
        'sale_price',
        'link',
        'image_link',
        'additional_image_link',
        'brand',
        'product_type',
    ];

    /**
     * Generate a complete UTF-8 CSV feed for Meta Commerce Manager.
     *
     * Supplying products is useful for isolated tests. In production, the
     * service reads the current product catalog directly from the database.
     */
    public function generate(?iterable $products = null): string
    {
        $products ??= $this->catalogProducts();

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to create the Meta catalog feed.');
        }

        // A UTF-8 BOM helps spreadsheet/feed readers preserve Bengali text.
        fwrite($stream, "\xEF\xBB\xBF");
        $this->writeCsvRow($stream, self::HEADERS);

        foreach ($products as $product) {
            $row = $this->productRow($product);

            if ($row === null) {
                continue;
            }

            $this->writeCsvRow($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($csv === false) {
            throw new RuntimeException('Unable to read the generated Meta catalog feed.');
        }

        return $csv;
    }

    /**
     * Convert one existing product into one Meta catalog item.
     *
     * Colour-size variants are deliberately still collapsed into a single row.
     * Splitting them would change every item id in the live catalog, which would
     * break the Pixel content_ids already matching on products.id and force the
     * existing ad sets back into learning. Availability and inventory are now
     * real numbers, which is the part that actually stops selling air.
     */
    public function productRow(Product $product): ?array
    {
        $regularPrice = $this->regularPrice($product);
        $primaryImage = $product->images->first()?->image;

        if ($regularPrice === null || empty($primaryImage)) {
            Log::warning('Product omitted from Meta catalog feed because required data is missing.', [
                'product_id' => $product->id,
                'has_price' => $regularPrice !== null,
                'has_image' => ! empty($primaryImage),
            ]);

            return null;
        }

        $salePrice = $this->salePrice($product, $regularPrice);
        $images = $product->images
            ->pluck('image')
            ->filter()
            ->map(fn (string $image): string => $this->absoluteUrl(
                config('meta_catalog.image_base_url'),
                $image
            ))
            ->values();

        $tracked = (bool) $product->track_inventory;
        $available = (int) ($product->variants_available ?? 0);

        return [
            (string) $product->id,
            $this->plainText($product->title),
            $this->description($product),
            $this->availability($product, $tracked, $available),
            $tracked ? (string) max(0, $available) : '',
            'new',
            $this->formatPrice($regularPrice),
            $salePrice !== null ? $this->formatPrice($salePrice) : '',
            $this->productUrl($product),
            $images->first(),
            $images->slice(1)->implode(','),
            $this->plainText((string) config('meta_catalog.brand')),
            $this->plainText((string) ($product->category->first()?->name ?? '')),
        ];
    }

    private function catalogProducts(): Collection
    {
        return Product::query()
            ->select([
                'id',
                'title',
                'short_description',
                'description',
                'price',
                'discount',
                'status',
                'track_inventory',
                'preorder_mode',
            ])
            // Sellable units across the whole variant matrix, in one subquery.
            ->selectSub(
                ProductVariant::selectRaw('COALESCE(SUM(GREATEST(stock - reserved, 0)), 0)')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('is_active', true),
                'variants_available'
            )
            ->with([
                'images:id,product_id,image,position',
                'sizes:id,size',
                'category:id,name',
            ])
            ->orderBy('id')
            ->get();
    }

    private function description(Product $product): string
    {
        $description = $product->short_description
            ?: $product->description
            ?: $product->title;

        return $this->plainText((string) $description);
    }

    /**
     * Real stock wins over the manual status flag, but only for products that
     * actually track inventory. Everything else keeps its existing behaviour.
     */
    private function availability(Product $product, bool $tracked, int $available): string
    {
        if ($product->status === 'sold') {
            return 'out of stock';
        }

        if ($tracked && $available <= 0) {
            return $product->allowsPreorder() ? 'preorder' : 'out of stock';
        }

        return match ($product->status) {
            'prebook' => 'preorder',
            default => 'in stock',
        };
    }

    private function regularPrice(Product $product): ?float
    {
        if (is_numeric($product->price) && (float) $product->price > 0) {
            return (float) $product->price;
        }

        $sizePrice = $product->sizes
            ->map(fn ($size) => $size->pivot?->price)
            ->filter(fn ($price) => is_numeric($price) && (float) $price > 0)
            ->map(fn ($price) => (float) $price)
            ->min();

        if ($sizePrice !== null) {
            return $sizePrice;
        }

        if (is_numeric($product->discount) && (float) $product->discount > 0) {
            return (float) $product->discount;
        }

        return null;
    }

    private function salePrice(Product $product, float $regularPrice): ?float
    {
        if (! is_numeric($product->discount)) {
            return null;
        }

        $salePrice = (float) $product->discount;

        return $salePrice > 0 && $salePrice < $regularPrice
            ? $salePrice
            : null;
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '').' '.strtoupper(
            (string) config('meta_catalog.currency', 'BDT')
        );
    }

    private function productUrl(Product $product): string
    {
        $path = str_replace(
            '{id}',
            rawurlencode((string) $product->id),
            (string) config('meta_catalog.product_url_pattern')
        );

        return $this->absoluteUrl(config('meta_catalog.storefront_url'), $path);
    }

    private function absoluteUrl(?string $baseUrl, string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return rtrim((string) $baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** @param resource $stream */
    private function writeCsvRow($stream, array $row): void
    {
        if (fputcsv($stream, $row, ',', '"', '', "\n") === false) {
            throw new RuntimeException('Unable to write the Meta catalog feed.');
        }
    }
}
