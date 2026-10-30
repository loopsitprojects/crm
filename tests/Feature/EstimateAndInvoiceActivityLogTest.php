<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateAndInvoiceActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@loops.com',
            'role' => 'Super Admin',
        ]);

        $this->customer = Customer::create([
            'name' => 'Acme Global Ltd',
            'email' => 'contact@acmeglobal.com',
            'brand' => 'Acme',
        ]);
    }

    public function test_creating_estimate_logs_detailed_activity(): void
    {
        $response = $this->actingAs($this->admin)->post(route('estimates.store'), [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Brand',
            'currency' => 'LKR',
            'date' => '2026-10-30',
            'proforma_invoice' => 'yes',
            'invoice_type' => 'tax_invoice',
            'items' => [
                [
                    'description' => 'Social Media Strategy',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'position' => 0,
                    'department' => 'Digital',
                    'revenue_category' => 'Retainer',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('estimates.index'));

        $estimate = Estimate::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($estimate);

        $activity = Activity::where('description', 'like', "%Created estimate: {$estimate->reference_number}%")
            ->where('subject_type', Estimate::class)
            ->where('subject_id', $estimate->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->admin->id, $activity->user_id);
        $this->assertEquals($estimate->reference_number, $activity->properties['reference_number']);
        $this->assertEquals('Acme Global Ltd', $activity->properties['customer']);
        $this->assertEquals(1, $activity->properties['items_count']);
    }

    public function test_viewing_estimate_preview_logs_activity(): void
    {
        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'reference_number' => 'EST-VIEW-001',
            'brand_name' => 'Acme',
            'currency' => 'LKR',
            'total_amount' => 50000,
            'status' => 'draft',
            'date' => '2026-10-30',
        ]);

        $response = $this->actingAs($this->admin)->get(route('estimates.show', $estimate));
        $response->assertOk();

        $activity = Activity::where('description', "Viewed estimate preview/PDF: {$estimate->reference_number}")
            ->where('subject_type', Estimate::class)
            ->where('subject_id', $estimate->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(50000, $activity->properties['total_amount']);
    }

    public function test_updating_estimate_logs_field_changes_and_items_diff(): void
    {
        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'reference_number' => 'EST-UPD-001',
            'brand_name' => 'Old Brand',
            'currency' => 'LKR',
            'total_amount' => 50000,
            'status' => 'draft',
            'date' => '2026-10-01',
            'proforma_invoice' => 'no',
        ]);

        $estimate->items()->create([
            'description' => 'Original Item',
            'quantity' => 1,
            'unit_price' => 50000,
            'amount' => 50000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 50000,
            'department' => 'Digital',
            'revenue_category' => 'Retainer',
            'position' => 0,
        ]);

        $updatePayload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'New Brand',
            'date' => '2026-10-15',
            'currency' => 'LKR',
            'proforma_invoice' => 'yes',
            'items' => [
                [
                    'description' => 'Original Item Renamed',
                    'quantity' => 2,
                    'unit_price' => 40000,
                    'position' => 0,
                    'department' => 'Digital',
                    'revenue_category' => 'Retainer',
                ],
                [
                    'description' => 'Second Item Added',
                    'quantity' => 1,
                    'unit_price' => 20000,
                    'position' => 1,
                    'department' => 'Creative',
                    'revenue_category' => 'Production',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('estimates.update', $estimate->id), $updatePayload);
        $response->assertRedirect(route('estimates.show', $estimate->id));

        $activity = Activity::where('description', 'like', "%Updated estimate: {$estimate->reference_number}%")
            ->where('subject_type', Estimate::class)
            ->where('subject_id', $estimate->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertArrayHasKey('changes', $activity->properties);
        $this->assertArrayHasKey('items_diff', $activity->properties);

        // Check specific field diffs
        $this->assertEquals('Old Brand', $activity->properties['changes']['brand_name']['from']);
        $this->assertEquals('New Brand', $activity->properties['changes']['brand_name']['to']);
        $this->assertEquals('2026-10-01', $activity->properties['changes']['date']['from']);
        $this->assertEquals('2026-10-15', $activity->properties['changes']['date']['to']);

        // Check items diff
        $this->assertEquals(1, $activity->properties['items_diff']['added']);
        $this->assertEquals(1, $activity->properties['items_diff']['modified']);
    }

    public function test_updating_invoice_logs_field_changes_and_items_diff(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-LOG-001',
            'date' => '2026-10-01',
            'due_date' => '2026-11-01',
            'total_amount' => 60000,
            'status' => 'unpaid',
            'brand_name' => 'Initial Brand',
            'currency' => 'LKR',
        ]);

        $invoice->items()->create([
            'description' => 'Original Invoice Item',
            'quantity' => 1,
            'unit_price' => 60000,
            'amount' => 60000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 60000,
            'department' => 'Tech',
            'revenue_category' => 'Development',
            'position' => 0,
        ]);

        $updatePayload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Updated Invoice Brand',
            'date' => '2026-10-05',
            'currency' => 'LKR',
            'invoice_number' => 'INV-LOG-001',
            'items' => [
                [
                    'description' => 'Modified Invoice Item',
                    'quantity' => 2,
                    'unit_price' => 45000,
                    'position' => 0,
                    'department' => 'Tech',
                    'revenue_category' => 'Development',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('invoices.update', $invoice->id), $updatePayload);
        $response->assertRedirect(route('invoices.index'));

        $activity = Activity::where('description', 'like', "%Updated invoice {$invoice->invoice_number}%")
            ->where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertArrayHasKey('changes', $activity->properties);
        $this->assertEquals('Initial Brand', $activity->properties['changes']['brand_name']['from']);
        $this->assertEquals('Updated Invoice Brand', $activity->properties['changes']['brand_name']['to']);
        $this->assertEquals('2026-10-01', $activity->properties['changes']['date']['from']);
        $this->assertEquals('2026-10-05', $activity->properties['changes']['date']['to']);
    }

    public function test_updating_invoice_status_logs_old_and_new_status(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-STAT-001',
            'date' => '2026-10-01',
            'due_date' => '2026-11-01',
            'total_amount' => 25000,
            'status' => 'unpaid',
            'currency' => 'LKR',
        ]);

        $response = $this->actingAs($this->admin)->post(route('invoices.updateStatus', $invoice), [
            'status' => 'paid',
        ]);
        $response->assertRedirect();

        $activity = Activity::where('description', 'like', "%Updated status to paid for invoice: INV-STAT-001%")
            ->where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('unpaid', $activity->properties['old_status']);
        $this->assertEquals('paid', $activity->properties['new_status']);
    }

    public function test_deleting_estimate_and_invoice_logs_with_properties(): void
    {
        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'reference_number' => 'EST-DEL-001',
            'brand_name' => 'Acme',
            'currency' => 'LKR',
            'total_amount' => 77000,
            'status' => 'draft',
            'date' => '2026-10-30',
        ]);

        $this->actingAs($this->admin)->delete(route('estimates.destroy', $estimate));

        $estimateActivity = Activity::where('description', 'Deleted estimate: EST-DEL-001')->first();
        $this->assertNotNull($estimateActivity);
        $this->assertEquals('Acme Global Ltd', $estimateActivity->properties['customer']);
        $this->assertEquals(77000, $estimateActivity->properties['total_amount']);

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-DEL-001',
            'date' => '2026-10-01',
            'due_date' => '2026-11-01',
            'total_amount' => 45000,
            'status' => 'unpaid',
            'currency' => 'LKR',
        ]);

        $this->actingAs($this->admin)->delete(route('invoices.destroy', $invoice->id));

        $invoiceActivity = Activity::where('description', 'Deleted invoice: INV-DEL-001')->first();
        $this->assertNotNull($invoiceActivity);
        $this->assertEquals('Acme Global Ltd', $invoiceActivity->properties['customer']);
        $this->assertEquals(45000, $invoiceActivity->properties['total_amount']);
    }

    public function test_activities_index_filters_by_search_and_subject_type(): void
    {
        $estimate = Estimate::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'reference_number' => 'EST-SEARCH-999',
            'brand_name' => 'Acme',
            'currency' => 'LKR',
            'total_amount' => 12345,
            'status' => 'draft',
            'date' => '2026-10-30',
        ]);

        $this->actingAs($this->admin)->get(route('estimates.show', $estimate));

        // Filter by keyword
        $response = $this->actingAs($this->admin)->get(route('activities.index', ['search' => 'EST-SEARCH-999']));
        $response->assertOk();
        $response->assertSee('EST-SEARCH-999');

        // Filter by subject_type
        $responseType = $this->actingAs($this->admin)->get(route('activities.index', ['subject_type' => 'estimate']));
        $responseType->assertOk();
        $responseType->assertSee('EST-SEARCH-999');
    }

    public function test_updating_invoice_line_item_logs_detailed_item_changes(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-ITEM-001',
            'date' => '2026-10-21',
            'due_date' => '2026-11-21',
            'total_amount' => 100000,
            'status' => 'unpaid',
            'brand_name' => 'Acme Brand',
            'currency' => 'LKR',
        ]);

        $invoice->items()->create([
            'description' => '<p>Original Campaign Services</p>',
            'quantity' => 1,
            'unit_price' => 100000,
            'amount' => 100000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 100000,
            'department' => 'Digital',
            'revenue_category' => 'Retainer',
            'position' => 0,
        ]);

        // Submit update where invoice attributes stay the same, but item quantity and department change
        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Brand',
            'date' => '2026-10-21',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => '<p>Updated Campaign Services</p>',
                    'quantity' => 2,
                    'unit_price' => 100000,
                    'position' => 0,
                    'department' => 'Creative',
                    'revenue_category' => 'Retainer',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('invoices.update', $invoice->id), $payload);
        $response->assertRedirect(route('invoices.index'));

        $activity = Activity::where('description', 'like', "%Updated invoice {$invoice->invoice_number}%")
            ->where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertArrayHasKey('total_amount', $activity->properties['changes']);
        $this->assertEquals(100000, $activity->properties['changes']['total_amount']['from']);
        $this->assertEquals(200000, $activity->properties['changes']['total_amount']['to']);
        $this->assertEquals(1, $activity->properties['items_diff']['modified']);
        $this->assertNotEmpty($activity->properties['items_diff']['details']['modified']);

        $modDetail = $activity->properties['items_diff']['details']['modified'][0];
        $this->assertEquals(1, $modDetail['item_number']);
        $this->assertEquals('Updated Campaign Services', $modDetail['description']);
        $this->assertEquals(1, $modDetail['changes']['quantity']['from']);
        $this->assertEquals(2, $modDetail['changes']['quantity']['to']);
        $this->assertEquals('Digital', $modDetail['changes']['department']['from']);
        $this->assertEquals('Creative', $modDetail['changes']['department']['to']);
        $this->assertEquals('Original Campaign Services', $modDetail['changes']['description']['from']);
        $this->assertEquals('Updated Campaign Services', $modDetail['changes']['description']['to']);

        // Check view rendering - shouldn't display "0 field", should display "1 item" badge and modified table
        $viewResponse = $this->actingAs($this->admin)->get(route('activities.index'));
        $viewResponse->assertOk();
        $viewResponse->assertDontSee('>0 field<', false);
        $viewResponse->assertSee('1 item');
        $viewResponse->assertSee('Modified Line Items');
        $viewResponse->assertSee('Updated Campaign Services');
    }

    public function test_updating_invoice_without_changes_does_not_falsely_flag_modified_items(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SAME-001',
            'date' => '2026-10-21',
            'due_date' => '2026-11-21',
            'total_amount' => 50000,
            'status' => 'unpaid',
            'brand_name' => 'Acme Brand',
            'currency' => 'LKR',
        ]);

        $invoice->items()->create([
            'description' => 'Plain description without tags',
            'quantity' => 1,
            'unit_price' => 50000,
            'amount' => 50000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 50000,
            'department' => 'digital',
            'revenue_category' => 'Retainer',
            'position' => 0,
        ]);

        // Submit form with Quill wrapped <p> tag of identical text and CRLF newline
        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Brand',
            'date' => '2026-10-21',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => "<p>Plain description without tags</p>\r\n",
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'position' => 0,
                    'department' => 'digital',
                    'revenue_category' => 'Retainer',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('invoices.update', $invoice->id), $payload);
        $response->assertRedirect(route('invoices.index'));

        $activity = Activity::where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        if (!$activity) {
            dump(Activity::all()->pluck('description', 'id')->toArray());
        }

        $this->assertNotNull($activity);
        $this->assertEquals(0, $activity->properties['items_diff']['modified']);
        $this->assertEquals(0, $activity->properties['items_diff']['added']);
        $this->assertEquals(0, $activity->properties['items_diff']['removed']);
        $this->assertEmpty($activity->properties['changes']);
    }

    public function test_adding_and_removing_invoice_items_logs_details(): void
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-ADD-REM-001',
            'date' => '2026-10-21',
            'due_date' => '2026-11-21',
            'total_amount' => 100000,
            'status' => 'unpaid',
            'brand_name' => 'Acme Brand',
            'currency' => 'LKR',
        ]);

        $invoice->items()->create([
            'description' => 'First item to keep',
            'quantity' => 1,
            'unit_price' => 40000,
            'amount' => 40000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 40000,
            'department' => 'Digital',
            'revenue_category' => 'Retainer',
            'position' => 0,
        ]);

        $invoice->items()->create([
            'description' => 'Second item to remove',
            'quantity' => 1,
            'unit_price' => 60000,
            'amount' => 60000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 60000,
            'department' => 'Tech',
            'revenue_category' => 'Development',
            'position' => 1,
        ]);

        // Replace item 2 with a newly added item
        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Brand',
            'date' => '2026-10-21',
            'currency' => 'LKR',
            'items' => [
                [
                    'description' => 'First item to keep',
                    'quantity' => 1,
                    'unit_price' => 40000,
                    'position' => 0,
                    'department' => 'Digital',
                    'revenue_category' => 'Retainer',
                ],
            ],
        ];

        // First remove item 2
        $response = $this->actingAs($this->admin)->put(route('invoices.update', $invoice->id), $payload);
        $response->assertRedirect(route('invoices.index'));

        $activity = Activity::where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(1, $activity->properties['items_diff']['removed']);
        $this->assertNotEmpty($activity->properties['items_diff']['details']['removed']);
        $this->assertEquals('Second item to remove', $activity->properties['items_diff']['details']['removed'][0]['description']);
        $this->assertEquals('60,000.00', $activity->properties['items_diff']['details']['removed'][0]['unit_price']);

        // Now add an item
        $payload['items'][] = [
            'description' => 'Third item added',
            'quantity' => 3,
            'unit_price' => 15000,
            'position' => 1,
            'department' => 'Creative',
            'revenue_category' => 'Design',
        ];

        $response = $this->actingAs($this->admin)->put(route('invoices.update', $invoice->id), $payload);
        $response->assertRedirect(route('invoices.index'));

        $activity2 = Activity::where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity2);
        $this->assertEquals(1, $activity2->properties['items_diff']['added']);
        $this->assertNotEmpty($activity2->properties['items_diff']['details']['added']);
        $this->assertEquals('Third item added', $activity2->properties['items_diff']['details']['added'][0]['description']);
        $this->assertEquals(3, $activity2->properties['items_diff']['details']['added'][0]['quantity']);
        $this->assertEquals('15,000.00', $activity2->properties['items_diff']['details']['added'][0]['unit_price']);
    }

    public function test_updating_invoice_with_long_special_terms_does_not_overflow_description(): void
    {
        $longTermsOld = trim(str_repeat('Old Special Terms Bank & Branch Details ', 10));
        $longTermsNew = trim(str_repeat('New Special Terms Bank & Branch Details ', 15));

        $invoice = Invoice::create([
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-LONG-001',
            'date' => '2026-10-21',
            'due_date' => '2026-11-21',
            'total_amount' => 50000,
            'status' => 'unpaid',
            'brand_name' => 'Acme Brand',
            'currency' => 'LKR',
            'special_terms' => $longTermsOld,
        ]);

        $invoice->items()->create([
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 50000,
            'amount' => 50000,
            'vat_amount' => 0,
            'sscl_amount' => 0,
            'total_with_vat' => 50000,
            'department' => 'Digital',
            'revenue_category' => 'Retainer',
            'position' => 0,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'brand_name' => 'Acme Brand',
            'date' => '2026-10-21',
            'currency' => 'LKR',
            'special_terms' => $longTermsNew,
            'items' => [
                [
                    'description' => 'Item 1',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'position' => 0,
                    'department' => 'Digital',
                    'revenue_category' => 'Retainer',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('invoices.update', $invoice->id), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('invoices.index'));

        $activity = Activity::where('subject_type', Invoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertArrayHasKey('special_terms', $activity->properties['changes']);
        $this->assertEquals($longTermsOld, $activity->properties['changes']['special_terms']['from']);
        $this->assertEquals($longTermsNew, $activity->properties['changes']['special_terms']['to']);
    }
}
