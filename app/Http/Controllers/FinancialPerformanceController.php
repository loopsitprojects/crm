<?php

namespace App\Http\Controllers;

use App\Services\FinancialPerformanceService;
use App\Traits\LogsActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialPerformanceController extends Controller
{
    use LogsActivity;

    protected FinancialPerformanceService $service;

    public function __construct(FinancialPerformanceService $service)
    {
        $this->service = $service;
    }

    /**
     * Authorize that the current authenticated user has IT Admin or Management role.
     */
    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canViewFinancialPerformance()) {
            abort(403, 'Unauthorized access. Only IT Admin and Management roles can access the Financial Performance Dashboard.');
        }
    }

    /**
     * Display the Executive Financial Performance Dashboard.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $this->logAction('Accessed Financial Performance tab');

        $dashboardData = $this->service->getDashboardData();

        return view('financial-performance.index', [
            'dashboardData' => $dashboardData,
        ]);
    }

    /**
     * Return JSON data for asynchronous live dashboard refreshes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $dashboardData = $this->service->getDashboardData();

        return response()->json($dashboardData);
    }
}
