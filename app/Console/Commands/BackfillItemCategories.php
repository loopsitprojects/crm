<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvoiceItem;
use App\Models\EstimateItem;
use App\Models\Estimate;

class BackfillItemCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:backfill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing department and revenue category fields for Invoice and Estimate items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill for Invoice and Estimate items...');

        // 1. Backfill Invoice Items
        $items = InvoiceItem::whereNull('department')->orWhereNull('revenue_category')->get();
        $updatedInv = 0;

        foreach ($items as $item) {
            $invoice = $item->invoice;
            if (!$invoice || !$invoice->quotation_id) continue;

            $estimate = Estimate::find($invoice->quotation_id);
            if (!$estimate) continue;

            $estItems = $estimate->items->values();
            $estItem = $estItems->where('position', $item->position)->first()
                    ?? $estItems->filter(fn($i) => $i->description === $item->description)->first();

            if (!$estItem) {
                $allInvoiceItems = $invoice->items->values();
                $idx = $allInvoiceItems->search(fn($i) => $i->id === $item->id);
                if ($idx !== false && isset($estItems[$idx])) {
                    $estItem = $estItems[$idx];
                }
            }

            $changed = false;
            if (empty($item->department)) {
                $dept = $estItem->department ?? null;
                if (!$dept && $estimate->deal && $estimate->deal->owner) {
                    $dept = $estimate->deal->owner->department;
                }
                if ($dept) {
                    $item->department = $dept;
                    $changed = true;
                }
            }

            if (empty($item->revenue_category)) {
                $revCat = $estItem->revenue_category ?? null;
                if (!$revCat) {
                    $revCat = 'Retainer';
                }
                if ($revCat) {
                    $item->revenue_category = $revCat;
                    $changed = true;
                }
            }

            if ($changed) {
                $item->save();
                $updatedInv++;
            }
        }

        // Standalone Invoice Items fallback
        $remainingInv = InvoiceItem::whereNull('department')->orWhereNull('revenue_category')->get();
        foreach ($remainingInv as $item) {
            if (empty($item->department)) $item->department = 'creative';
            if (empty($item->revenue_category)) $item->revenue_category = 'Retainer';
            $item->save();
        }

        // 2. Backfill Estimate Items
        $estItems = EstimateItem::whereNull('department')->orWhereNull('revenue_category')->get();
        foreach ($estItems as $item) {
            $est = $item->estimate;
            $dept = ($est && $est->deal && $est->deal->owner) ? $est->deal->owner->department : 'creative';
            if (empty($item->department)) $item->department = $dept ?: 'creative';
            if (empty($item->revenue_category)) $item->revenue_category = 'Retainer';
            $item->save();
        }

        $this->info("Backfill completed successfully!");
        $this->info("Invoice items missing department: " . InvoiceItem::whereNull('department')->count());
        $this->info("Invoice items missing revenue_category: " . InvoiceItem::whereNull('revenue_category')->count());
        $this->info("Estimate items missing department: " . EstimateItem::whereNull('department')->count());
        $this->info("Estimate items missing revenue_category: " . EstimateItem::whereNull('revenue_category')->count());
    }
}
