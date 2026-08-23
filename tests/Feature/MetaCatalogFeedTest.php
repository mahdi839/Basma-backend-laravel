<?php

namespace Tests\Feature;

use App\Services\MetaCatalogFeedService;
use Tests\TestCase;

class MetaCatalogFeedTest extends TestCase
{
    public function test_meta_can_fetch_the_public_catalog_feed(): void
    {
        $this->app->instance(MetaCatalogFeedService::class, new class extends MetaCatalogFeedService
        {
            public function generate(?iterable $products = null): string
            {
                return "\xEF\xBB\xBFid,title\n125,Test Product\n";
            }
        });

        $response = $this->get('/api/meta/catalog.csv');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            'inline; filename="eyara-fashion-meta-catalog.csv"'
        );
        $response->assertSee('125,Test Product', false);
    }
}
