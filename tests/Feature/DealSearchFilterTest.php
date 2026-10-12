<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_deals_index_renders_search_bar_and_filters_results(): void
    {
        $admin = User::factory()->create(['role' => 'Super Admin']);

        $customer1 = Customer::create([
            'name' => 'Acme Corporation',
            'brand' => 'Acme',
            'email' => 'acme@example.com',
            'phone' => '1234567890',
            'status' => 'active'
        ]);

        $customer2 = Customer::create([
            'name' => 'Wayne Enterprises',
            'brand' => 'Wayne',
            'email' => 'wayne@example.com',
            'phone' => '9876543210',
            'status' => 'active'
        ]);

        $deal1 = Deal::create([
            'title' => 'Alpha Redesign Project',
            'customer_id' => $customer1->id,
            'user_id' => $admin->id,
            'customer_name' => $customer1->name,
            'revenue' => 150000,
            'contribution' => 100000,
            'currency' => 'LKR',
            'pipeline' => 'Sales Pipeline',
            'stage' => 'Planned to Meet',
            'job_number' => 'LOOPS/2026/0101',
            'close_date' => now()->toDateString(),
        ]);

        $deal2 = Deal::create([
            'title' => 'Beta Mobile Application',
            'customer_id' => $customer2->id,
            'user_id' => $admin->id,
            'customer_name' => $customer2->name,
            'revenue' => 250000,
            'contribution' => 180000,
            'currency' => 'LKR',
            'pipeline' => 'Sales Pipeline',
            'stage' => 'Planned to Meet',
            'job_number' => 'LOOPS/2026/0202',
            'close_date' => now()->toDateString(),
        ]);

        // 1. Visit index with no filter - search bar is present and both deals are visible
        $response = $this->actingAs($admin)->get(route('deals.index'));
        $response->assertStatus(200);
        $response->assertSee('id="deal_search"', false);
        $response->assertSee('Alpha Redesign Project');
        $response->assertSee('Beta Mobile Application');

        // 2. Search by title "Alpha"
        $responseTitle = $this->actingAs($admin)->get(route('deals.index', ['search' => 'Alpha']));
        $responseTitle->assertStatus(200);
        $responseTitle->assertSee('Alpha Redesign Project');
        $responseTitle->assertDontSee('Beta Mobile Application');

        // 3. Search by job number "0202"
        $responseJob = $this->actingAs($admin)->get(route('deals.index', ['search' => '0202']));
        $responseJob->assertStatus(200);
        $responseJob->assertSee('Beta Mobile Application');
        $responseJob->assertDontSee('Alpha Redesign Project');

        // 4. Search by customer name "Wayne"
        $responseCustomer = $this->actingAs($admin)->get(route('deals.index', ['search' => 'Wayne']));
        $responseCustomer->assertStatus(200);
        $responseCustomer->assertSee('Beta Mobile Application');
        $responseCustomer->assertDontSee('Alpha Redesign Project');
    }
}
