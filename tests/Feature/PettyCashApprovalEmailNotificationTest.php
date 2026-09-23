<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\PettyCashItem;
use App\Models\PettyCashRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PettyCashNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PettyCashApprovalEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $hod;
    private User $financeAdmin;
    private User $management;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->management = User::factory()->create([
            'name' => 'Management User',
            'email' => 'management_exec@test.com',
            'role' => 'Management',
        ]);

        $this->hod = User::factory()->create([
            'name' => 'Creative HOD',
            'email' => 'creative_hod@test.com',
            'role' => 'HOD',
            'department' => 'Creative',
            'supervisor_id' => $this->management->id,
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff Member',
            'email' => 'staff_user@test.com',
            'role' => 'Staff',
            'department' => 'Creative',
            'supervisor_id' => $this->hod->id,
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Chief',
            'email' => 'finance_head@test.com',
            'role' => 'Finance Admin',
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'General Expenses',
            'status' => 'active',
        ]);

        Setting::set('super_admin_notification_emails', 'finance_head@test.com');
        Setting::set('management_notification_emails', 'management_exec@test.com');
    }

    public function test_hod_receives_email_when_hod_approval_needed_and_finance_management_do_not(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->staff)->post(route('petty-cash.store'), [
            'is_iou' => 0,
            'hod_id' => $this->hod->id,
            'items' => [
                [
                    'expense_category_id' => $this->category->id,
                    'amount' => 1500,
                    'description' => 'Office items',
                ]
            ],
        ]);

        $response->assertRedirect();

        $request = PettyCashRequest::latest('id')->first();
        $this->assertEquals('pending_hod', $request->status);

        // HOD must be notified and 'mail' channel included
        Notification::assertSentTo($this->hod, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->hod)) && $notification->action === 'submitted';
        });

        // Requester gets notified
        Notification::assertSentTo($this->staff, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->staff)) && $notification->action === 'submitted';
        });

        // Finance Admin must NOT receive email notification when pending HOD
        Notification::assertNotSentTo($this->financeAdmin, PettyCashNotification::class);

        // Management must NOT receive email notification
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);
    }

    public function test_finance_admin_receives_email_when_hod_approves_and_hod_does_not(): void
    {
        $pettyCash = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1001',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 2000,
            'is_iou' => 0,
            'status' => 'pending_hod',
        ]);

        Notification::fake();

        $response = $this->actingAs($this->hod)->post(route('petty-cash.hodApprove', $pettyCash));
        $response->assertRedirect();

        $pettyCash->refresh();
        $this->assertEquals('pending_super_admin', $pettyCash->status);

        // Finance Admin must be notified and 'mail' channel included
        Notification::assertSentTo($this->financeAdmin, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->financeAdmin)) && $notification->action === 'hod_approved';
        });

        // Requester notified
        Notification::assertSentTo($this->staff, PettyCashNotification::class);

        // HOD must NOT receive email notification
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);

        // Management must NOT receive email notification
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);
    }

    public function test_management_receives_email_when_sent_to_management_and_hod_finance_do_not(): void
    {
        $pettyCash = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1002',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 50000,
            'is_iou' => 0,
            'status' => 'pending_super_admin',
        ]);

        Notification::fake();

        $response = $this->actingAs($this->financeAdmin)->post(route('petty-cash.sendToManagement', $pettyCash), [
            'management_notes' => 'High value request needs board review',
        ]);
        $response->assertRedirect();

        $pettyCash->refresh();
        $this->assertEquals('pending_management', $pettyCash->status);

        // Management must receive notification and 'mail' channel included
        Notification::assertSentTo($this->management, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->management)) && $notification->action === 'sent_to_management';
        });

        // Requester notified
        Notification::assertSentTo($this->staff, PettyCashNotification::class);

        // HOD must NOT receive email notification
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);
    }

    public function test_finance_admin_receives_email_when_management_approves_and_hod_does_not(): void
    {
        $pettyCash = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1003',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 50000,
            'is_iou' => 0,
            'status' => 'pending_management',
        ]);

        Notification::fake();

        $response = $this->actingAs($this->management)->post(route('petty-cash.managementApprove', $pettyCash), [
            'management_notes' => 'Approved by Management',
        ]);
        $response->assertRedirect();

        $pettyCash->refresh();
        $this->assertEquals('pending_super_admin', $pettyCash->status);

        // Finance Admin must receive notification and 'mail' channel included
        Notification::assertSentTo($this->financeAdmin, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->financeAdmin)) && $notification->action === 'management_approved';
        });

        // Requester notified
        Notification::assertSentTo($this->staff, PettyCashNotification::class);

        // HOD must NOT receive email notification
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);

        // Management must NOT receive email notification
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);
    }

    public function test_only_requester_receives_email_when_finance_approves(): void
    {
        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $pettyCash = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1004',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 5000,
            'is_iou' => 1,
            'status' => 'pending_super_admin',
        ]);

        Notification::fake();

        $response = $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminApprove', $pettyCash), [
            'signature' => $dummySig,
        ]);
        $response->assertRedirect();

        $pettyCash->refresh();
        $this->assertEquals('iou_issued', $pettyCash->status);

        // Only Requester notified
        Notification::assertSentTo($this->staff, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->staff)) && $notification->action === 'admin_approved';
        });

        // Neither HOD, Finance Admin, nor Management receive email
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);
        Notification::assertNotSentTo($this->financeAdmin, PettyCashNotification::class);
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);
    }

    public function test_rejections_only_email_requester(): void
    {
        // 1. HOD Reject
        $pc1 = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1005',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 3000,
            'is_iou' => 0,
            'status' => 'pending_hod',
        ]);

        Notification::fake();

        $this->actingAs($this->hod)->post(route('petty-cash.hodReject', $pc1), [
            'hod_rejection_note' => 'Not needed',
        ]);

        Notification::assertSentTo($this->staff, PettyCashNotification::class);
        Notification::assertNotSentTo($this->financeAdmin, PettyCashNotification::class);
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);

        // 2. Finance Admin Reject
        $pc2 = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1006',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 3000,
            'is_iou' => 0,
            'status' => 'pending_super_admin',
        ]);

        Notification::fake();

        $this->actingAs($this->financeAdmin)->post(route('petty-cash.adminReject', $pc2), [
            'admin_rejection_note' => 'Budget exceeded',
        ]);

        Notification::assertSentTo($this->staff, PettyCashNotification::class);
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);
        Notification::assertNotSentTo($this->financeAdmin, PettyCashNotification::class);
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);

        // 3. Management Reject
        $pc3 = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1007',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 3000,
            'is_iou' => 0,
            'status' => 'pending_management',
        ]);

        Notification::fake();

        $this->actingAs($this->management)->post(route('petty-cash.managementReject', $pc3), [
            'management_rejection_note' => 'Management declined',
        ]);

        Notification::assertSentTo($this->staff, PettyCashNotification::class);
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);
        Notification::assertNotSentTo($this->financeAdmin, PettyCashNotification::class);
    }

    public function test_exceeded_settlement_only_emails_hod_when_hod_approval_needed(): void
    {
        $pettyCash = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1008',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 5000,
            'approved_amount' => 5000,
            'is_iou' => 1,
            'status' => 'iou_issued',
            'issued_at' => now(),
        ]);

        $item = PettyCashItem::create([
            'petty_cash_request_id' => $pettyCash->id,
            'expense_category_id' => $this->category->id,
            'amount' => 5000,
            'description' => 'Original item',
        ]);

        Notification::fake();

        // Staff submits settlement with higher amount (6500 > 5000)
        $response = $this->actingAs($this->staff)->post(route('petty-cash.settle', $pettyCash), [
            'items' => [
                [
                    'id' => $item->id,
                    'amount' => 6500,
                    'description' => 'Cost increased',
                ]
            ],
            'settlement_note' => 'Actual bills were 6500',
        ]);
        $response->assertRedirect();

        $pettyCash->refresh();
        $this->assertEquals('pending_settlement_hod', $pettyCash->status);

        // HOD must receive email notification for approval
        Notification::assertSentTo($this->hod, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->hod)) && $notification->action === 'iou_settlement_exceeded';
        });

        // Requester receives notification
        Notification::assertSentTo($this->staff, PettyCashNotification::class);

        // Finance Admin must NOT receive email notification until HOD approves
        Notification::assertNotSentTo($this->financeAdmin, PettyCashNotification::class);

        // Management must NOT receive email notification
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);
    }

    public function test_standard_settlement_only_emails_finance_not_hod(): void
    {
        $pettyCash = PettyCashRequest::create([
            'reference_number' => 'PC-2026-1009',
            'user_id' => $this->staff->id,
            'hod_id' => $this->hod->id,
            'total_amount' => 5000,
            'approved_amount' => 5000,
            'is_iou' => 1,
            'status' => 'iou_issued',
            'issued_at' => now(),
        ]);

        $item = PettyCashItem::create([
            'petty_cash_request_id' => $pettyCash->id,
            'expense_category_id' => $this->category->id,
            'amount' => 5000,
            'description' => 'Original item',
        ]);

        Notification::fake();

        // Staff submits settlement within approved amount (4500 <= 5000)
        $response = $this->actingAs($this->staff)->post(route('petty-cash.settle', $pettyCash), [
            'items' => [
                [
                    'id' => $item->id,
                    'amount' => 4500,
                    'description' => 'Actual cost',
                ]
            ],
            'settlement_note' => 'Settled with change returned',
        ]);
        $pettyCash->refresh();
        $this->assertEquals('pending_settlement', $pettyCash->status);

        // Finance Admin must receive notification for verification & approval
        Notification::assertSentTo($this->financeAdmin, PettyCashNotification::class, function ($notification) {
            return in_array('mail', $notification->via($this->financeAdmin)) && $notification->action === 'submitted';
        });

        // HOD must NOT receive email notification (standard settlement within budget)
        Notification::assertNotSentTo($this->hod, PettyCashNotification::class);

        // Management must NOT receive email notification
        Notification::assertNotSentTo($this->management, PettyCashNotification::class);
    }
}
