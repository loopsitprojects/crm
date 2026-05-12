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
        $reportType = $request->input('report_type');

        $stages = [
            'Planned to Meet',
            'Introductory meeting',
            'Brief Stage',
            'Working on pitch',
            'Pitched',
            'Objection handling',
            'Finalizing terms',
            'Closed Won',
            'Rejected'
        ];

        $user = auth()->user();
        $isRestricted = !in_array($user->role, ['Super Admin', 'Management']);

        // Category Mappings
        $sbuDepts = ['Creative', 'Digital', 'Tech'];
        $salesDepts = ['AM', 'BD'];

        // Base Query with RBAC & Filters
        $applyFilters = function ($query) use ($startDate, $endDate, $department, $customerName, $isRestricted, $user, $sbuDepts, $salesDepts) {
            $query->whereBetween('close_date', [$startDate->startOfDay(), $endDate->endOfDay()]);

            if ($department) {
                if ($department === 'SBU') {
                    $query->where(function($q) use ($sbuDepts) {
                        foreach ($sbuDepts as $dept) {
                            $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } elseif ($department === 'Sales') {
                    $query->where(function($q) use ($salesDepts) {
                        foreach ($salesDepts as $dept) {
                            $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } else {
                    $query->whereJsonContains('department_split', [['department' => $department]]);
                }
            }

            if ($customerName) {
                $query->whereHas('customer', function ($q) use ($customerName) {
                    $q->where('name', 'LIKE', "%{$customerName}%");
                });
            }

            if ($isRestricted) {
                $query->where(function ($q) use ($user) {
                    // Own deals
                    $q->where('user_id', $user->id)
                      // Team member deals
                      ->orWhereHas('teamMembers', function ($tm) use ($user) {
                          $tm->where('users.id', $user->id);
                      });
                    
                    // Department split check (if user has a department)
                    if ($user->department) {
                        $q->orWhereJsonContains('department_split', [['department' => $user->department]]);
                    }
                    
                    // HOD specific: subordinates
                    if ($user->role === 'HOD') {
                        // Deals owned by subordinates
                        $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                        if ($subordinateIds->isNotEmpty()) {
                            $q->orWhereIn('user_id', $subordinateIds);
                        }
                    }
                });
            }
            return $query;
        };

        $dealQuery = $applyFilters(Deal::query());
        if ($stageFilter) {
            $dealQuery->where('stage', $stageFilter);
        }

        // Apply Report Type Filters (These should work ON TOP of other filters for consistency)
        if ($reportType === 'pending') {
            $dealQuery->whereNotIn('stage', ['Closed Won', 'Rejected']);
        } elseif ($reportType === 'complete') {
            $dealQuery->where('stage', 'Closed Won');
        } elseif ($reportType === 'deadlines') {
            $dealQuery->whereNotNull('close_date')
                ->where('close_date', '>=', now()->toDateString());
        }
        
        // Sorting
        if ($reportType === 'deadlines') {
            $dealQuery->orderBy('close_date', 'asc');
        } else {
            $dealQuery->latest();
        }

        $invoiceQuery = Invoice::with('customer', 'estimate.deal')
            ->where('invoices.is_proforma', false)
            ->whereBetween('invoices.created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
        
        // Add table name to dealQuery as well if it's used in joins later
        // (Currently not used in joins that would cause ambiguity, but good practice)

        if ($department) {
            $invoiceQuery->whereHas('estimate.deal', function ($q) use ($department, $sbuDepts, $salesDepts) {
                if ($department === 'SBU') {
                    $q->where(function($sq) use ($sbuDepts) {
                        foreach ($sbuDepts as $dept) {
                            $sq->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } elseif ($department === 'Sales') {
                    $q->where(function($sq) use ($salesDepts) {
                        foreach ($salesDepts as $dept) {
                            $sq->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } else {
                    $q->whereJsonContains('department_split', [['department' => $department]]);
                }
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
                    $dq->where(function ($sq) use ($user) {
                        // Own deals
                        $sq->where('user_id', $user->id)
                          // Team member deals
                          ->orWhereHas('teamMembers', function ($tm) use ($user) {
                              $tm->where('users.id', $user->id);
                          });
                        
                        // Department split check (if user has a department)
                        if ($user->department) {
                            $sq->orWhereJsonContains('department_split', [['department' => $user->department]]);
                        }
                        
                        // HOD specific: subordinates
                        if ($user->role === 'HOD') {
                            $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                            if ($subordinateIds->isNotEmpty()) {
                                $sq->orWhereIn('user_id', $subordinateIds);
                            }
                        }
                    });
                });
            });
        }

        // Unified Split Calculation Logic is now handled via private method $this->calculateDealSplits()

        // Fetch all deals with estimates and items for metrics
        $allReportDeals = (clone $dealQuery)->with(['estimates.items', 'estimates.thirdPartyCosts'])->get();
        $allReportDeals->each(function($deal) use ($request, $user, $isRestricted) {
            $this->calculateDealSplits($deal, $request, $user, $isRestricted);
        });

        // Expanded Metrics
        $totalDealRevenue = $allReportDeals->sum('revenue');
        $openDealsCount = $allReportDeals->whereIn('stage', ['Planned to Meet', 'Introductory meeting', 'Brief Stage', 'Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms'])->count();
        $weightedRevenue = $allReportDeals->whereIn('stage', ['Planned to Meet', 'Introductory meeting', 'Brief Stage', 'Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms'])->sum('revenue');
        $approvedRevenue = $allReportDeals->where('stage', 'Closed Won')->sum('revenue');
        $newDeals30 = Deal::where('created_at', '>=', now()->subDays(30));
        if ($isRestricted) {
            $newDeals30->where(function ($q) use ($user) {
                // Own deals
                $q->where('user_id', $user->id)
                  // Team member deals
                  ->orWhereHas('teamMembers', function ($tm) use ($user) {
                      $tm->where('users.id', $user->id);
                  });
                
                // Department split check (if user has a department)
                if ($user->department) {
                    $q->orWhereJsonContains('department_split', [['department' => $user->department]]);
                }
                
                // HOD specific: subordinates
                if ($user->role === 'HOD') {
                    $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                    if ($subordinateIds->isNotEmpty()) {
                        $q->orWhereIn('user_id', $subordinateIds);
                    }
                }
            });
        }
        $newDeals30Revenue = $allReportDeals->where('created_at', '>=', now()->subDays(30))->sum('revenue');

        $avgDealAge = $allReportDeals->avg(function($deal) {
            return now()->diffInDays($deal->created_at);
        }) ?: 0;

        $invoicedAmount = (clone $invoiceQuery)
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->sum('invoice_items.amount');
            
        $paymentCollected = (clone $invoiceQuery)
            ->where('invoices.status', 'paid')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->sum('invoice_items.amount');
            
        $pendingAmount = (clone $invoiceQuery)
            ->where('invoices.status', '!=', 'paid')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->sum('invoice_items.amount');

        // Legacy variable for view compatibility if needed
        $revenue = $paymentCollected;
        $dealsRevenue = $totalDealRevenue;

        // Data for Charts
        $dailyRevenue = (clone $invoiceQuery)
            ->where('invoices.status', 'paid')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->select(DB::raw('DATE(invoices.created_at) as report_date'), DB::raw('SUM(invoice_items.amount) as total'))
            ->groupBy(DB::raw('DATE(invoices.created_at)'))
            ->orderBy('report_date')
            ->get();

        $dealsByStage = $allReportDeals->groupBy('stage')->map(function ($group, $stage) {
            return (object)[
                'stage' => $stage,
                'count' => $group->count(),
                'total' => $group->sum('revenue')
            ];
        })->values();

        $revenueByDeptQuery = DB::table('invoices')
            ->join('quotations', 'invoices.quotation_id', '=', 'quotations.id')
            ->join('deals', 'quotations.deal_id', '=', 'deals.id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->where('invoices.status', 'paid');

        if ($isRestricted) {
            $revenueByDeptQuery->where(function ($q) use ($user) {
                // Own deals
                $q->where('deals.user_id', $user->id)
                  // Team member deals
                  ->orWhereExists(function ($qe) use ($user) {
                      $qe->select(DB::raw(1))
                         ->from('deal_user')
                         ->whereColumn('deal_user.deal_id', 'deals.id')
                         ->where('deal_user.user_id', $user->id);
                  });
                
                // Department split check (if user has a department)
                if ($user->department) {
                    $q->orWhereJsonContains('deals.department_split', [['department' => $user->department]]);
                }
                
                // HOD specific: subordinates
                if ($user->role === 'HOD') {
                    $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                    if ($subordinateIds->isNotEmpty()) {
                        $q->orWhereIn('deals.user_id', $subordinateIds);
                    }
                }
            });
        }

        if ($department) {
            if ($department === 'SBU') {
                $revenueByDeptQuery->where(function($q) use ($sbuDepts) {
                    foreach ($sbuDepts as $dept) {
                        $q->orWhereJsonContains('deals.department_split', [['department' => $dept]]);
                    }
                });
            } elseif ($department === 'Sales') {
                $revenueByDeptQuery->where(function($q) use ($salesDepts) {
                    foreach ($salesDepts as $dept) {
                        $q->orWhereJsonContains('deals.department_split', [['department' => $dept]]);
                    }
                });
            } else {
                $revenueByDeptQuery->whereJsonContains('deals.department_split', [['department' => $department]]);
            }
        }

        $revenueByDept = $revenueByDeptQuery
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->select('deals.type', DB::raw('SUM(invoice_items.amount) as total'))
            ->groupBy('deals.type')
            ->get();

        // Use Deals as the primary source for the Detailed Report to ensure all matching counts are visible
        $detailedData = (clone $dealQuery)
            ->with(['owner', 'customer', 'estimates.items', 'estimates.thirdPartyCosts', 'estimates.invoices.items'])
            ->latest()
            ->paginate(25);

        $detailedData->each(function($deal) use ($request, $user, $isRestricted) {
            $this->calculateDealSplits($deal, $request, $user, $isRestricted);

            $estimate = $deal->estimates->first();
            $invoice = $estimate ? $estimate->invoices->where('is_proforma', false)->first() : null;
            
            if ($invoice && $invoice->items->isNotEmpty()) {
                $deal->first_invoice_item = $invoice->items->first();
                $deal->first_invoice_item->invoice = $invoice;
            } else {
                $dummyItem = new \stdClass();
                $dummyItem->description = $deal->title;
                $dummyItem->amount = $deal->revenue ?? 0;
                $dummyItem->sscl_amount = 0;
                $dummyItem->vat_amount = 0;
                $dummyItem->total_with_vat = $deal->revenue ?? 0;
                $dummyItem->revenue_category = 'N/A';
                $dummyItem->department = 'N/A';

                $dummyInvoice = new \stdClass();
                $dummyInvoice->date = $deal->created_at->format('Y-m-d');
                $dummyInvoice->invoice_number = 'N/A';
                $dummyInvoice->status = 'pending';
                $dummyInvoice->total_amount = $deal->revenue ?? 0;
                $dummyInvoice->customer = $deal->customer;

                $dummyItem->invoice = $dummyInvoice;
                $deal->first_invoice_item = $dummyItem;
            }
        });

        $incomeBreakdown = [];

        // Stats for Quick Link cards - Must match the table's filter logic exactly!
        $pendingCount = $applyFilters(Deal::query());
        if ($stageFilter) $pendingCount->where('stage', $stageFilter);
        $pendingCount = $pendingCount->whereNotIn('stage', ['Closed Won', 'Rejected'])->count();

        $completeCount = $applyFilters(Deal::query());
        if ($stageFilter) $completeCount->where('stage', $stageFilter);
        $completeCount = $completeCount->where('stage', 'Closed Won')->count();

        $deadlineCount = $applyFilters(Deal::query());
        if ($stageFilter) $deadlineCount->where('stage', $stageFilter);
        $deadlineCount = $deadlineCount->whereNotNull('close_date')->where('close_date', '>=', now()->toDateString())->count();

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
            'detailedData',
            'customerName',
            'stageFilter',
            'stages',
            'incomeBreakdown',
            'reportType',
            'pendingCount',
            'completeCount',
            'deadlineCount'
        ));
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : now()->endOfMonth();
        $department = $request->input('department');
        $type = $request->input('type', 'deals');
        $reportType = $request->input('report_type');

        $user = auth()->user();
        $isRestricted = !in_array($user->role, ['Super Admin', 'Management']);

        // Category Mappings
        $sbuDepts = ['Creative', 'Digital', 'Tech'];
        $salesDepts = ['AM', 'BD'];

        if ($type === 'detailed') {
            // Updated to match the new Deal-based detailed report logic
            $dealQuery = Deal::whereBetween('close_date', [$startDate->startOfDay(), $endDate->endOfDay()]);
            
            if ($isRestricted) {
                $dealQuery->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereHas('teamMembers', function ($tm) use ($user) {
                          $tm->where('users.id', $user->id);
                      })
                      ->orWhereJsonContains('department_split', [['department' => $user->department]]);
                    
                    if ($user->role === 'HOD') {
                        $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                        if ($subordinateIds->isNotEmpty()) {
                            $q->orWhereIn('user_id', $subordinateIds);
                        }
                    }
                });
            }

            if ($department) {
                if ($department === 'SBU') {
                    $dealQuery->where(function($q) use ($sbuDepts) {
                        foreach ($sbuDepts as $dept) {
                            $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } elseif ($department === 'Sales') {
                    $dealQuery->where(function($q) use ($salesDepts) {
                        foreach ($salesDepts as $dept) {
                            $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } else {
                    $dealQuery->whereJsonContains('department_split', [['department' => $department]]);
                }
            }

            if ($reportType === 'pending') {
                $dealQuery->whereNotIn('stage', ['Closed Won', 'Rejected']);
            } elseif ($reportType === 'complete') {
                $dealQuery->where('stage', 'Closed Won');
            } elseif ($reportType === 'deadlines') {
                $dealQuery->whereNotNull('close_date')
                    ->where('close_date', '>=', now()->toDateString());
            }

            $data = $dealQuery->with(['owner', 'customer', 'estimates.items', 'estimates.thirdPartyCosts', 'estimates.invoices.items'])->get();

            $data->each(function($deal) use ($request, $user, $isRestricted) {
                $this->calculateDealSplits($deal, $request, $user, $isRestricted);
            });

            $filename = "detailed_report_" . now()->format('YmdHis') . ".csv";
            $headers = [
                'Inv Date', 'Est Date', 'Inv No', 'Est No', 'Job No', 'Invoiced Month/ Closing month', 
                'Client Name', 'TIN', 'Brand', 'Description', 'Line Amount', 'SSCL', 'VAT', 
                'Total Amount', 'Con Confirmed', 'Revenue Category', 'Department', 'Data Inputter', 
                'Stages', 'Advance payment Status', 'Payment Status', 'Balance Due'
            ];

            $callback = function () use ($data, $headers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);
                foreach ($data as $deal) {
                    $estimate = $deal->estimates->first();
                    $invoice = $estimate ? $estimate->invoices->where('is_proforma', false)->first() : null;
                    $item = $invoice ? $invoice->items->first() : null;

                    $total = $invoice->total_amount ?? ($deal->revenue ?? 0);
                    $balanceDue = ($invoice && ($invoice->status ?? '') === 'paid') ? 0 : $total;
                    $advanceStatus = ($estimate && ($estimate->advance_received_amount ?? 0) > 0) ? 'RECEIVED' : 'PENDING';

                    fputcsv($file, [
                        $invoice->date ?? 'N/A',
                        $estimate->date ?? 'N/A',
                        $invoice->invoice_number ?? 'N/A',
                        $estimate->reference_number ?? 'N/A',
                        $deal->job_number ?? 'N/A',
                        ($invoice && isset($invoice->date)) ? date('M Y', strtotime($invoice->date)) : ($deal->close_date ? date('M Y', strtotime($deal->close_date)) : 'N/A'),
                        $deal->customer->name ?? 'N/A',
                        $deal->customer->customer_tax_number ?? 'N/A',
                        $estimate->brand_name ?? 'N/A',
                        $item->description ?? $deal->title,
                        $item->amount ?? ($deal->revenue ?? 0),
                        $item->sscl_amount ?? 0,
                        $item->vat_amount ?? 0,
                        $item->total_with_vat ?? ($deal->revenue ?? 0),
                        $deal->contribution ?? 0,
                        $item->revenue_category ?? 'N/A',
                        $deal->owner->department ?? 'N/A',
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
                        if ($user->role === 'HOD' && $user->department) {
                            $sq->whereJsonContains('department_split', [['department' => $user->department]]);
                        } else {
                            $sq->where('user_id', $user->id)
                                ->orWhereHas('teamMembers', function ($ssq) use ($user) {
                                    $ssq->where('users.id', $user->id);
                                });
                        }
                    });
                });
            }

            if ($reportType === 'pending') {
                $query->whereHas('estimate.deal', function ($dq) {
                    $dq->whereNotIn('stage', ['Closed Won', 'Rejected']);
                });
            } elseif ($reportType === 'complete') {
                $query->whereHas('estimate.deal', function ($dq) {
                    $dq->where('stage', 'Closed Won');
                });
            } elseif ($reportType === 'deadlines') {
                $query->whereHas('estimate.deal', function ($dq) {
                    $dq->whereNotNull('close_date')
                        ->where('close_date', '>=', now()->toDateString());
                });
            }
            
            if ($department) {
                $query->whereHas('estimate.deal', function ($q) use ($department, $sbuDepts, $salesDepts) {
                    if ($department === 'SBU') {
                        $q->where(function($sq) use ($sbuDepts) {
                            foreach ($sbuDepts as $dept) {
                                $sq->orWhereJsonContains('department_split', [['department' => $dept]]);
                            }
                        });
                    } elseif ($department === 'Sales') {
                        $q->where(function($sq) use ($salesDepts) {
                            foreach ($salesDepts as $dept) {
                                $sq->orWhereJsonContains('department_split', [['department' => $dept]]);
                            }
                        });
                    } else {
                        $q->whereJsonContains('department_split', [['department' => $department]]);
                    }
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
            $query = Deal::whereBetween('close_date', [$startDate->startOfDay(), $endDate->endOfDay()])->with('customer', 'owner');

            if ($reportType === 'pending') {
                $query->whereNotIn('stage', ['Closed Won', 'Rejected']);
            } elseif ($reportType === 'complete') {
                $query->where('stage', 'Closed Won');
            } elseif ($reportType === 'deadlines') {
                $query->whereNotNull('close_date')
                    ->where('close_date', '>=', now()->toDateString())
                    ->orderBy('close_date', 'asc');
            }

            if ($isRestricted) {
                $query->where(function ($q) use ($user) {
                    // Own deals
                    $q->where('user_id', $user->id)
                      // Team member deals
                      ->orWhereHas('teamMembers', function ($tm) use ($user) {
                          $tm->where('users.id', $user->id);
                      });
                    
                    // Department split check (if user has a department)
                    if ($user->department) {
                        $q->orWhereJsonContains('department_split', [['department' => $user->department]]);
                    }
                    
                    // HOD specific: subordinates
                    if ($user->role === 'HOD') {
                        $subordinateIds = \App\Models\User::where('supervisor_id', $user->id)->pluck('id');
                        if ($subordinateIds->isNotEmpty()) {
                            $q->orWhereIn('user_id', $subordinateIds);
                        }
                    }
                });
            }
            
            if ($department) {
                if ($department === 'SBU') {
                    $query->where(function($q) use ($sbuDepts) {
                        foreach ($sbuDepts as $dept) {
                            $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } elseif ($department === 'Sales') {
                    $query->where(function($q) use ($salesDepts) {
                        foreach ($salesDepts as $dept) {
                            $q->orWhereJsonContains('department_split', [['department' => $dept]]);
                        }
                    });
                } else {
                    $query->whereJsonContains('department_split', [['department' => $department]]);
                }
            }

            $data = $query->with(['estimates.items', 'estimates.thirdPartyCosts'])->get();
            
            // Adjust revenue/contribution metrics to exclude VAT and SSCL, and deduct third party costs
            $data->each(function($deal) use ($user, $isRestricted, $request) {
                $estimate = $deal->estimates->first();
                if ($estimate) {
                    // Calculate total excluding VAT and SSCL: sum of amount from items
                    $preTaxTotal = $estimate->items->sum(function($item) {
                        return (float)$item->amount;
                    });
                    
                    // Calculate third party costs
                    $thirdPartyTotal = $estimate->thirdPartyCosts->sum('cost');
                    
                    if ($preTaxTotal > 0) {
                        $deal->revenue = $preTaxTotal;
                        $deal->contribution = $preTaxTotal - $thirdPartyTotal;
                    }
                }

                // Apply department split override for CSV export consistency
                $activeDept = $request->input('department') ?: null;
                if (!$activeDept && $isRestricted) {
                    $activeDept = $user->department;
                }

                $deptRevenue = 0;
                $deptContribution = 0;
                
                if ($activeDept) {
                    $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
                    if (is_array($splits) && !empty($splits)) {
                        foreach ($splits as $split) {
                            $splitDept = trim(strtolower($split['department'] ?? ''));
                            $targetDept = trim(strtolower($activeDept));
                            if ($splitDept === $targetDept) {
                                $revPercent = (float)($split['revenue_percentage'] ?? 0);
                                $conPercent = (float)($split['contribution_percentage'] ?? 0);
                                if ($revPercent > 0) {
                                    $deptRevenue += ($deal->revenue * ($revPercent / 100));
                                } else {
                                    $deptRevenue += (float)($split['revenue_amount'] ?? 0);
                                }
                                if ($conPercent > 0) {
                                    $deptContribution += ($deal->contribution * ($conPercent / 100));
                                } else {
                                    $deptContribution += (float)($split['contribution_amount'] ?? 0);
                                }
                            }
                        }
                    }
                }
                
                // Owner keeps 100%, others get split if a filter/restriction applies
                if ($deal->user_id !== $user->id) {
                    if ($activeDept || $isRestricted) {
                        $deal->revenue = $deptRevenue;
                        $deal->contribution = $deptContribution;
                    }
                }
            });

            $filename = "deals_report_" . now()->format('YmdHis') . ".csv";
            $headers = ['Date', 'Title', 'Customer', 'Owner', 'Type', 'Stage', 'Amount', 'Probability'];

            $callback = function () use ($data, $headers) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $headers);
                foreach ($data as $deal) {
                    fputcsv($file, [
                        $deal->close_date ? \Carbon\Carbon::parse($deal->close_date)->format('Y-m-d') : 'N/A',
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
    private function calculateDealSplits(\App\Models\Deal $deal, Request $request, \App\Models\User $user, bool $isRestricted)
    {
        $estimate = $deal->estimates->first();
        if ($estimate) {
            $preTaxTotal = $estimate->items->sum(fn($item) => (float)$item->amount);
            $thirdPartyTotal = $estimate->thirdPartyCosts->sum('cost');
            if ($preTaxTotal > 0) {
                $deal->revenue = $preTaxTotal;
                $deal->contribution = $preTaxTotal - $thirdPartyTotal;
            }
        }

        $activeDept = $request->input('department') ?: null;
        if (!$activeDept && $isRestricted) $activeDept = $user->department;

        if ($activeDept && $deal->user_id !== $user->id) {
            $deptRevenue = 0;
            $deptContribution = 0;
            $splits = is_string($deal->department_split) ? json_decode($deal->department_split, true) : $deal->department_split;
            if (is_array($splits) && !empty($splits)) {
                foreach ($splits as $split) {
                    if (trim(strtolower($split['department'] ?? '')) === trim(strtolower($activeDept))) {
                        $revPercent = (float)($split['revenue_percentage'] ?? 0);
                        $conPercent = (float)($split['contribution_percentage'] ?? 0);
                        $deptRevenue += $revPercent > 0 ? ($deal->revenue * ($revPercent / 100)) : (float)($split['revenue_amount'] ?? 0);
                        $deptContribution += $conPercent > 0 ? ($deal->contribution * ($conPercent / 100)) : (float)($split['contribution_amount'] ?? 0);
                    }
                }
            }
            $deal->revenue = $deptRevenue;
            $deal->contribution = $deptContribution;
        }
    }
}
