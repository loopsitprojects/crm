<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TempInvoice;
use Illuminate\Support\Facades\Blade;

class ProcessInvoiceAccessTest extends TestCase
{
    private string $readyBladeActionSnippet = <<<'BLADE'
@if(in_array(auth()->user()->role, ['Super Admin', 'Finance Admin', 'Management']) || auth()->user()->isFinanceAdmin() || auth()->user()->isManagement())
    <a href="{{ route('temp-invoices.edit', $estimate->id) }}"
        class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs shadow-sm font-semibold inline-flex items-center">
        <i class="fas fa-cog mr-1"></i> Process Invoice
    </a>
@endif
BLADE;

    public function test_finance_admin_sees_process_invoice_option(): void
    {
        $user = new User([
            'name' => 'Finance Officer',
            'role' => 'Finance Admin',
        ]);
        $user->id = 10;
        $this->actingAs($user);

        $tempInvoice = new TempInvoice();
        $tempInvoice->id = 99;

        $rendered = Blade::render($this->readyBladeActionSnippet, ['estimate' => $tempInvoice]);

        $this->assertStringContainsString('Process Invoice', $rendered);
        $this->assertStringContainsString(route('temp-invoices.edit', 99), $rendered);
    }

    public function test_management_sees_process_invoice_option(): void
    {
        $user = new User([
            'name' => 'Management Director',
            'role' => 'Management',
        ]);
        $user->id = 20;
        $this->actingAs($user);

        $tempInvoice = new TempInvoice();
        $tempInvoice->id = 101;

        $rendered = Blade::render($this->readyBladeActionSnippet, ['estimate' => $tempInvoice]);

        $this->assertStringContainsString('Process Invoice', $rendered);
        $this->assertStringContainsString(route('temp-invoices.edit', 101), $rendered);
    }

    public function test_super_admin_legacy_sees_process_invoice_option(): void
    {
        $user = new User([
            'name' => 'Legacy Admin',
            'role' => 'Super Admin',
        ]);
        $user->id = 30;
        $this->actingAs($user);

        $tempInvoice = new TempInvoice();
        $tempInvoice->id = 102;

        $rendered = Blade::render($this->readyBladeActionSnippet, ['estimate' => $tempInvoice]);

        $this->assertStringContainsString('Process Invoice', $rendered);
        $this->assertStringContainsString(route('temp-invoices.edit', 102), $rendered);
    }

    public function test_staff_does_not_see_process_invoice_option(): void
    {
        $user = new User([
            'name' => 'Staff Member',
            'role' => 'Staff',
        ]);
        $user->id = 40;
        $this->actingAs($user);

        $tempInvoice = new TempInvoice(['id' => 103]);

        $rendered = Blade::render($this->readyBladeActionSnippet, ['estimate' => $tempInvoice]);

        $this->assertStringNotContainsString('Process Invoice', $rendered);
    }

    public function test_manager_does_not_see_process_invoice_option(): void
    {
        $user = new User([
            'name' => 'Project Manager',
            'role' => 'Manager',
        ]);
        $user->id = 50;
        $this->actingAs($user);

        $tempInvoice = new TempInvoice(['id' => 104]);

        $rendered = Blade::render($this->readyBladeActionSnippet, ['estimate' => $tempInvoice]);

        $this->assertStringNotContainsString('Process Invoice', $rendered);
    }

    public function test_hod_does_not_see_process_invoice_option(): void
    {
        $user = new User([
            'name' => 'Department Head',
            'role' => 'HOD',
        ]);
        $user->id = 60;
        $this->actingAs($user);

        $tempInvoice = new TempInvoice(['id' => 105]);

        $rendered = Blade::render($this->readyBladeActionSnippet, ['estimate' => $tempInvoice]);

        $this->assertStringNotContainsString('Process Invoice', $rendered);
    }

    public function test_readonly_status_for_roles(): void
    {
        $financeAdmin = new User(['role' => 'Finance Admin']);
        $management = new User(['role' => 'Management']);
        $staff = new User(['role' => 'Staff']);

        $financeReadonly = !$financeAdmin->hasRole('Finance Admin') && !$financeAdmin->hasRole('Super Admin') && !$financeAdmin->hasRole('Management');
        $managementReadonly = !$management->hasRole('Finance Admin') && !$management->hasRole('Super Admin') && !$management->hasRole('Management');
        $staffReadonly = !$staff->hasRole('Finance Admin') && !$staff->hasRole('Super Admin') && !$staff->hasRole('Management');

        $this->assertFalse($financeReadonly, 'Finance Admin should not be readonly');
        $this->assertFalse($managementReadonly, 'Management should not be readonly');
        $this->assertTrue($staffReadonly, 'Staff should be readonly');
    }
}
