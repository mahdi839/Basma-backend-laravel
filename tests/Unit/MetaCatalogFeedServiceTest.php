<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\Size;
use App\Services\MetaCatalogFeedService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MetaCatalogFeedServiceTest extends TestCase
{
    public function test_it_generates_a_product_level_meta_catalog_row(): void
    {
        config()->set('meta_catalog.brand', 'Eyara Fashion');
        config()->set('meta_catalog.currency', 'BDT');
        config()->set('meta_catalog.storefront_url', 'https://eyarafashion.xyz');
        config()->set('meta_catalog.product_url_pattern', '/frontEnd/product-page/{id}');
        config()->set('meta_catalog.image_base_url', 'https://api.eyarafashion.xyz');

        $product = new Product([
            'title' => 'Premium, Women Shoe',
            'short_description' => '<p>Comfortable &amp; elegant shoe.</p>',
            'price' => 2500,
            'discount' => 2200,
            'status' => 'in-stock',
        ]);
        $product->id = 125;
        $product->setRelation('images', new Collection([
            new ProductImage(['image' => 'uploads/product_photos/main.jpg']),
            new ProductImage(['image' => 'uploads/product_photos/side.jpg']),
        ]));
        $product->setRelation('sizes', new Collection());
        $product->setRelation('category', new Collection([
            new Category(['name' => 'Women Shoes']),
        ]));

        $csv = app(MetaCatalogFeedService::class)->generate([$product]);
        [$headers, $row] = $this->parseCsv($csv);
        $item = array_combine($headers, $row);

        $this->assertSame('125', $item['id']);
        $this->assertSame('Premium, Women Shoe', $item['title']);
        $this->assertSame('Comfortable & elegant shoe.', $item['description']);
        $this->assertSame('in stock', $item['availability']);
        $this->assertSame('2500.00 BDT', $item['price']);
        $this->assertSame('2200.00 BDT', $item['sale_price']);
        $this->assertSame(
            'https://eyarafashion.xyz/frontEnd/product-page/125',
            $item['link']
        );
        $this->assertSame(
            'https://api.eyarafashion.xyz/uploads/product_photos/main.jpg',
            $item['image_link']
        );
        $this->assertSame(
            'https://api.eyarafashion.xyz/uploads/product_photos/side.jpg',
            $item['additional_image_link']
        );
        $this->assertSame('Eyara Fashion', $item['brand']);
        $this->assertSame('Women Shoes', $item['product_type']);
    }

    public function test_it_maps_existing_product_statuses_to_meta_availability(): void
    {
        $service = app(MetaCatalogFeedService::class);

        $this->assertSame('out of stock', $this->rowForStatus($service, 'sold')[3]);
        $this->assertSame('preorder', $this->rowForStatus($service, 'prebook')[3]);
        $this->assertSame('in stock', $this->rowForStatus($service, 'in-stock')[3]);
    }

    public function test_it_omits_products_without_a_price_or_image(): void
    {
        $product = new Product([
            'title' => 'Incomplete product',
            'status' => 'in-stock',
        ]);
        $product->id = 999;
        $product->setRelation('images', new Collection());
        $product->setRelation('sizes', new Collection());
        $product->setRelation('category', new Collection());

        $this->assertNull(app(MetaCatalogFeedService::class)->productRow($product));
    }

    public function test_it_uses_the_lowest_size_price_when_the_product_has_no_base_price(): void
    {
        $size38 = new Size(['size' => '38']);
        $size38->setRelation('pivot', new ProductSize(['price' => 2400]));

        $size40 = new Size(['size' => '40']);
        $size40->setRelation('pivot', new ProductSize(['price' => 2600]));

        $product = new Product([
            'title' => 'Size-priced shoe',
            'short_description' => 'Description',
            'status' => 'in-stock',
        ]);
        $product->id = 45;
        $product->setRelation('images', new Collection([
            new ProductImage(['image' => 'image.jpg']),
        ]));
        $product->setRelation('sizes', new Collection([$size38, $size40]));
        $product->setRelation('category', new Collection());

        $row = app(MetaCatalogFeedService::class)->productRow($product);

        $this->assertSame('2400.00 BDT', $row[5]);
    }

    private function rowForStatus(MetaCatalogFeedService $service, string $status): array
    {
        $product = new Product([
            'title' => 'Status product',
            'short_description' => 'Description',
            'price' => 1000,
            'status' => $status,
        ]);
        $product->id = 1;
        $product->setRelation('images', new Collection([
            new ProductImage(['image' => 'image.jpg']),
        ]));
        $product->setRelation('sizes', new Collection());
        $product->setRelation('category', new Collection());

        return $service->productRow($product);
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $csv);
        rewind($stream);

        $headers = fgetcsv($stream);
        $row = fgetcsv($stream);
        fclose($stream);

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        return [$headers, $row];
    }
}
