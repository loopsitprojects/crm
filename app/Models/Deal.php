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
    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Estimate::class, 'deal_id', 'quotation_id');
    }

    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'deal_user')->withPivot('cost_allocation')->withTimestamps();
    }

    /**
     * Check if a specific user can edit this deal.
     * Logic: Super Admin, Management, or Deal Owner. HOD users can edit their own deals only.
     * 
     * @param User|null $user
     * @return bool
     */
    public function canEdit($user = null)
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;

        // 1. Super Admin or Management override
        if ($user->hasRole('Super Admin') || $user->hasRole('Management')) {
            return true;
        }

        // 2. The Owner (who added the deal)
        if ($this->user_id === $user->id) {
            return true;
        }

        // 3. HOD role users can edit their own deals ONLY
        if ($user->hasRole('HOD')) {
            return false;
        }

        // 4. Check by direct supervisor_id (for non-HOD roles)
        if ($this->owner && $this->owner->supervisor_id === $user->id) {
            return true;
        }

        return false;
    }
}
