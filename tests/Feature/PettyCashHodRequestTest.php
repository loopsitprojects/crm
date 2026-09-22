<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashHodRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $management;
    private User $hodWithSupervisor;
    private User $hodWithoutSupervisor;
    private User $financeAdmin;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->management = User::factory()->create([
            'name' => 'Management Boss',
            'email' => 'management@test.com',
            'role' => 'Management',
        ]);

        $this->hodWithSupervisor = User::factory()->create([
            'name' => 'HOD With Management',
            'email' => 'hod_managed@test.com',
            'role' => 'HOD',
            'department' => 'Creative',
            'supervisor_id' => $this->management->id,
        ]);

        $this->hodWithoutSupervisor = User::factory()->create([
            'name' => 'HOD Lone Wolf',
            'email' => 'hod_alone@test.com',
            'role' => 'HOD',
            'department' => 'Digital',
            'supervisor_id' => null,
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Chief',
            'email' => 'finance@test.com',
            'role' => 'Finance Admin',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Office Supplies',
            'status' => 'active',
        ]);
    }

    public function test_hod_with_assigned_hod_routes_to_assigned_hod(): void
    {
        $response = $this->actingAs($this->hodWithSupervisor)->post(route('petty-cash.store'), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 5000,
                    'description' => 'Creative software license',
                ]
            ],
        ]);

        $response->assertRedirect();
        
        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertEquals($this->hodWithSupervisor->id, $request->user_id);
        $this->assertEquals($this->management->id, $request->hod_id);
        $this->assertEquals('pending_hod', $request->status);

        // Management can approve the request
        $approveResponse = $this->actingAs($this->management)->post(route('petty-cash.hodApprove', $request));
        $approveResponse->assertRedirect();

        $request->refresh();
        $this->assertEquals('pending_super_admin', $request->status);

        // Finance Admin can then do final approval
        $financeResponse = $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $request));
        $financeResponse->assertRedirect();

        $request->refresh();
        $this->assertEquals('approved', $request->status);
    }

    public function test_hod_without_assigned_hod_routes_directly_to_finance(): void
    {
        $response = $this->actingAs($this->hodWithoutSupervisor)->post(route('petty-cash.store'), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 3500,
                    'description' => 'Digital team tools',
                ]
            ],
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertEquals($this->hodWithoutSupervisor->id, $request->user_id);
        $this->assertNull($request->hod_id);
        $this->assertEquals('pending_super_admin', $request->status);

        // Finance Admin can approve directly
        $financeResponse = $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $request));
        $financeResponse->assertRedirect();

        $request->refresh();
        $this->assertEquals('approved', $request->status);
    }

    public function test_hod_reappeal_routes_to_assigned_hod_when_available(): void
    {
        $request = PettyCashRequest::create([
            'reference_number' => 'PC-2026-9001',
            'user_id' => $this->hodWithSupervisor->id,
            'hod_id' => $this->management->id,
            'department' => 'Creative',
            'total_amount' => 2000,
            'approved_amount' => 2000,
            'is_iou' => 0,
            'status' => 'rejected_by_hod',
            'hod_rejection_note' => 'Please provide more details',
        ]);

        $response = $this->actingAs($this->hodWithSupervisor)->post(route('petty-cash.reappeal', $request), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 2000,
                    'description' => 'Updated details for software',
                ]
            ],
        ]);

        $response->assertRedirect();
        $request->refresh();

        $this->assertEquals('pending_hod', $request->status);
        $this->assertEquals($this->management->id, $request->hod_id);
    }

    public function test_hod_reappeal_routes_to_finance_when_no_assigned_hod(): void
    {
        $request = PettyCashRequest::create([
            'reference_number' => 'PC-2026-9002',
            'user_id' => $this->hodWithoutSupervisor->id,
            'hod_id' => null,
            'department' => 'Digital',
            'total_amount' => 2000,
            'approved_amount' => 2000,
            'is_iou' => 0,
            'status' => 'rejected_by_super_admin',
            'admin_rejection_note' => 'Not approved currently',
        ]);

        $response = $this->actingAs($this->hodWithoutSupervisor)->post(route('petty-cash.reappeal', $request), [
            'is_iou' => 0,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 2000,
                    'description' => 'Updated justification',
                ]
            ],
        ]);

        $response->assertRedirect();
        $request->refresh();

        $this->assertEquals('pending_super_admin', $request->status);
        $this->assertNull($request->hod_id);
    }
}
