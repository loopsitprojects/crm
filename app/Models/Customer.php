<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'billing_address',
        'telephone',
        'fax',
        'business_registration_number',
        'primary_contact_name',
        'primary_contact_designation',
        'primary_contact_mobile',
        'primary_contact_office',
        'primary_contact_email',
        'promo_contact_name',
        'promo_contact_designation',
        'promo_contact_mobile',
        'promo_contact_office',
        'promo_contact_email',
        'customer_tax_number',
        'customer_vat_registration_number',
        'customer_suspended_vat_registration_number',
        'approved_credit_period',
        'approved_credit_limit',
    ];

    public function estimates()
    {
        return $this->hasMany(Estimate::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
