<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Notifications\PettyCashNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PettyCashSendToHodTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeAdmin;
    protected User $hod1;
    protected User $hod2;
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

        $this->hod1 = User::factory()->create([
            'role' => 'HOD',
            'department' => 'Tech',
            'email' => 'hod1@loops.lk',
        ]);

        $this->hod2 = User::factory()->create([
            'role' => 'HOD',
            'department' => 'Creative',
            'email' => 'hod2@loops.lk',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'Staff',
            'department' => 'Tech',
            'supervisor_id' => $this->hod1->id,
            'email' => 'staff@loops.lk',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'General Expense',
            'status' => 'active',
        ]);
    }

    public function test_finance_admin_can_transfer_petty_cash_request_to_hod(): void
    {
        $pettyCash = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_super_admin',
            'total_amount' => 2500,
            'is_iou' => false,
            'hod_id' => null,
        ]);

        $response = $this->actingAs($this->financeAdmin)->post(route('petty-cash.sendToHod', $pettyCash), [
            'hod_id' => $this->hod1->id,
            'hod_notes' => 'Please verify these client meeting expenses',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $pettyCash->refresh();
        $this->assertEquals('pending_hod', $pettyCash->status);
        $this->assertEquals($this->hod1->id, $pettyCash->hod_id);
        $this->assertStringContainsString('Please verify these client meeting expenses', $pettyCash->extra_notes);

        Notification::assertSentTo($this->hod1, PettyCashNotification::class, function ($notification) {
            return $notification->action === 'sent_to_hod';
        });

        Notification::assertSentTo($this->staff, PettyCashNotification::class, function ($notification) {
            return $notification->action === 'sent_to_hod';
        });
    }

    public function test_finance_admin_can_transfer_iou_request_to_different_hod(): void
    {
        $iou = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_management',
            'total_amount' => 15000,
            'is_iou' => true,
            'hod_id' => $this->hod1->id,
        ]);

        $response = $this->actingAs($this->financeAdmin)->post(route('petty-cash.sendToHod', $iou), [
            'hod_id' => $this->hod2->id,
            'hod_notes' => 'Re-routing to Creative HOD as project belongs there',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $iou->refresh();
        $this->assertEquals('pending_hod', $iou->status);
        $this->assertEquals($this->hod2->id, $iou->hod_id);

        Notification::assertSentTo($this->hod2, PettyCashNotification::class, function ($notification) {
            return $notification->action === 'sent_to_hod';
        });
    }

    public function test_finance_admin_transfer_during_settlement_routes_to_pending_settlement_hod(): void
    {
        $iou = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_settlement',
            'total_amount' => 5000,
            'settlement_amount' => 6500,
            'is_iou' => true,
            'hod_id' => $this->hod1->id,
        ]);

        $response = $this->actingAs($this->financeAdmin)->post(route('petty-cash.sendToHod', $iou), [
            'hod_id' => $this->hod1->id,
            'hod_notes' => 'Please check the exceeded settlement receipts',
        ]);

        $response->assertRedirect();
        $iou->refresh();
        $this->assertEquals('pending_settlement_hod', $iou->status);
    }

    public function test_non_finance_user_cannot_transfer_to_hod(): void
    {
        $pettyCash = PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_super_admin',
            'total_amount' => 2500,
            'is_iou' => false,
        ]);

        $response = $this->actingAs($this->staff)->post(route('petty-cash.sendToHod', $pettyCash), [
            'hod_id' => $this->hod1->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $pettyCash->refresh();
        $this->assertEquals('pending_super_admin', $pettyCash->status);
    }

    public function test_ui_shows_to_hod_button_for_finance_admin(): void
    {
        PettyCashRequest::create([
            'user_id' => $this->staff->id,
            'department' => 'Tech',
            'status' => 'pending_super_admin',
            'total_amount' => 2500,
            'is_iou' => false,
        ]);

        $response = $this->actingAs($this->financeAdmin)->get(route('petty-cash.index', ['scope' => 'approvals']));

        $response->assertOk();
        $response->assertSee('To HOD');
        $response->assertSee('sendToHodModal');
    }
}
