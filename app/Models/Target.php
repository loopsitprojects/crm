<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    protected $fillable = [
        'type',
        'department',
        'user_id',
        'year',
        'month',
        'target_amount'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getDepartmentTarget(string $department, ?int $year = null, ?int $month = null): float
    {
        $year = $year ?: (int) date('Y');
        $month = $month ?: (int) date('n');

        $target = static::where('type', 'department')
            ->where('department', $department)
            ->where('year', $year)
            ->where('month', $month)
            ->value('target_amount');

        if ($target !== null) {
            return (float) $target;
        }

        if (TargetPeriod::exists() && !TargetPeriod::where('year', $year)->where('month', $month)->exists()) {
            return 0.0;
        }

        $defaultTarget = static::where('type', 'department')
            ->where('department', $department)
            ->whereNull('month')
            ->value('target_amount');

        return $defaultTarget !== null ? (float) $defaultTarget : 0.0;
    }

    public static function getUserTarget(int $userId, ?int $year = null, ?int $month = null): float
    {
        $year = $year ?: (int) date('Y');
        $month = $month ?: (int) date('n');

        $target = static::where('type', 'user')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->where('month', $month)
            ->value('target_amount');

        if ($target !== null) {
            return (float) $target;
        }

        if (TargetPeriod::exists() && !TargetPeriod::where('year', $year)->where('month', $month)->exists()) {
            return 0.0;
        }

        $defaultTarget = static::where('type', 'user')
            ->where('user_id', $userId)
            ->whereNull('month')
            ->value('target_amount');

        return $defaultTarget !== null ? (float) $defaultTarget : 0.0;
    }
}
