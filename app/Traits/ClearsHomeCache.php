<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ClearsHomeCache {
     /**
     * Clear all home category caches
     */

    protected function clearHomeCategoryCach (){
        Cache::tags(['home_categories'])->flush();
    }
}