<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = Deal::whereNotNull('job_number');

        // Access Control
        $user = auth()->user();
        if (!in_array($user->role, ['Super Admin', 'Management'])) {
            $query->where('user_id', $user->id);
        }

        // Filters
        // 1. Date Range (created_at)
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // 2. Department (Check if department_split JSON contains the department)
        if ($request->has('department') && $request->department) {
            // Assuming department_split is stored as JSON list of objects [{ "department": "Creative", ... }]
            // We use whereJsonContains or similar logic. 
            // Since structure is [{"department": "Name", ...}], we search if any element has "department": "Name"
            // MySQL 5.7+ supports JSON paths. 
            $query->whereJsonContains('department_split', [['department' => $request->department]]);
        }

        // 3. User (Deal Owner)
        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $jobs = $query->orderBy('job_number', 'desc')->get();

        // Data for dropdowns
        $users = \App\Models\User::all();
        $departments = ['Creative', 'Digital', 'Play', 'Tech']; // Default departments

        return view('jobs.index', compact('jobs', 'users', 'departments'));
    }
}
