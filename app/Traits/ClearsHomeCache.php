<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ClearsHomeCache {
     /**
     * Clear all home category caches
     */

    protected function clearHomeCategoryCach (){
        for ($page = 1; $page <= 25; $page++) {
            Cache::forget("home_categories_page_{$page}");
        }
    }
}
