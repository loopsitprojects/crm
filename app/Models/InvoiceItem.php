<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_price', 'amount', 'sscl_amount', 'vat_amount', 'total_with_vat', 'department', 'revenue_category'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
