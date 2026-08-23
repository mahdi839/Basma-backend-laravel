<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MetaCatalogFeedService;
use Illuminate\Http\Response;

class MetaCatalogController extends Controller
{
    public function __invoke(MetaCatalogFeedService $feed): Response
    {
        return response($feed->generate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="eyara-fashion-meta-catalog.csv"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
