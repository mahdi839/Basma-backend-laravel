<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterSetting extends Model
{
     protected $fillable = [
        'logo_path',
        'company_description',
        'company_address',
        'company_email',
        'company_phone',
    ];
}
