<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'type', 'unit', 'price', 'base_cost', 'markup', 'evomi_product_id', 'is_active', 'is_trial', 'tagline', 'features', 'volume_discounts'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_trial'  => 'boolean',
        'price' => 'decimal:2',
        'base_cost' => 'decimal:2',
        'markup' => 'decimal:2',
        'features' => 'array',
        'volume_discounts' => 'array',
    ];
}
