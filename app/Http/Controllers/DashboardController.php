<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;

use App\Models\Deal;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Handle Filters
        $month = $request->input('month', 'all');
        $brandFilter = $request->input('brand', 'all');
        $managerFilter = $request->input('manager', 'all');
        $departmentFilter = $request->input('department', 'all');
        $stageFilter = $request->input('stage', 'all');

        $months = [
            '1' => 'January', '2' => 'February', '3' => 'March', '4' => 'April',
            '5' => 'May', '6' => 'June', '7' => 'July', '8' => 'August',
            '9' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
        ];
        
        // Populate brands from Customer brand field
        $brands = Customer::whereNotNull('brand')->where('brand', '!=', '')->distinct()->pluck('brand', 'brand')->toArray();
        asort($brands);

        $user = auth()->user();
        $userRole = $user->role;
        $userDept = $user->department;

        $managers = \App\Models\User::where('role', 'Manager');
        if ($userRole === 'Manager') {
            $managers->where('id', $user->id);
        }
        $managers = $managers->pluck('name', 'id');

        $departments = ['Corporate', 'Creative', 'Digital', 'Play', 'Tech'];
        if ($userRole === 'HOD' && $userDept) {
            $departments = [$userDept];
        }
        
        $stages = Deal::whereNotNull('stage')->distinct();
        if ($userRole === 'HOD' && $userDept) {
            $stages->where('department_split', 'like', '%' . $userDept . '%');
        } elseif ($userRole === 'Manager') {
            $stages->where('user_id', $user->id);
        }
        $stages = $stages->pluck('stage', 'stage');

        // 2. Base Query context
        $query = Deal::with(['owner', 'customer', 'estimates' => function($q) {
            $q->with('items')->where('status', 'Approved');
        }]);

        // Role-based filtering
        if ($userRole === 'HOD' && $userDept) {
            $query->where('department_split', 'like', '%' . $userDept . '%');
        } elseif ($userRole === 'Manager') {
            $query->where('user_id', $user->id);
        }

        if ($month !== 'all') {
            $query->whereMonth('created_at', $month);
        }
        if ($managerFilter !== 'all') {
            $query->where('user_id', $managerFilter);
        }
        if ($departmentFilter !== 'all') {
            $query->where('department_split', 'like', '%' . $departmentFilter . '%');
        }
        if ($stageFilter !== 'all') {
            $query->where('stage', $stageFilter);
        }
        if ($brandFilter !== 'all') {
            $query->whereHas('customer', function($q) use ($brandFilter) {
                $q->where('brand', $brandFilter);
            });
        }

        $deals = $query->get();

        // 3. Calculate KPIs and Chart Data
        $totalContribution = 0;
        foreach ($deals as $deal) {
            if ($userRole === 'HOD' && $userDept) {
                // For HOD, only sum their department's share
                $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (($split['department'] ?? '') === $userDept) {
                            $totalContribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            } else {
                $totalContribution += $deal->contribution;
            }
        }

        // Horizontal Bar: Contribution - Account Manager
        $managerContribution = $deals->groupBy(function($d) {
            return $d->owner->name ?? 'Unknown';
        })->map(function($group) use ($userRole, $userDept) {
            if ($userRole === 'HOD' && $userDept) {
                // For HOD, filter contribution by department within the manager group
                $sum = 0;
                foreach ($group as $deal) {
                    $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                    if (is_array($splits)) {
                        foreach ($splits as $split) {
                            if (($split['department'] ?? '') === $userDept) {
                                $sum += (float)($split['contribution_amount'] ?? 0);
                            }
                        }
                    }
                }
                return $sum;
            }
            return $group->sum('contribution');
        })->sortDesc();

        // Vertical Bar: Departmentwise Contribution
        $departmentContribution = [];
        foreach ($departments as $dept) {
            $departmentContribution[$dept] = 0;
        }

        foreach ($deals as $deal) {
            if ($deal->department_split) {
                $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        $deptName = $split['department'] ?? null;
                        if ($deptName && isset($departmentContribution[$deptName])) {
                            // If HOD, only show their own department data in the chart
                            if ($userRole === 'HOD' && $userDept && $deptName !== $userDept) {
                                continue;
                            }
                            $departmentContribution[$deptName] += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            }
        }
        arsort($departmentContribution);

        // Horizontal Bar: Brandwise Contribution
        $brandContribution = [];
        foreach ($deals as $deal) {
            // Use customer's brand, fallback to 'Unknown'
            $brand = ($deal->customer && $deal->customer->brand) 
                ? $deal->customer->brand 
                : 'Unknown';
            
            $contribution = $deal->contribution;
            if ($userRole === 'HOD' && $userDept) {
                $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                $contribution = 0;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (($split['department'] ?? '') === $userDept) {
                            $contribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            }
                
            $brandContribution[$brand] = ($brandContribution[$brand] ?? 0) + $contribution;
        }
        arsort($brandContribution);

        // Donut: Revenuewise contribution (Summing item amounts grouped by category)
        $revenueCategoryContribution = [];
        foreach ($deals as $deal) {
            $approvedEstimate = $deal->estimates->first();
            if ($approvedEstimate && $approvedEstimate->items) {
                foreach ($approvedEstimate->items as $item) {
                    $category = $item->revenue_category ?? 'Uncategorized';
                    
                    $amount = $item->amount;
                    if ($userRole === 'HOD' && $userDept && ($item->department ?? '') !== $userDept) {
                        continue;
                    }

                    $revenueCategoryContribution[$category] = ($revenueCategoryContribution[$category] ?? 0) + $amount;
                }
            }
        }
        arsort($revenueCategoryContribution);

        // Key Campaigns Table
        $keyCampaigns = $deals->map(function($d) use ($userRole, $userDept) {
            $approvedEstimate = $d->estimates->first();
            $title = $d->title;
            if ($approvedEstimate && $approvedEstimate->description) {
                $title = $approvedEstimate->description;
            }

            $contribution = $d->contribution;
            if ($userRole === 'HOD' && $userDept) {
                $splits = is_string($d->department_split) ? json_decode($d->department_split, true) : $d->department_split;
                $contribution = 0;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (($split['department'] ?? '') === $userDept) {
                            $contribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            }

            return [
                'description' => $title,
                'contribution' => $contribution
            ];
        })->sortByDesc('contribution')->values();

        return view('dashboard', compact(
            'month', 'brandFilter', 'managerFilter', 'departmentFilter', 'stageFilter',
            'months', 'brands', 'managers', 'departments', 'stages',
            'totalContribution', 'managerContribution', 'departmentContribution',
            'brandContribution', 'revenueCategoryContribution', 'keyCampaigns'
        ));
    }
}
