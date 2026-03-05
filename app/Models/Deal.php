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
        'revenue',
        'contribution',
        'project_cost',
        'currency',
        'stage',
        'pipeline',
        'type',
        'priority',
        'winning_percentage',
        'close_date',
        'job_number',
        'rejection_reason',
        'senior_manager'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function estimates()
    {
        return $this->hasMany(Estimate::class);
    }

    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'deal_user')->withPivot('cost_allocation')->withTimestamps();
    }
}
