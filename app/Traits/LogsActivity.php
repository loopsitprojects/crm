<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected function logAction($description, $subject = null, $properties = [], $userId = null)
    {
        $resolvedUserId = is_object($userId) && isset($userId->id) ? $userId->id : ($userId ?? Auth::id());

        Activity::create([
            'user_id' => $resolvedUserId,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'properties' => $properties,
            'ip_address' => request()->ip(),
        ]);
    }
}
