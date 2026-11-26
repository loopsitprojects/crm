<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\PettyCashItem;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PettyCashVoucherNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeAdmin;
    protected User $hod;
    protected User $staff;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->financeAdmin = User::factory()->create([
            'role' => 'Finance Admin',
            'email' => 'finance@loops.lk',
        ]);

        $this->hod = User::factory()->create([
            'role' => 'HOD',
            'department' => 'Tech',
            'email' => 'hod@loops.lk',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'Staff',
            'department' => 'Tech',
            'supervisor_id' => $this->hod->id,
            'email' => 'staff@loops.lk',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Employee Birthday',
            'status' => 'active',
        ]);
    }

    public function test_category_renamed_to_employee_birthday(): void
    {
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Employee Birthday',
        ]);
        $this->assertDatabaseMissing('expense_categories', [
            'name' => 'Employee Birthday Cake',
        ]);
    }

    public function test_submitting_petty_cash_request_does_not_generate_voucher_number(): void
    {
        $response = $this->actingAs($this->staff)->post(route('petty-cash.store'), [
            'hod_id' => $this->hod->id,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 1500,
                    'description' => 'Office tea and coffee',
                ],
            ],
            'is_iou' => 0,
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertNull($request->reference_number, 'Voucher number must be null upon request submission.');
        $this->assertEquals('pending_hod', $request->status);
    }

    public function test_submitting_iou_request_does_not_generate_voucher_number(): void
    {
        $response = $this->actingAs($this->staff)->post(route('petty-cash.store'), [
            'hod_id' => $this->hod->id,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 5000,
                    'description' => 'Advance for field visit',
                ],
            ],
            'is_iou' => 1,
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertNotNull($request);
        $this->assertTrue((bool)$request->is_iou);
        $this->assertNull($request->reference_number, 'IOU voucher number must be null upon request submission.');
    }

    public function test_finance_approval_generates_pc_voucher_number_for_standard_petty_cash(): void
    {
        $request = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'department' => 'Tech',
            'total_amount' => 2500,
            'approved_amount' => 2500,
            'is_iou' => 0,
            'status' => 'pending_super_admin',
        ]);

        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $request), [
            'signature' => $dummySig,
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertNotNull($request->reference_number);
        $year = date('Y');
        $this->assertStringStartsWith("PC-{$year}-", $request->reference_number);
    }

    public function test_finance_approval_generates_iou_voucher_number_starting_with_iou001(): void
    {
        $request1 = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'department' => 'Tech',
            'total_amount' => 3000,
            'approved_amount' => 3000,
            'is_iou' => 1,
            'status' => 'pending_super_admin',
        ]);

        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response1 = $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $request1), [
            'signature' => $dummySig,
        ]);
        $response1->assertRedirect();

        $request1->refresh();
        $this->assertEquals('iou_issued', $request1->status);
        $this->assertEquals('IOU001', $request1->reference_number);

        // Another staff member submits and gets approved for IOU
        $staff2 = User::factory()->create([
            'role' => 'Staff',
            'department' => 'Tech',
        ]);

        $request2 = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $staff2->id,
            'hod_id' => $this->hod->id,
            'department' => 'Tech',
            'total_amount' => 4500,
            'approved_amount' => 4500,
            'is_iou' => 1,
            'status' => 'pending_super_admin',
        ]);

        $response2 = $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $request2), [
            'signature' => $dummySig,
        ]);
        $response2->assertRedirect();

        $request2->refresh();
        $this->assertEquals('IOU002', $request2->reference_number);
    }

    public function test_iou_and_pc_sequences_are_completely_separate(): void
    {
        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // 1. Approve standard PC
        $pc1 = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 1000,
            'is_iou' => 0,
            'status' => 'pending_super_admin',
        ]);
        $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $pc1), ['signature' => $dummySig]);
        $pc1->refresh();
        $year = date('Y');
        $this->assertEquals("PC-{$year}-0001", $pc1->reference_number);

        // 2. Approve IOU
        $iou1 = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 2000,
            'is_iou' => 1,
            'status' => 'pending_super_admin',
        ]);
        $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $iou1), ['signature' => $dummySig]);
        $iou1->refresh();
        $this->assertEquals('IOU001', $iou1->reference_number);

        // 3. Approve another standard PC (must become PC-YYYY-0002, NOT affected by IOU001)
        $staff2 = User::factory()->create(['role' => 'Staff']);
        $pc2 = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $staff2->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 1200,
            'is_iou' => 0,
            'status' => 'pending_super_admin',
        ]);
        $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $pc2), ['signature' => $dummySig]);
        $pc2->refresh();
        $this->assertEquals("PC-{$year}-0002", $pc2->reference_number);

        // 4. Approve another IOU (must become IOU002, NOT affected by PC vouchers)
        $staff3 = User::factory()->create(['role' => 'Staff']);
        $iou2 = PettyCashRequest::create([
            'reference_number' => null,
            'user_id' => $staff3->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 3500,
            'is_iou' => 1,
            'status' => 'pending_super_admin',
        ]);
        $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $iou2), ['signature' => $dummySig]);
        $iou2->refresh();
        $this->assertEquals('IOU002', $iou2->reference_number);
    }
}
