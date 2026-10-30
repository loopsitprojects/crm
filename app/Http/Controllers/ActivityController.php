<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('user')->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('subject_type')) {
            $type = strtolower($request->subject_type);
            $map = [
                'estimate' => \App\Models\Estimate::class,
                'invoice' => \App\Models\Invoice::class,
                'temp_invoice' => \App\Models\TempInvoice::class,
                'deal' => \App\Models\Deal::class,
                'user' => \App\Models\User::class,
                'customer' => \App\Models\Customer::class,
            ];

            if (isset($map[$type])) {
                $query->where('subject_type', $map[$type]);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $activities = $query->paginate(50)->withQueryString();

        return view('activities.index', compact('activities'));
    }
}
