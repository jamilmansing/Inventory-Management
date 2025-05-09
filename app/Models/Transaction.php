<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type', // purchase, sale, adjustment
        'quantity',
        'unit_price',
        'total_price',
        'reference',
        'notes',
        'odoo_transaction_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}