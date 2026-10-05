<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberEditTest extends TestCase
{
    use RefreshDatabase;

    private User $itAdmin;
    private User $financeAdmin;
    private User $management;
    private Customer $customer;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itAdmin = User::factory()->create([
            'name' => 'IT Admin User',
            'email' => 'itadmin@loops.com',
            'role' => 'IT Admin',
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Admin User',
            'email' => 'finance@loops.com',
            'role' => 'Finance Admin',
        ]);

        $this->management = User::factory()->create([
            'name' => 'Management User',
            'email' => 'mgmt@loops.com',
            'role' => 'Management',
        ]);

        $this->customer = Customer::create([
            'name' => 'St. Anthony Interior Solutions',
            'email' => 'anthony@example.com',
            'brand' => 'KIRIN',
        ]);

        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'reference_number' => 'EST-001',
            'brand_name' => 'KIRIN',
            'currency' => 'LKR',
            'total_amount' => 181538.46,
            'status' => 'accepted',
            'date' => '2026-09-30',
        ]);

        $this->invoice = Invoice::create([
            'quotation_id' => $estimate->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => '26OCT_LDSL_00233',
            'brand_name' => 'KIRIN',
            'date' => '2026-09-30',
            'due_date' => '2026-10-30',
            'total_amount' => 181538.46,
            'status' => 'unpaid',
            'currency' => 'LKR',
        ]);

        $this->invoice->items()->create([
            'description' => 'Performance Marketing Services September 2026 (KIRIN)',
            'type' => 'item',
            'quantity' => 1,
            'unit_price' => 181538.46,
            'amount' => 181538.46,
            'sscl_amount' => 0,
            'vat_amount' => 0,
            'total_with_vat' => 181538.46,
            'department' => 'digital',
            'revenue_category' => 'Retainer',
            'position' => 0,
        ]);
    }

    public function test_it_admin_sees_editable_invoice_number_input(): void
    {
        $response = $this->actingAs($this->itAdmin)->get(route('invoices.edit', $this->invoice->id));

        $response->assertOk();
        $response->assertSee('name="invoice_number"', false);
        $response->assertSee('value="26OCT_LDSL_00233"', false);
        $response->assertSee('IT &amp; Finance Admin Only', false);
    }

    public function test_finance_admin_sees_editable_invoice_number_input(): void
    {
        $response = $this->actingAs($this->financeAdmin)->get(route('invoices.edit', $this->invoice->id));

        $response->assertOk();
        $response->assertSee('name="invoice_number"', false);
        $response->assertSee('value="26OCT_LDSL_00233"', false);
    }

    public function test_management_sees_readonly_invoice_number(): void
    {
        $response = $this->actingAs($this->management)->get(route('invoices.edit', $this->invoice->id));

        $response->assertOk();
        $response->assertDontSee('name="invoice_number"', false);
        $response->assertSee('readonly', false);
    }

    public function test_it_admin_can_update_invoice_number(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'KIRIN',
            'invoice_number' => '26SEP_LDSL_00233',
            'date' => '2026-09-30',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => 'Performance Marketing Services September 2026 (KIRIN)',
                    'quantity' => 1,
                    'unit_price' => 181538.46,
                    'position' => 0,
                    'department' => 'digital',
                    'revenue_category' => 'Retainer',
                ]
            ],
        ];

        $response = $this->actingAs($this->itAdmin)->put(route('invoices.update', $this->invoice->id), $payload);

        $response->assertRedirect(route('invoices.index'));
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'invoice_number' => '26SEP_LDSL_00233',
        ]);
    }

    public function test_finance_admin_can_update_invoice_number(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'KIRIN',
            'invoice_number' => '26SEP_LDSL_00234',
            'date' => '2026-09-30',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => 'Performance Marketing Services September 2026 (KIRIN)',
                    'quantity' => 1,
                    'unit_price' => 181538.46,
                    'position' => 0,
                    'department' => 'digital',
                    'revenue_category' => 'Retainer',
                ]
            ],
        ];

        $response = $this->actingAs($this->financeAdmin)->put(route('invoices.update', $this->invoice->id), $payload);

        $response->assertRedirect(route('invoices.index'));
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'invoice_number' => '26SEP_LDSL_00234',
        ]);
    }

    public function test_management_cannot_update_invoice_number(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'KIRIN',
            'invoice_number' => '26HACKED_00999',
            'date' => '2026-09-30',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => 'Performance Marketing Services September 2026 (KIRIN)',
                    'quantity' => 1,
                    'unit_price' => 181538.46,
                    'position' => 0,
                    'department' => 'digital',
                    'revenue_category' => 'Retainer',
                ]
            ],
        ];

        $response = $this->actingAs($this->management)->put(route('invoices.update', $this->invoice->id), $payload);

        $response->assertRedirect(route('invoices.index'));
        // Original invoice number remains untouched!
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'invoice_number' => '26OCT_LDSL_00233',
        ]);
    }

    public function test_duplicate_invoice_number_fails_validation(): void
    {
        // Another invoice exists
        Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => '26SEP_LDSL_00001',
            'brand_name' => 'KIRIN',
            'date' => '2026-09-30',
            'due_date' => '2026-10-30',
            'total_amount' => 1000,
            'status' => 'unpaid',
            'currency' => 'LKR',
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'KIRIN',
            'invoice_number' => '26SEP_LDSL_00001', // Already taken!
            'date' => '2026-09-30',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => 'Test',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'position' => 0,
                    'department' => 'digital',
                    'revenue_category' => 'Retainer',
                ]
            ],
        ];

        $response = $this->actingAs($this->itAdmin)->put(route('invoices.update', $this->invoice->id), $payload);

        $response->assertSessionHasErrors(['invoice_number']);
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'invoice_number' => '26OCT_LDSL_00233',
        ]);
    }
}
