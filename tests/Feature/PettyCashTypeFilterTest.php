<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\PettyCashItem;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PettyCashTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeAdmin;
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

        $this->staff = User::factory()->create([
            'role' => 'Staff',
            'department' => 'Tech',
            'email' => 'staff@loops.lk',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'General Expense',
            'status' => 'active',
        ]);
    }

    public function test_filter_all_requests_returns_both_petty_cash_and_iou(): void
    {
        $pcRequest = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 1000,
            'is_iou' => false,
        ]);

        $iouRequest = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 5000,
            'is_iou' => true,
        ]);

        $response = $this->actingAs($this->staff)->get(route('petty-cash.index', ['scope' => 'my_requests', 'type' => 'all']));

        $response->assertOk();
        $response->assertViewHas('pettyCashes', function ($items) use ($pcRequest, $iouRequest) {
            return $items->contains('id', $pcRequest->id) && $items->contains('id', $iouRequest->id);
        });
        $response->assertViewHas('totalCount', 2);
        $response->assertViewHas('pettyCashCount', 1);
        $response->assertViewHas('iouCount', 1);
    }

    public function test_filter_petty_cash_only_returns_non_iou_requests(): void
    {
        $pcRequest = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 1000,
            'is_iou' => false,
        ]);

        $iouRequest = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 5000,
            'is_iou' => true,
        ]);

        $response = $this->actingAs($this->staff)->get(route('petty-cash.index', ['scope' => 'my_requests', 'type' => 'petty_cash']));

        $response->assertOk();
        $response->assertViewHas('pettyCashes', function ($items) use ($pcRequest, $iouRequest) {
            return $items->contains('id', $pcRequest->id) && !$items->contains('id', $iouRequest->id);
        });
    }

    public function test_filter_iou_only_returns_iou_requests(): void
    {
        $pcRequest = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 1000,
            'is_iou' => false,
        ]);

        $iouRequest = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 5000,
            'is_iou' => true,
        ]);

        $response = $this->actingAs($this->staff)->get(route('petty-cash.index', ['scope' => 'my_requests', 'type' => 'iou']));

        $response->assertOk();
        $response->assertViewHas('pettyCashes', function ($items) use ($pcRequest, $iouRequest) {
            return !$items->contains('id', $pcRequest->id) && $items->contains('id', $iouRequest->id);
        });
    }

    public function test_combining_type_and_status_filters(): void
    {
        // 1. Pending Petty Cash
        $pendingPc = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_super_admin',
            'total_amount' => 1000,
            'is_iou' => false,
        ]);

        // 2. Approved Petty Cash
        $approvedPc = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'approved',
            'total_amount' => 2000,
            'is_iou' => false,
        ]);

        // 3. Approved IOU (iou_issued)
        $approvedIou = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'iou_issued',
            'total_amount' => 6000,
            'is_iou' => true,
        ]);

        // 4. Pending Settlement IOU
        $pendingSettlementIou = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_settlement',
            'total_amount' => 7000,
            'is_iou' => true,
        ]);

        // Query IOU + Approved
        $response = $this->actingAs($this->staff)->get(route('petty-cash.index', [
            'scope' => 'my_requests',
            'type' => 'iou',
            'status' => 'approved',
        ]));

        $response->assertOk();
        $response->assertViewHas('pettyCashes', function ($items) use ($approvedIou) {
            return $items->count() === 1 && $items->first()->id === $approvedIou->id;
        });

        // Query Petty Cash + Approved
        $responsePc = $this->actingAs($this->staff)->get(route('petty-cash.index', [
            'scope' => 'my_requests',
            'type' => 'petty_cash',
            'status' => 'approved',
        ]));

        $responsePc->assertOk();
        $responsePc->assertViewHas('pettyCashes', function ($items) use ($approvedPc) {
            return $items->count() === 1 && $items->first()->id === $approvedPc->id;
        });
    }

    public function test_ui_renders_filter_pills_and_labels(): void
    {
        PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 1500,
            'is_iou' => false,
        ]);

        PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_hod',
            'total_amount' => 8000,
            'is_iou' => true,
        ]);

        $response = $this->actingAs($this->staff)->get(route('petty-cash.index', ['scope' => 'my_requests']));

        $response->assertOk();
        $response->assertSee('Request Type:');
        $response->assertSee('All Requests');
        $response->assertSee('Petty Cash');
        $response->assertSee('IOU Requests');
        $response->assertSee('All Statuses');
    }
}
