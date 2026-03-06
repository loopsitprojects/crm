<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();
        $department = $request->input('department');
        $customerName = $request->input('customer_name');
        $stageFilter = $request->input('stage');

        $stages = [
            'Planned to Meet',
            'Introductory meeting',
            'Brief Stage',
            'Working on pitch',
            'Pitched',
            'Objection handling',
            'Finalizing terms',
            'Approved',
            'Rejected'
        ];

        $user = auth()->user();
        $isRestricted = !in_array($user->role, ['Super Admin', 'Management']);

        // Base Query with RBAC & Filters
        $applyFilters = function ($query) use ($startDate, $endDate, $department, $customerName, $isRestricted, $user) {
            $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);

            if ($department) {
                $query->where('type', $department);
            }

            if ($customerName) {
                $query->whereHas('customer', function ($q) use ($customerName) {
                    $q->where('name', 'LIKE', "%{$customerName}%");
                });
            }

            if ($isRestricted) {
                if ($user->role === 'HOD' && $user->department) {
                    $query->where('department_split', 'like', '%' . $user->department . '%');
                } else {
                    $query->where(function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->orWhereHas('teamMembers', function ($tm) use ($user) {
                                $tm->where('users.id', $user->id);
                            });
                    });
                }
            }
            return $query;
        };

        $dealQuery = $applyFilters(Deal::query());
        if ($stageFilter) {
            $dealQuery->where('stage', $stageFilter);
        }

        $invoiceQuery = Invoice::with('customer', 'estimate.deal')
            ->where('is_proforma', false) // Keep existing condition
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);

        if ($department) {
            $invoiceQuery->whereHas('estimate.deal', function ($q) use ($department) {
                $q->where('type', $department);
            });
        }

        if ($customerName) {
            $invoiceQuery->whereHas('customer', function ($q) use ($customerName) {
                $q->where('name', 'LIKE', "%{$customerName}%");
            });
        }

        if ($isRestricted) {
            $invoiceQuery->where(function ($q) use ($user) {
                $q->whereHas('estimate.deal', function ($dq) use ($user) {
                    if ($user->role === 'HOD' && $user->department) {
                        $dq->where('department_split', 'like', '%' . $user->department . '%');
                    } else {
                        $dq->where('user_id', $user->id)
                            ->orWhereHas('teamMembers', function ($tm) use ($user) {
                                $tm->where('users.id', $user->id);
                            });
                    }
                });
            });
        }

        // Expanded Metrics
        $totalDealRevenue = (clone $dealQuery)->sum('revenue');
        $openDealsCount = (clone $dealQuery)->whereIn('stage', ['Planned to Meet', 'Introductory meeting', 'Brief Stage', 'Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms'])->count();
        $weightedRevenue = (clone $dealQuery)->whereIn('stage', ['Planned to Meet', 'Introductory meeting', 'Brief Stage', 'Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms'])->sum('revenue'); // Using total of open deals for now
        $approvedRevenue = (clone $dealQuery)->where('stage', 'Approved')->sum('revenue');
        $newDeals30 = Deal::where('created_at', '>=', now()->subDays(30));
        if ($isRestricted) {
            $newDeals30->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('teamMembers', function ($tm) use ($user) {
                        $tm->where('users.id', $user->id);
                    });
            });
        }
        $newDeals30Revenue = $newDeals30->sum('revenue');

        $avgDealAge = (clone $dealQuery)->avg(DB::raw('DATEDIFF(NOW(), created_at)')) ?: 0;

        $invoicedAmount = (clone $invoiceQuery)->sum('total_amount');
        $paymentCollected = (clone $invoiceQuery)->where('status', 'paid')->sum('total_amount');
        $pendingAmount = (clone $invoiceQuery)->where('status', '!=', 'paid')->sum('total_amount');

        // Legacy variable for view compatibility if needed
        $revenue = $paymentCollected;
        $dealsRevenue = $totalDealRevenue;

        // Data for Charts
        $dailyRevenue = (clone $invoiceQuery)
            ->where('status', 'paid')
            ->select(DB::raw('DATE(created_at) as report_date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('report_date')
            ->get();

        $dealsByStage = (clone $dealQuery)
            ->select('stage', DB::raw('count(*) as count'), DB::raw('SUM(revenue) as total'))
            ->groupBy('stage')
            ->get();

        $revenueByDeptQuery = DB::table('invoices')
            ->join('quotations', 'invoices.quotation_id', '=', 'quotations.id')
            ->join('deals', 'quotations.deal_id', '=', 'deals.id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->where('invoices.status', 'paid');

        if ($isRestricted) {
            $revenueByDeptQuery->where('deals.type', $user->department);
        } elseif ($department) {
            $revenueByDeptQuery->where('deals.type', $department);
        }

        $revenueByDept = $revenueByDeptQuery->select('deals.type', DB::raw('SUM(invoices.total_amount) as total'))
            ->groupBy('deals.type')
            ->get();

        // Handle Detailed Data
        $detailedData = \App\Models\InvoiceItem::with([
            'invoice.customer',
            'invoice.estimate.deal.owner',
            'invoice.estimate'
        ])
        ->whereHas('invoice', function ($q) use ($startDate, $endDate, $customerName, $department, $isRestricted, $user) {
            $q->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
              ->where('is_proforma', false);

            if ($customerName) {
                $q->whereHas('customer', function ($cq) use ($customerName) {
                    $cq->where('name', 'LIKE', "%{$customerName}%");
                });
            }

            if ($department || $isRestricted) {
                $q->whereHas('estimate.deal', function ($dq) use ($department, $isRestricted, $user) {
                    if ($department) {
                        $dq->where('type', $department);
                    }
                    if ($isRestricted) {
                        $dq->where(function ($sq) use ($user) {
                            if ($user->role === 'HOD' && $user->department) {
                                $sq->where('department_split', 'like', '%' . $user->department . '%');
                            } else {
                                $sq->where('user_id', $user->id)
                                    ->orWhereHas('teamMembers', function ($tm) use ($user) {
                                        $tm->where('users.id', $user->id);
                                    });
                            }
                        });
                    }
                });
            }
        });

        if ($stageFilter) {
            $detailedData->whereHas('invoice.estimate.deal', function ($dq) use ($stageFilter) {
                $dq->where('stage', $stageFilter);
            });
        }

        $detailedData = $detailedData->latest()->paginate(25);

        $incomeBreakdown = [];

        return view('reports.index', compact(
            'startDate',
            'endDate',
            'department',
            'isRestricted',
            'totalDealRevenue',
            'openDealsCount',
            'weightedRevenue',
            'approvedRevenue',
            'newDeals30Revenue',
            'avgDealAge',
            'invoicedAmount',
            'paymentCollected',
            'pendingAmount',
            'revenue',
            'dealsRevenue',
            'dailyRevenue',
            'dealsByStage',
            'revenueByDept',
            'revenueByDept',
            'detailedData',
            'customerName',
            'stageFilter',
            'stages',
            'incomeBreakdown'
        ));
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();
        $department = $request->input('department');
        $type = $request->input('type', 'deals');

        $user = auth()->user();
        $isRestricted = !in_array($user->role, ['Super Admin', 'Management']);

        if ($type === 'detailed') {
            $query = \App\Models\InvoiceItem::with([
                'invoice.customer',
                'invoice.estimate.deal.owner',
                'invoice.estimate' // Ensure estimate is loaded for advance_received_amount and reference_number
            ])
            ->whereHas('invoice', function ($q) use ($startDate, $endDate, $isRestricted, $user, $department) {
                $q->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->where('is_proforma', false);

                if ($isRestricted) {
                    $q->whereHas('estimate.deal', function ($dq) use ($user) {
                        $dq->where(function ($sq) use ($user) {
                            $sq->where('user_id', $user->id)
                                ->orWhereHas('teamMembers', function ($tm) use ($user) {
                                    $tm->where('users.id', $user->id);
                                });
                        });
                    });
                } elseif ($department) {
                    $q->whereHas('estimate.deal', function ($dq) use ($department) {
                        $dq->where('type', $department);
                    });
                }
            });

            $data = $query->get();
            $filename = "detailed_report_" . now()->format('YmdHis') . ".csv";
            $headers = [
                'Inv Date/ Est Date', 'Est/Inv No', 'Job No', 'Invoiced Month/ Closing month', 
                'Client Name', 'TIN', 'Brand', 'Description', 'Line Amount', 'SSCL', 'VAT', 
                'Total Amount', 'Con Confirmed', 'Revenue Category', 'Department', 'Data Inputter', 
                'Stages', 'Advance payment Status', 'Payment Status', 'Balance Due'
            ];

            $callback = function () use ($data, $headers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);
                foreach ($data as $item) {
                    $invoice = $item->invoice;
                    $estimate = $invoice->estimate ?? null;
                    $deal = $estimate->deal ?? null;
                    
                    $total = $invoice->total_amount ?? 0;
                    $balanceDue = ($invoice->status === 'paid') ? 0 : $total;
                    $advanceStatus = ($estimate && $estimate->advance_received_amount > 0) ? 'RECEIVED' : 'PENDING';

                    fputcsv($file, [
                        $invoice->date ?? ($estimate->date ?? 'N/A'),
                        $invoice->invoice_number ?? ($estimate->reference_number ?? 'N/A'),
                        $deal->job_number ?? 'N/A',
                        $invoice->date ? date('M Y', strtotime($invoice->date)) : 'N/A',
                        $invoice->customer->name ?? 'N/A',
                        $invoice->customer->customer_tax_number ?? 'N/A',
                        $estimate->brand_name ?? 'N/A',
                        $item->description,
                        $item->amount,
                        $item->sscl_amount,
                        $item->vat_amount,
                        $item->total_with_vat,
                        $deal->contribution ?? 'N/A',
                        $item->revenue_category ?? 'N/A',
                        $item->department ?? 'N/A',
                        $deal->owner->name ?? 'N/A',
                        $deal->stage ?? 'N/A',
                        $advanceStatus,
                        strtoupper($invoice->status ?? 'pending'),
                        $balanceDue
                    ]);
                }
                fclose($file);
            };
        } elseif ($type === 'invoices') {
            $query = Invoice::whereBetween('created_at', [$startDate, $endDate])->with('customer', 'estimate.deal');

            if ($isRestricted) {
                $query->whereHas('estimate.deal', function ($q) use ($user) {
                    $q->where(function ($sq) use ($user) {
                        $sq->where('user_id', $user->id)
                            ->orWhereHas('teamMembers', function ($ssq) use ($user) {
                                $ssq->where('users.id', $user->id);
                            });
                    });
                });
            } elseif ($department) {
                $query->whereHas('estimate.deal', function ($q) use ($department) {
                    $q->where('type', $department);
                });
            }

            $data = $query->get();
            $filename = "invoices_report_" . now()->format('YmdHis') . ".csv";
            $headers = ['Date', 'Invoice #', 'Customer', 'Deal', 'Amount', 'Status', 'Is Proforma'];

            $callback = function () use ($data, $headers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);
                foreach ($data as $invoice) {
                    fputcsv($file, [
                        $invoice->created_at->format('Y-m-d'),
                        $invoice->invoice_number,
                        $invoice->customer->name ?? 'N/A',
                        $invoice->estimate->deal->title ?? 'N/A',
                        $invoice->total_amount,
                        strtoupper($invoice->status),
                        $invoice->is_proforma ? 'Yes' : 'No'
                    ]);
                }
                fclose($file);
            };
        } else {
            $query = Deal::whereBetween('created_at', [$startDate, $endDate])->with('customer', 'owner');

            if ($isRestricted) {
                $query->where(function ($q) use ($user) {
                    if ($user->role === 'HOD' && $user->department) {
                        $q->where('department_split', 'like', '%' . $user->department . '%');
                    } else {
                        $q->where('user_id', $user->id)
                            ->orWhereHas('teamMembers', function ($sq) use ($user) {
                                $sq->where('users.id', $user->id);
                            });
                    }
                });
            } elseif ($department) {
                $query->where('type', $department);
            }

            $data = $query->get();
            $filename = "deals_report_" . now()->format('YmdHis') . ".csv";
            $headers = ['Date', 'Title', 'Customer', 'Owner', 'Type', 'Stage', 'Amount', 'Probability'];

            $callback = function () use ($data, $headers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);
                foreach ($data as $deal) {
                    fputcsv($file, [
                        $deal->created_at->format('Y-m-d'),
                        $deal->title,
                        $deal->customer->name ?? 'N/A',
                        $deal->owner->name ?? 'N/A',
                        $deal->type,
                        $deal->stage,
                        $deal->revenue,
                        $deal->probability . '%'
                    ]);
                }
                fclose($file);
            };
        }

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}
