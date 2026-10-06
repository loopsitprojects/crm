<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HrAdminPettyCashTest extends TestCase
{
    use RefreshDatabase;

    private User $hrAdmin;
    private User $staffUnderHr;
    private User $otherStaff;
    private User $management;
    private User $financeAdmin;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->management = User::factory()->create([
            'name' => 'Management Director',
            'email' => 'director@loops.com',
            'role' => 'Management',
        ]);

        $this->hrAdmin = User::factory()->create([
            'name' => 'Harini Perera',
            'email' => 'harini.hr@loops.com',
            'role' => 'HR Admin',
            'department' => 'Corporate',
            'supervisor_id' => $this->management->id,
        ]);

        $this->staffUnderHr = User::factory()->create([
            'name' => 'Kasun Silva',
            'email' => 'kasun@loops.com',
            'role' => 'Staff',
            'department' => 'Corporate',
            'supervisor_id' => $this->hrAdmin->id,
        ]);

        $otherHod = User::factory()->create([
            'name' => 'Tech Lead',
            'email' => 'tech@loops.com',
            'role' => 'HOD',
            'department' => 'Tech',
        ]);

        $this->otherStaff = User::factory()->create([
            'name' => 'Nimal Fernando',
            'email' => 'nimal@loops.com',
            'role' => 'Staff',
            'department' => 'Tech',
            'supervisor_id' => $otherHod->id,
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Chief',
            'email' => 'finance@loops.com',
            'role' => 'Finance Admin',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Recruitment & Team',
            'status' => 'active',
        ]);
    }

    public function test_hr_admin_role_has_staff_like_crm_restrictions(): void
    {
        // Blocked by PreventStaffAccess middleware from accessing reports
        $response = $this->actingAs($this->hrAdmin)->get(route('reports.index'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        // Can access dashboard
        $dashResponse = $this->actingAs($this->hrAdmin)->get(route('dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('HR Admin');
    }

    public function test_staff_request_routes_to_hr_admin_as_hod(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->staffUnderHr)->post(route('petty-cash.store'), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 4500,
                    'description' => 'Team interview hospitality',
                ]
            ],
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertEquals($this->staffUnderHr->id, $request->user_id);
        $this->assertEquals($this->hrAdmin->id, $request->hod_id);
        $this->assertEquals('pending_hod', $request->status);

        Notification::assertSentTo($this->hrAdmin, \App\Notifications\PettyCashNotification::class);
    }

    public function test_hr_admin_can_view_and_approve_staff_petty_cash(): void
    {
        $request = PettyCashRequest::create([
            'reference_number' => 'PC-2026-8001',
            'user_id' => $this->staffUnderHr->id,
            'hod_id' => $this->hrAdmin->id,
            'department' => 'Corporate',
            'total_amount' => 3000,
            'approved_amount' => 3000,
            'is_iou' => 0,
            'status' => 'pending_hod',
        ]);

        // HR Admin sees the request in approvals
        $viewResponse = $this->actingAs($this->hrAdmin)->get(route('petty-cash.index', ['scope' => 'approvals']));
        $viewResponse->assertOk();
        $viewResponse->assertSee('PC-2026-8001');

        // HR Admin approves request
        $approveResponse = $this->actingAs($this->hrAdmin)->post(route('petty-cash.hodApprove', $request));
        $approveResponse->assertRedirect();

        $request->refresh();
        $this->assertEquals('pending_super_admin', $request->status);
    }

    public function test_hr_admin_can_reject_staff_petty_cash(): void
    {
        $request = PettyCashRequest::create([
            'reference_number' => 'PC-2026-8002',
            'user_id' => $this->staffUnderHr->id,
            'hod_id' => $this->hrAdmin->id,
            'department' => 'Corporate',
            'total_amount' => 3000,
            'approved_amount' => 3000,
            'is_iou' => 0,
            'status' => 'pending_hod',
        ]);

        $rejectResponse = $this->actingAs($this->hrAdmin)->post(route('petty-cash.hodReject', $request), [
            'hod_rejection_note' => 'Please provide additional invoice copy.',
        ]);
        $rejectResponse->assertRedirect();

        $request->refresh();
        $this->assertEquals('rejected_by_hod', $request->status);
        $this->assertEquals('Please provide additional invoice copy.', $request->hod_rejection_note);
    }

    public function test_hr_admin_can_view_all_team_requests_of_own_staff(): void
    {
        $ownStaffRequest = PettyCashRequest::create([
            'reference_number' => 'PC-2026-8003',
            'user_id' => $this->staffUnderHr->id,
            'hod_id' => $this->hrAdmin->id,
            'department' => 'Corporate',
            'total_amount' => 2000,
            'approved_amount' => 2000,
            'is_iou' => 0,
            'status' => 'approved',
        ]);

        $otherStaffRequest = PettyCashRequest::create([
            'reference_number' => 'PC-2026-8004',
            'user_id' => $this->otherStaff->id,
            'hod_id' => $this->otherStaff->supervisor_id,
            'department' => 'Tech',
            'total_amount' => 9000,
            'approved_amount' => 9000,
            'is_iou' => 0,
            'status' => 'approved',
        ]);

        $teamResponse = $this->actingAs($this->hrAdmin)->get(route('petty-cash.index', ['scope' => 'all_team']));
        $teamResponse->assertOk();
        $teamResponse->assertSee('PC-2026-8003');
        $teamResponse->assertDontSee('PC-2026-8004');
    }

    public function test_hr_admin_cannot_approve_other_department_requests(): void
    {
        $otherStaffRequest = PettyCashRequest::create([
            'reference_number' => 'PC-2026-8005',
            'user_id' => $this->otherStaff->id,
            'hod_id' => $this->otherStaff->supervisor_id,
            'department' => 'Tech',
            'total_amount' => 5000,
            'approved_amount' => 5000,
            'is_iou' => 0,
            'status' => 'pending_hod',
        ]);

        $response = $this->actingAs($this->hrAdmin)->post(route('petty-cash.hodApprove', $otherStaffRequest));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Unauthorized action.');

        $otherStaffRequest->refresh();
        $this->assertEquals('pending_hod', $otherStaffRequest->status);
    }

    public function test_hr_admin_own_request_with_supervisor_routes_to_supervisor(): void
    {
        $response = $this->actingAs($this->hrAdmin)->post(route('petty-cash.store'), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 8000,
                    'description' => 'Annual training session supplies',
                ]
            ],
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertEquals($this->hrAdmin->id, $request->user_id);
        $this->assertEquals($this->management->id, $request->hod_id);
        $this->assertEquals('pending_hod', $request->status);
    }

    public function test_hr_admin_own_request_without_supervisor_routes_to_finance(): void
    {
        $loneHrAdmin = User::factory()->create([
            'name' => 'Independent HR',
            'email' => 'lone.hr@loops.com',
            'role' => 'HR Admin',
            'department' => 'Corporate',
            'supervisor_id' => null,
        ]);

        $response = $this->actingAs($loneHrAdmin)->post(route('petty-cash.store'), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 6000,
                    'description' => 'Employee recognition awards',
                ]
            ],
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertEquals($loneHrAdmin->id, $request->user_id);
        $this->assertNull($request->hod_id);
        $this->assertEquals('pending_super_admin', $request->status);
    }
}
