<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $table = 'quotations'; // Keep existing table name

    protected $fillable = [
        'customer_id',
        'deal_id',
        'brand_name',
        'reference_number',
        'date',
        'total_amount',
        'status',
        'attention_to',
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'designation',
        'currency',
        'heading',
        'terms',
        'special_terms',
        'advance_payment',
        'advance_percentage',
        'proforma_invoice',
        'third_party_cost',
        'proforma_percentage',
        'proforma_tax',
        'senior_manager',
        'additional_notes',
        'sscl_applicable',
        'vat_applicable'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'quotation_id');
    }

    public function items()
    {
        return $this->hasMany(EstimateItem::class, 'quotation_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
