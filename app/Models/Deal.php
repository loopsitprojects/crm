<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    protected $fillable = [
        'title',
        'customer_id',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'amount',
        'currency',
        'stage',
        'pipeline',
        'type',
        'priority',
        'close_date'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
