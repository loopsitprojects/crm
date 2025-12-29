<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimateItem extends Model
{
    protected $table = 'quotation_items'; // Keep existing table name

    protected $fillable = [
        'quotation_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'vat_amount',
        'total_with_vat',
        'sscl_amount',
        'item_heading',
        'locations',
        'days'
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class, 'quotation_id');
    }
}
