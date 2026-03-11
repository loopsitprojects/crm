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
        
        // Populate brands from Estimate brand_name field
        $brands = Estimate::whereNotNull('brand_name')
            ->where('brand_name', '!=', '')
            ->distinct()
            ->orderBy('brand_name')
            ->pluck('brand_name', 'brand_name')
            ->toArray();

        $user = auth()->user();
        $userRole = $user->role;
        $userDept = $user->department;

        $managers = \App\Models\User::where('role', 'Manager');
        if ($userRole === 'Manager') {
            $managers->where('id', $user->id);
        } elseif ($userRole === 'HOD' && $userDept) {
            $managers->where('department', $userDept);
        }
        $managers = $managers->pluck('name', 'id');

        $departments = ['Creative', 'Digital', 'Tech'];
        if (in_array($userRole, ['HOD', 'Manager']) && $userDept) {
            $departments = [$userDept];
        }
        
        $stages = ['Objection handling', 'Finalizing terms', 'Closed Won'];
        $stages = array_combine($stages, $stages);

        // Define Category Mappings
        $sbuDepts = ['Creative', 'Digital', 'Tech'];
        $salesDepts = ['AM', 'BD'];

        // 2. Base Query context
        $query = Deal::with(['owner', 'customer', 'estimates' => function($q) {
            $q->with('items')->where('status', 'Approved');
        }]);

        // Role-based filtering
        if ($userRole === 'HOD' && $userDept) {
            $query->whereJsonContains('department_split', [['department' => $userDept]]);
        } elseif ($userRole === 'Manager') {
            $query->where('user_id', $user->id);
        }

        if ($month !== 'all') {
            $query->whereMonth('close_date', $month);
        }
        if ($managerFilter !== 'all') {
            $query->where('user_id', $managerFilter);
        }
        
        if ($departmentFilter !== 'all') {
            if ($departmentFilter === 'SBU') {
                $query->where(function($q) use ($sbuDepts) {
                    foreach ($sbuDepts as $dept) {
                        $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                    }
                });
            } elseif ($departmentFilter === 'Sales') {
                $query->where(function($q) use ($salesDepts) {
                    foreach ($salesDepts as $dept) {
                        $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                    }
                });
            } else {
                $query->whereJsonContains('department_split', [['department' => $departmentFilter]]);
            }
        }
        if ($stageFilter !== 'all') {
            $query->where('stage', $stageFilter);
        }
        if ($brandFilter !== 'all') {
            $query->whereHas('estimates', function($q) use ($brandFilter) {
                $q->where('brand_name', $brandFilter);
            });
        }

        $deals = $query->get();

        // 3. Calculate KPIs and Chart Data
        $totalContribution = 0;
        foreach ($deals as $deal) {
            $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
            
            if ($userRole === 'HOD' && $userDept) {
                // For HOD, only sum their department's share
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (($split['department'] ?? '') === $userDept) {
                            $totalContribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            } elseif ($departmentFilter === 'SBU' || $departmentFilter === 'Sales') {
                // For category filters, sum only depts in that category
                $targetDepts = ($departmentFilter === 'SBU') ? $sbuDepts : $salesDepts;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (in_array($split['department'] ?? '', $targetDepts)) {
                            $totalContribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            } else {
                $totalContribution += $deal->contribution;
            }
        }

        // Determine active department/category filter depts
        $targetDepts = null;
        if ($userRole === 'HOD' && $userDept) {
            $targetDepts = [$userDept];
        } elseif ($departmentFilter === 'SBU') {
            $targetDepts = $sbuDepts;
        } elseif ($departmentFilter === 'Sales') {
            $targetDepts = $salesDepts;
        } elseif ($departmentFilter !== 'all') {
            $targetDepts = [$departmentFilter];
        }

        // 3. Calculate KPIs and Chart Data
        $totalContribution = 0;
        foreach ($deals as $deal) {
            if ($targetDepts) {
                $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (in_array($split['department'] ?? '', $targetDepts)) {
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
        })->map(function($group) use ($targetDepts) {
            $sum = 0;
            foreach ($group as $deal) {
                if ($targetDepts) {
                    $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                    if (is_array($splits)) {
                        foreach ($splits as $split) {
                            if (in_array($split['department'] ?? '', $targetDepts)) {
                                $sum += (float)($split['contribution_amount'] ?? 0);
                            }
                        }
                    }
                } else {
                    $sum += $deal->contribution;
                }
            }
            return $sum;
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
                            // If targetDepts is set, only show depts in that list
                            if ($targetDepts && !in_array($deptName, $targetDepts)) {
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
            $brand = 'Unknown';
            if ($deal->estimates->isNotEmpty()) {
                $firstWithBrand = $deal->estimates->first(fn($e) => !empty($e->brand_name));
                if ($firstWithBrand) {
                    $brand = $firstWithBrand->brand_name;
                }
            }
            if ($brand === 'Unknown' || empty($brand)) { 
                if ($deal->customer && !empty($deal->customer->brand)) {
                    $brand = $deal->customer->brand;
                }
            }
            
            $contribution = 0;
            if ($targetDepts) {
                $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (in_array($split['department'] ?? '', $targetDepts)) {
                            $contribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            } else {
                $contribution = $deal->contribution;
            }
                
            if ($brand !== 'Unknown') {
                $brandContribution[$brand] = ($brandContribution[$brand] ?? 0) + $contribution;
            }
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
                    $itemDept = $item->department ?? '';

                    // Filter by targetDepts if active
                    if ($targetDepts && !in_array($itemDept, $targetDepts)) {
                        continue;
                    }

                    $revenueCategoryContribution[$category] = ($revenueCategoryContribution[$category] ?? 0) + $amount;
                }
            }
        }
        arsort($revenueCategoryContribution);

        // Key Campaigns Table
        $keyCampaigns = $deals->map(function($d) use ($targetDepts) {
            $approvedEstimate = $d->estimates->first();
            $title = $d->title;
            if ($approvedEstimate && $approvedEstimate->description) {
                $title = $approvedEstimate->description;
            }

            $contribution = 0;
            if ($targetDepts) {
                $splits = is_string($d->department_split) ? json_decode($d->department_split, true) : $d->department_split;
                if (is_array($splits)) {
                    foreach ($splits as $split) {
                        if (in_array($split['department'] ?? '', $targetDepts)) {
                            $contribution += (float)($split['contribution_amount'] ?? 0);
                        }
                    }
                }
            } else {
                $contribution = $d->contribution;
            }

            return [
                'description' => $title,
                'contribution' => $contribution
            ];
        })->filter(fn($item) => $item['contribution'] > 0)->sortByDesc('contribution')->values();
        
        // Target Type Logic
        $sbuDepts = ['Creative', 'Digital', 'Tech'];
        $salesDepts = ['AM', 'BD'];
        
        $sbuActual = 0;
        $salesActual = 0;
        $sbuDeptActuals = array_fill_keys($sbuDepts, 0);
        $salesDeptActuals = array_fill_keys($salesDepts, 0);

        foreach ($deals as $deal) {
            $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
            if (is_array($splits)) {
                foreach ($splits as $split) {
                    $deptName = $split['department'] ?? '';
                    $amount = (float)($split['contribution_amount'] ?? 0);
                    
                    if (in_array($deptName, $sbuDepts)) {
                        $sbuActual += $amount;
                        $sbuDeptActuals[$deptName] += $amount;
                    } elseif (in_array($deptName, $salesDepts)) {
                        $salesActual += $amount;
                        $salesDeptActuals[$deptName] += $amount;
                    }
                }
            }
        }

        $sbuTarget = (float)\App\Models\Setting::get('target_sbu', 0);
        $salesTarget = (float)\App\Models\Setting::get('target_sales', 0);

        return view('dashboard', compact(
            'month', 'brandFilter', 'managerFilter', 'departmentFilter', 'stageFilter',
            'months', 'brands', 'managers', 'departments', 'stages',
            'totalContribution', 'managerContribution', 'departmentContribution',
            'brandContribution', 'revenueCategoryContribution', 'keyCampaigns',
            'sbuActual', 'salesActual', 'sbuTarget', 'salesTarget',
            'sbuDeptActuals', 'salesDeptActuals'
        ));
    }
}
