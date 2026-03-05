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
        'advance_received_amount',
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

    public function thirdPartyCosts()
    {
        return $this->hasMany(ThirdPartyCost::class, 'quotation_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }


    public static function generateReferenceNumber()
    {
        $year = date('Y');
        $prefix = "EST/{$year}/";

        $lastEstimate = self::where('reference_number', 'like', $prefix . '%')
            ->orderBy('reference_number', 'desc')
            ->first();

        if (!$lastEstimate) {
            $sequence = 1;
        } else {
            // Extract the number from EST/YYYY/XXXX
            $parts = explode('/', $lastEstimate->reference_number);
            $lastSequence = (int) end($parts);
            $sequence = $lastSequence + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
