<?php

namespace App\Traits;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected function logAction($description, $subject = null, $properties = [], $userId = null)
    {
        $resolvedUserId = is_object($userId) && isset($userId->id) ? $userId->id : ($userId ?? Auth::id());

        // Safe bounds for activity description
        $safeDescription = is_string($description) ? \Illuminate\Support\Str::limit($description, 2000, '...') : $description;

        return Activity::create([
            'user_id' => $resolvedUserId,
            'description' => $safeDescription,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'properties' => $properties ?: null,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Build attribute changes between old and new model states.
     */
    protected function buildAttributeDiff(array $old, array $new, array $ignored = ['updated_at', 'created_at', 'id']): array
    {
        $changes = [];
        $summaryParts = [];

        foreach ($new as $key => $newVal) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $oldVal = $old[$key] ?? null;

            if (!$this->areAttributeValuesEqual($oldVal, $newVal)) {
                $changes[$key] = [
                    'from' => $oldVal,
                    'to' => $newVal,
                ];

                $label = ucwords(str_replace('_', ' ', $key));
                $fromText = $this->formatValueForLog($oldVal, $key);
                $toText = $this->formatValueForLog($newVal, $key);

                if ($key === 'customer_id') {
                    $label = 'Customer';
                } elseif ($key === 'user_id') {
                    $label = 'Assigned User';
                }

                $summaryParts[] = "{$label}: {$fromText} → {$toText}";
            }
        }

        return [
            'changes' => $changes,
            'summary' => implode('; ', $summaryParts),
        ];
    }

    /**
     * Compare two attribute values with smart normalization.
     */
    protected function areAttributeValuesEqual($oldVal, $newVal): bool
    {
        $oldEmpty = is_null($oldVal) || (is_string($oldVal) && trim($oldVal) === '');
        $newEmpty = is_null($newVal) || (is_string($newVal) && trim($newVal) === '');

        if ($oldEmpty && $newEmpty) {
            return true;
        }

        // Both effectively 0 or empty numeric
        if (($oldEmpty || (is_numeric($oldVal) && (float)$oldVal == 0.0)) &&
            ($newEmpty || (is_numeric($newVal) && (float)$newVal == 0.0))) {
            return true;
        }

        // Both numeric
        if (is_numeric($oldVal) && is_numeric($newVal)) {
            return round((float)$oldVal, 2) == round((float)$newVal, 2);
        }

        // Both boolean or 1/0
        if ((is_bool($oldVal) || $oldVal === 1 || $oldVal === 0 || $oldVal === '1' || $oldVal === '0') &&
            (is_bool($newVal) || $newVal === 1 || $newVal === 0 || $newVal === '1' || $newVal === '0')) {
            return (bool)$oldVal === (bool)$newVal;
        }

        // Strings
        if (is_string($oldVal) || is_string($newVal)) {
            return trim((string)$oldVal) === trim((string)$newVal);
        }

        return $oldVal == $newVal;
    }

    /**
     * Format value for activity log summary.
     */
    protected function formatValueForLog($val, ?string $key = null, bool $forSummary = true): string
    {
        if (is_null($val) || $val === '') {
            return '(empty)';
        }

        if ($key === 'customer_id' && is_numeric($val)) {
            $customer = Customer::find($val);
            if ($customer) {
                return $customer->name;
            }
        }

        if ($key === 'user_id' && is_numeric($val)) {
            $user = User::find($val);
            if ($user) {
                return $user->name;
            }
        }

        if (is_bool($val)) {
            return $val ? 'Yes' : 'No';
        }

        if (is_numeric($val)) {
            return number_format((float)$val, 2);
        }

        if (is_array($val)) {
            return json_encode($val);
        }

        $str = (string)$val;
        if ($forSummary && mb_strlen($str) > 45) {
            $clean = preg_replace('/\s+/', ' ', trim(strip_tags($str)));
            return \Illuminate\Support\Str::limit($clean, 45, '...');
        }

        return $str;
    }

    /**
     * Clean and strip HTML for display in text logs.
     */
    protected function cleanDescriptionForDisplay(?string $desc): string
    {
        if ($desc === null || $desc === '') {
            return '';
        }
        $decoded = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', strip_tags($decoded)));
    }

    /**
     * Normalize description for change comparison (avoiding false positives from Quill p tags or CRLF).
     */
    protected function areDescriptionsEqual(?string $desc1, ?string $desc2): bool
    {
        $clean1 = $this->normalizeDescriptionHtml((string)$desc1);
        $clean2 = $this->normalizeDescriptionHtml((string)$desc2);

        if ($clean1 === $clean2) {
            return true;
        }

        $plain1 = trim(preg_replace('/\s+/', ' ', strip_tags($clean1)));
        $plain2 = trim(preg_replace('/\s+/', ' ', strip_tags($clean2)));

        if ($plain1 === $plain2) {
            $inner1 = trim(preg_replace('/\s+/', ' ', preg_replace('/^\s*<p>(.*)<\/p>\s*$/is', '$1', $clean1)));
            $inner2 = trim(preg_replace('/\s+/', ' ', preg_replace('/^\s*<p>(.*)<\/p>\s*$/is', '$1', $clean2)));

            if ($inner1 === $inner2) {
                return true;
            }

            if (strip_tags($inner1) === $inner1 && strip_tags($inner2) === $inner2) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeDescriptionHtml(string $desc): string
    {
        $text = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Remove trailing empty p tags
        $text = preg_replace('/<p>\s*(<br\s*\/?>)?\s*<\/p>\s*$/i', '', $text);
        return trim($text);
    }

    /**
     * Build item level comparison between old collection/array and new items array.
     */
    protected function buildItemsDiff($oldItems, array $newItems): array
    {
        $oldList = is_array($oldItems) ? $oldItems : ($oldItems ? $oldItems->toArray() : []);
        $oldList = array_values($oldList);
        $newItems = array_values($newItems);

        $oldCount = count($oldList);
        $newCount = count($newItems);

        $addedCount = 0;
        $removedCount = 0;
        $modifiedCount = 0;

        $modifiedDetails = [];
        $addedDetails = [];
        $removedDetails = [];

        $minCount = min($oldCount, $newCount);

        for ($i = 0; $i < $minCount; $i++) {
            $o = $oldList[$i];
            $n = $newItems[$i];

            $itemChanges = [];

            // Description
            $oDesc = (string)($o['description'] ?? '');
            $nDesc = (string)($n['description'] ?? '');
            if (!$this->areDescriptionsEqual($oDesc, $nDesc)) {
                $itemChanges['description'] = [
                    'from' => $this->cleanDescriptionForDisplay($oDesc),
                    'to' => $this->cleanDescriptionForDisplay($nDesc),
                ];
            }

            // Quantity
            $oQty = (float)($o['quantity'] ?? 0);
            $nQty = (float)($n['quantity'] ?? 0);
            if ($oQty != $nQty) {
                $itemChanges['quantity'] = [
                    'from' => $oQty,
                    'to' => $nQty,
                ];
            }

            // Unit Price
            $oPrice = round((float)($o['unit_price'] ?? 0), 2);
            $nPrice = round((float)($n['unit_price'] ?? 0), 2);
            if ($oPrice != $nPrice) {
                $itemChanges['unit_price'] = [
                    'from' => number_format($oPrice, 2),
                    'to' => number_format($nPrice, 2),
                ];
            }

            // Department
            $oDept = trim((string)($o['department'] ?? ''));
            $nDept = trim((string)($n['department'] ?? ''));
            if (strtolower($oDept) !== strtolower($nDept)) {
                $itemChanges['department'] = [
                    'from' => $oDept !== '' ? ucfirst($oDept) : '(empty)',
                    'to' => $nDept !== '' ? ucfirst($nDept) : '(empty)',
                ];
            }

            // Revenue category
            $oRev = trim((string)($o['revenue_category'] ?? ''));
            $nRev = trim((string)($n['revenue_category'] ?? ''));
            if (strtolower($oRev) !== strtolower($nRev)) {
                $itemChanges['revenue_category'] = [
                    'from' => $oRev !== '' ? $oRev : '(empty)',
                    'to' => $nRev !== '' ? $nRev : '(empty)',
                ];
            }

            // Item Heading
            $oHead = trim((string)($o['item_heading'] ?? ''));
            $nHead = trim((string)($n['item_heading'] ?? ''));
            if ($oHead !== '' || $nHead !== '') {
                if ($oHead !== $nHead) {
                    $itemChanges['item_heading'] = [
                        'from' => $oHead !== '' ? $oHead : '(empty)',
                        'to' => $nHead !== '' ? $nHead : '(empty)',
                    ];
                }
            }

            // Type
            $oType = trim((string)($o['type'] ?? 'item'));
            $nType = trim((string)($n['type'] ?? 'item'));
            if ($oType !== '' && $nType !== '' && strtolower($oType) !== strtolower($nType)) {
                $itemChanges['type'] = [
                    'from' => $oType,
                    'to' => $nType,
                ];
            }

            if (!empty($itemChanges)) {
                $modifiedCount++;
                $labelDesc = $this->cleanDescriptionForDisplay($nDesc ?: $oDesc);
                $modifiedDetails[] = [
                    'item_number' => $i + 1,
                    'description' => $labelDesc ?: "Line Item " . ($i + 1),
                    'changes' => $itemChanges,
                ];
            }
        }

        // Added items
        if ($newCount > $oldCount) {
            $addedCount = $newCount - $oldCount;
            for ($i = $oldCount; $i < $newCount; $i++) {
                $n = $newItems[$i];
                $nQty = (float)($n['quantity'] ?? 1);
                $nPrice = round((float)($n['unit_price'] ?? 0), 2);
                $addedDetails[] = [
                    'item_number' => $i + 1,
                    'description' => $this->cleanDescriptionForDisplay((string)($n['description'] ?? '')) ?: "Line Item " . ($i + 1),
                    'quantity' => $nQty,
                    'unit_price' => number_format($nPrice, 2),
                    'amount' => number_format($nQty * $nPrice, 2),
                    'department' => $n['department'] ?? null,
                    'revenue_category' => $n['revenue_category'] ?? null,
                ];
            }
        }

        // Removed items
        if ($oldCount > $newCount) {
            $removedCount = $oldCount - $newCount;
            for ($i = $newCount; $i < $oldCount; $i++) {
                $o = $oldList[$i];
                $oQty = (float)($o['quantity'] ?? 1);
                $oPrice = round((float)($o['unit_price'] ?? 0), 2);
                $removedDetails[] = [
                    'item_number' => $i + 1,
                    'description' => $this->cleanDescriptionForDisplay((string)($o['description'] ?? '')) ?: "Line Item " . ($i + 1),
                    'quantity' => $oQty,
                    'unit_price' => number_format($oPrice, 2),
                    'amount' => number_format($oQty * $oPrice, 2),
                    'department' => $o['department'] ?? null,
                    'revenue_category' => $o['revenue_category'] ?? null,
                ];
            }
        }

        $summaryParts = [];
        if ($addedCount > 0) $summaryParts[] = "+{$addedCount} item(s) added";
        if ($removedCount > 0) $summaryParts[] = "-{$removedCount} item(s) removed";
        if ($modifiedCount > 0) $summaryParts[] = "{$modifiedCount} item(s) modified";

        return [
            'old_count' => $oldCount,
            'new_count' => $newCount,
            'added' => $addedCount,
            'removed' => $removedCount,
            'modified' => $modifiedCount,
            'summary' => implode(', ', $summaryParts) ?: null,
            'details' => [
                'modified' => $modifiedDetails,
                'added' => $addedDetails,
                'removed' => $removedDetails,
            ],
        ];
    }

    /**
     * Build third party cost differences.
     */
    protected function buildThirdPartyCostsDiff($oldCosts, array $newCosts): array
    {
        $oldList = is_array($oldCosts) ? $oldCosts : ($oldCosts ? $oldCosts->toArray() : []);
        $oldList = array_values($oldList);
        $newCosts = array_values($newCosts);

        $oldCount = count($oldList);
        $newCount = count($newCosts);

        $addedCount = 0;
        $removedCount = 0;
        $modifiedCount = 0;

        $modifiedDetails = [];
        $addedDetails = [];
        $removedDetails = [];

        $minCount = min($oldCount, $newCount);
        for ($i = 0; $i < $minCount; $i++) {
            $o = $oldList[$i];
            $n = $newCosts[$i];

            $costChanges = [];

            $oSupplier = trim((string)($o['supplier'] ?? ''));
            $nSupplier = trim((string)($n['supplier'] ?? ''));
            if ($oSupplier !== $nSupplier) {
                $costChanges['supplier'] = ['from' => $oSupplier, 'to' => $nSupplier];
            }

            $oCost = round((float)($o['cost'] ?? 0), 2);
            $nCost = round((float)($n['cost'] ?? 0), 2);
            if ($oCost != $nCost) {
                $costChanges['cost'] = ['from' => number_format($oCost, 2), 'to' => number_format($nCost, 2)];
            }

            $oDept = trim((string)($o['department'] ?? ''));
            $nDept = trim((string)($n['department'] ?? ''));
            if (strtolower($oDept) !== strtolower($nDept)) {
                $costChanges['department'] = ['from' => $oDept ?: '(empty)', 'to' => $nDept ?: '(empty)'];
            }

            if (!empty($costChanges)) {
                $modifiedCount++;
                $modifiedDetails[] = [
                    'cost_number' => $i + 1,
                    'supplier' => $nSupplier ?: $oSupplier,
                    'changes' => $costChanges,
                ];
            }
        }

        if ($newCount > $oldCount) {
            $addedCount = $newCount - $oldCount;
            for ($i = $oldCount; $i < $newCount; $i++) {
                $n = $newCosts[$i];
                $addedDetails[] = [
                    'cost_number' => $i + 1,
                    'supplier' => $n['supplier'] ?? 'Cost ' . ($i + 1),
                    'cost' => number_format((float)($n['cost'] ?? 0), 2),
                    'department' => $n['department'] ?? null,
                ];
            }
        }

        if ($oldCount > $newCount) {
            $removedCount = $oldCount - $newCount;
            for ($i = $newCount; $i < $oldCount; $i++) {
                $o = $oldList[$i];
                $removedDetails[] = [
                    'cost_number' => $i + 1,
                    'supplier' => $o['supplier'] ?? 'Cost ' . ($i + 1),
                    'cost' => number_format((float)($o['cost'] ?? 0), 2),
                    'department' => $o['department'] ?? null,
                ];
            }
        }

        $oldTotal = array_sum(array_column($oldList, 'cost'));
        $newTotal = array_sum(array_column($newCosts, 'cost'));

        $summaryParts = [];
        if ($addedCount > 0) $summaryParts[] = "+{$addedCount} cost(s) added";
        if ($removedCount > 0) $summaryParts[] = "-{$removedCount} cost(s) removed";
        if ($modifiedCount > 0) $summaryParts[] = "{$modifiedCount} cost(s) modified";

        return [
            'old_count' => $oldCount,
            'new_count' => $newCount,
            'added' => $addedCount,
            'removed' => $removedCount,
            'modified' => $modifiedCount,
            'old_total' => round((float)$oldTotal, 2),
            'new_total' => round((float)$newTotal, 2),
            'summary' => implode(', ', $summaryParts) ?: ($oldCount !== $newCount || round((float)$oldTotal, 2) !== round((float)$newTotal, 2) ? "Costs: {$oldCount} → {$newCount}" : null),
            'details' => [
                'modified' => $modifiedDetails,
                'added' => $addedDetails,
                'removed' => $removedDetails,
            ],
        ];
    }
}
