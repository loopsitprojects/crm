<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Target;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class FinancialPerformanceService
{
    /**
     * Get the consolidated dashboard data structure.
     *
     * @return array
     */
    public function getDashboardData(): array
    {
        $basePath = storage_path('app/financial_performance_baseline.json');
        $data = [];

        if (File::exists($basePath)) {
            $data = json_decode(File::get($basePath), true) ?: [];
        }

        // 1. Sync Department Targets dynamically from DB
        $data = $this->syncTargets($data);

        // 2. Sync Live Pipeline Deals from DB
        $data = $this->syncPipeline($data);

        // 3. Sync Closed Won Transactions from DB
        $data = $this->syncTransactions($data);

        // 4. Sync Individual Salesperson Performance
        $data = $this->syncIndividuals($data);

        return $data;
    }

    /**
     * Dynamically sync targets from the `targets` table.
     */
    protected function syncTargets(array $data): array
    {
        $deptTargets = Target::where('type', 'department')->get()->keyBy('department');

        // Department mapping: DB department => Dashboard department
        // Tech => IT, Digital => DM, Creative => Creative
        $sbuMap = [
            'Digital' => 'DM',
            'Creative' => 'Creative',
            'Tech' => 'IT',
        ];

        foreach ($sbuMap as $dbDept => $dashDept) {
            if (isset($deptTargets[$dbDept]) && isset($data['departments'][$dashDept])) {
                $monthlyTarget = (float) $deptTargets[$dbDept]->target_amount;
                if ($monthlyTarget > 0) {
                    $annual = $monthlyTarget * 12;
                    $quarterly = $annual / 4;
                    $data['departments'][$dashDept]['target_annual'] = $annual;
                    $data['departments'][$dashDept]['target_quarterly'] = $quarterly;

                    // Update monthly targets array for this department (first 6 months or all 12)
                    if (isset($data['monthly_targets'][$dashDept])) {
                        for ($i = 0; $i < 6; $i++) {
                            $data['monthly_targets'][$dashDept][$i] = $monthlyTarget;
                        }
                    }
                }
            }
        }

        // Sales Units mapping: AM => Brands, BD => BD, Tech => IT
        $salesUnitMap = [
            'AM' => 'Brands',
            'BD' => 'BD',
            'Tech' => 'IT',
        ];

        foreach ($salesUnitMap as $dbDept => $unitName) {
            if (isset($deptTargets[$dbDept]) && isset($data['sales_units'][$unitName])) {
                $monthlyTarget = (float) $deptTargets[$dbDept]->target_amount;
                if ($monthlyTarget > 0) {
                    $annual = $monthlyTarget * 12;
                    $quarterly = $annual / 4;
                    $data['sales_units'][$unitName]['target_annual'] = $annual;
                    $data['sales_units'][$unitName]['target_quarterly'] = $quarterly;

                    $targetKey = $unitName === 'IT' ? 'IT_sales' : $unitName;
                    if (isset($data['monthly_targets'][$targetKey])) {
                        for ($i = 0; $i < 6; $i++) {
                            $data['monthly_targets'][$targetKey][$i] = $monthlyTarget;
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Dynamically sync open pipeline deals from the `deals` table.
     */
    protected function syncPipeline(array $data): array
    {
        $openDeals = Deal::with(['customer', 'owner'])
            ->whereNotIn('stage', ['Closed Won', 'Rejected', 'Closed Lost'])
            ->whereNotNull('stage')
            ->orderBy('close_date', 'asc')
            ->get();

        if ($openDeals->isEmpty()) {
            return $data;
        }

        $items = [];
        $byStage = [];
        $byUnit = [
            'Brands' => 0.0,
            'BD' => 0.0,
            'IT' => 0.0,
        ];
        $byMonth = [];

        foreach ($openDeals as $deal) {
            $amount = (float) ($deal->contribution > 0 ? $deal->contribution : $deal->revenue);
            if ($amount <= 0) {
                continue;
            }

            $stage = $deal->stage;
            $month = $deal->close_date ? Carbon::parse($deal->close_date)->format('F') : 'Upcoming';

            // Resolve unit & department
            $ownerDept = $deal->owner?->department ?? '';
            $unit = 'Brands';
            if ($ownerDept === 'BD') {
                $unit = 'BD';
            } elseif ($ownerDept === 'Tech' || str_contains(strtolower($deal->title), 'tech') || str_contains(strtolower($deal->title), 'web')) {
                $unit = 'IT';
            }

            $dept = 'DM';
            if ($unit === 'IT' || $ownerDept === 'Tech') {
                $dept = 'IT';
            } elseif (str_contains(strtolower($deal->title), 'creative') || str_contains(strtolower($deal->title), 'video') || str_contains(strtolower($deal->title), 'shoot') || str_contains(strtolower($deal->title), 'campaign')) {
                $dept = 'Creative';
            }

            $category = $deal->type ?: 'Project';
            if (str_contains(strtolower($deal->title), 'media') || str_contains(strtolower($deal->title), 'ads')) {
                $category = 'Ads';
            } elseif (str_contains(strtolower($deal->title), 'retainer')) {
                $category = 'Retainer';
            }

            $brand = $deal->customer?->brand ?: $deal->customer?->name ?: $deal->customer_name ?: 'Client Deal';
            $manager = $deal->owner?->name ?: $deal->senior_manager ?: 'Team';

            $item = [
                'deal_id' => $deal->id,
                'month' => $month,
                'brand' => $brand,
                'description' => $deal->title,
                'amount' => $amount,
                'category' => $category,
                'department' => $dept,
                'manager' => $manager,
                'stage' => $stage,
                'owner' => $unit,
                'is_live' => true,
            ];

            $items[] = $item;

            // Aggregate by stage
            $byStage[$stage] = ($byStage[$stage] ?? 0.0) + $amount;

            // Aggregate by unit
            $byUnit[$unit] = ($byUnit[$unit] ?? 0.0) + $amount;

            // Aggregate by month
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = [];
            }
            $byMonth[$month][$stage] = ($byMonth[$month][$stage] ?? 0.0) + $amount;
        }

        // Overlay with live pipeline items
        if (!empty($items)) {
            $data['pipeline']['items'] = $items;
            $data['pipeline']['by_stage'] = $byStage;
            $data['pipeline']['by_unit'] = $byUnit;
            $data['pipeline']['by_month'] = $byMonth;
        }

        return $data;
    }

    /**
     * Dynamically sync Closed Won deals with the transactions array.
     */
    protected function syncTransactions(array $data): array
    {
        $closedDeals = Deal::with(['customer', 'owner'])
            ->where('stage', 'Closed Won')
            ->orderBy('close_date', 'desc')
            ->get();

        if ($closedDeals->isEmpty()) {
            return $data;
        }

        // Maintain existing baseline transactions and merge/update with live Closed Won deals
        $existingTxns = $data['transactions'] ?? [];
        $existingDescriptions = array_flip(array_column($existingTxns, 'description'));

        foreach ($closedDeals as $deal) {
            $title = $deal->title;
            // If already present in baseline, keep or update
            if (isset($existingDescriptions[$title])) {
                continue;
            }

            $amount = (float) ($deal->contribution > 0 ? $deal->contribution : $deal->revenue);
            if ($amount <= 0) {
                continue;
            }

            $month = $deal->close_date ? Carbon::parse($deal->close_date)->format('F') : 'April';
            $ownerDept = $deal->owner?->department ?? '';
            $unit = 'Brands';
            if ($ownerDept === 'BD') {
                $unit = 'BD';
            } elseif ($ownerDept === 'Tech') {
                $unit = 'IT';
            }

            $dept = 'DM';
            if ($unit === 'IT' || $ownerDept === 'Tech') {
                $dept = 'IT';
            } elseif (str_contains(strtolower($deal->title), 'creative')) {
                $dept = 'Creative';
            }

            $brand = $deal->customer?->brand ?: $deal->customer?->name ?: $deal->customer_name ?: 'Client';
            $manager = $deal->owner?->name ?: $deal->senior_manager ?: 'Manager';

            $existingTxns[] = [
                'deal_id' => $deal->id,
                'month' => $month,
                'brand' => $brand,
                'description' => $deal->title,
                'amount' => $amount,
                'category' => $deal->type ?: 'Project',
                'department' => $dept,
                'manager' => $manager,
                'stage' => 'Closed Won',
                'owner' => $unit,
                'is_live' => true,
            ];
        }

        $data['transactions'] = $existingTxns;

        return $data;
    }

    /**
     * Dynamically update individual salesperson targets & contributions.
     */
    protected function syncIndividuals(array $data): array
    {
        if (empty($data['individuals'])) {
            return $data;
        }

        $userTargets = Target::where('type', 'user')->pluck('target_amount', 'user_id');
        $usersByName = User::all()->keyBy('name');

        foreach ($data['individuals'] as &$person) {
            $name = $person['name'] ?? '';

            // Match user by name substring
            $matchedUser = null;
            foreach ($usersByName as $userName => $u) {
                if (stripos($userName, $name) !== false) {
                    $matchedUser = $u;
                    break;
                }
            }

            if ($matchedUser && isset($userTargets[$matchedUser->id])) {
                $monthlyTarget = (float) $userTargets[$matchedUser->id];
                if ($monthlyTarget > 0 && isset($person['monthly_target'])) {
                    foreach ($person['monthly_target'] as $m => $val) {
                        $person['monthly_target'][$m] = $monthlyTarget;
                    }
                }
            }
        }

        return $data;
    }
}
