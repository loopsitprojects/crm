<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProformaInvoiceSyncDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@loops.com',
            'role' => 'Super Admin',
        ]);

        $this->customer = Customer::create([
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'brand' => 'Acme',
        ]);
    }

    public function test_updating_estimate_does_not_overwrite_existing_proforma_invoice_date(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'reference_number' => 'EST-SYNC-001',
            'brand_name' => 'Acme',
            'currency' => 'LKR',
            'total_amount' => 50000,
            'status' => 'draft',
            'date' => '2026-05-01',
            'proforma_invoice' => 'yes',
        ]);

        $proformaInvoice = Invoice::create([
            'quotation_id' => $estimate->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'PROINV_26_0001',
            'date' => '2026-05-01',
            'due_date' => '2026-06-01',
            'total_amount' => 50000,
            'status' => 'unpaid',
            'is_proforma' => true,
        ]);

        // Move time forward 5 months
        Carbon::setTestNow('2026-10-30 12:00:00');

        $updatePayload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Updated',
            'date' => '2026-05-01',
            'currency' => 'LKR',
            'proforma_invoice' => 'yes',
            'items' => [
                [
                    'description' => 'Updated item description',
                    'quantity' => 2,
                    'unit_price' => 30000,
                    'position' => 0,
                    'department' => 'Tech',
                    'revenue_category' => 'Development',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('estimates.update', $estimate->id), $updatePayload);
        $response->assertSessionHasNoErrors();

        $proformaInvoice->refresh();

        // The date must remain 2026-05-01, NOT overwritten with 2026-10-30
        $this->assertEquals('2026-05-01', $proformaInvoice->date);
    }

    public function test_updating_estimate_preserves_proforma_date_even_when_estimate_date_changes(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'reference_number' => 'EST-SYNC-002',
            'brand_name' => 'Acme',
            'currency' => 'LKR',
            'total_amount' => 50000,
            'status' => 'draft',
            'date' => '2026-05-01',
            'proforma_invoice' => 'yes',
        ]);

        $proformaInvoice = Invoice::create([
            'quotation_id' => $estimate->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'PROINV_26_0002',
            'date' => '2026-05-01',
            'due_date' => '2026-06-01',
            'total_amount' => 50000,
            'status' => 'unpaid',
            'is_proforma' => true,
        ]);

        Carbon::setTestNow('2026-10-30 12:00:00');

        // Update the estimate with a new estimate date
        $updatePayload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Updated',
            'date' => '2026-09-15',
            'currency' => 'LKR',
            'proforma_invoice' => 'yes',
            'items' => [
                [
                    'description' => 'Updated item description',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'position' => 0,
                    'department' => 'Tech',
                    'revenue_category' => 'Development',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('estimates.update', $estimate->id), $updatePayload);
        $response->assertSessionHasNoErrors();

        $proformaInvoice->refresh();

        // The proforma invoice date should remain 2026-05-01
        $this->assertEquals('2026-05-01', $proformaInvoice->date);
    }
}
