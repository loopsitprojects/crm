<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Estimate;
use App\Models\User;
use App\Services\WorkflowWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkflowWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /**
     * Test that creating a deal in a job stage triggers the webhook with job_number and brand_name.
     */
    public function test_creating_deal_in_job_stage_sends_webhook_with_job_and_brand(): void
    {
        $webhookUrl = 'https://workflow.test/webhook';
        Config::set('services.workflow.webhook_url', $webhookUrl);
        Config::set('services.workflow.webhook_secret', 'secret123');

        Http::fake([
            $webhookUrl => Http::response(['received' => true], 200),
        ]);

        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Acme Corporation',
            'company_name' => 'Acme Corporation',
            'brand' => 'Acme Global',
            'email' => 'client@acme.com',
            'phone' => '0771234567',
            'status' => 'active'
        ]);

        $deal = Deal::create([
            'title' => 'Acme Digital Campaign 2026',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'customer_name' => $customer->name,
            'revenue' => 350000,
            'contribution' => 280000,
            'currency' => 'LKR',
            'pipeline' => 'Sales Pipeline',
            'stage' => 'Working on pitch',
            'close_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertNotNull($deal->job_number);

        Http::assertSent(function ($request) use ($webhookUrl, $deal) {
            $data = $request->data();
            $signature = $request->header('X-Webhook-Signature')[0] ?? null;
            $token = $request->header('X-Webhook-Token')[0] ?? null;

            return $request->url() === $webhookUrl
                && count($data) === 2
                && array_key_exists('job_number', $data)
                && array_key_exists('brand_name', $data)
                && $data['job_number'] === $deal->job_number
                && $data['brand_name'] === 'Acme Global'
                && $token === 'secret123'
                && !empty($signature);
        });
    }

    /**
     * Test that an estimate's brand_name takes precedence over customer brand.
     */
    public function test_estimate_brand_takes_priority_over_customer_brand(): void
    {
        $webhookUrl = 'https://workflow.test/webhook';
        Config::set('services.workflow.webhook_url', $webhookUrl);
        Http::fake([
            $webhookUrl => Http::response(['status' => 'ok'], 200),
        ]);

        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Brand Parent Co',
            'company_name' => 'Brand Parent Co',
            'brand' => 'Customer Brand',
            'email' => 'parent@brand.com',
            'phone' => '0771234568',
            'status' => 'active'
        ]);

        // Create deal first
        $deal = Deal::create([
            'title' => 'Sub Brand Promotion',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'customer_name' => $customer->name,
            'revenue' => 500000,
            'stage' => 'Working on pitch',
        ]);

        // Now create an estimate with a specific brand_name
        $estimate = Estimate::create([
            'deal_id' => $deal->id,
            'customer_id' => $customer->id,
            'reference_number' => 'EST/2026/0001',
            'brand_name' => 'Specific Sub-Brand',
            'date' => now()->toDateString(),
            'total_amount' => 500000,
            'status' => 'draft',
        ]);

        $service = app(WorkflowWebhookService::class);
        $this->assertEquals('Specific Sub-Brand', $service->getBrandName($deal));

        // The estimate's saved event should have dispatched a webhook with strictly 'job_number' and 'brand_name'
        Http::assertSent(function ($request) use ($webhookUrl, $deal) {
            $data = $request->data();
            return $request->url() === $webhookUrl
                && count($data) === 2
                && $data['job_number'] === $deal->job_number
                && $data['brand_name'] === 'Specific Sub-Brand';
        });
    }

    /**
     * Test artisan webhook:workflow-test command.
     */
    public function test_artisan_webhook_test_command(): void
    {
        $testUrl = 'https://workflow.test/cli-test';
        Config::set('services.workflow.webhook_url', $testUrl);
        Http::fake([
            $testUrl => Http::response(['status' => 'success'], 200),
        ]);

        $deal = Deal::create([
            'title' => 'Sample Real Deal',
            'stage' => 'Working on pitch',
            'revenue' => 150000,
        ]);

        $this->artisan('webhook:workflow-test')
            ->expectsOutputToContain('=== Workflow Webhook Dispatcher ===')
            ->expectsOutputToContain('SUCCESS')
            ->assertExitCode(0);

        Http::assertSent(function ($request) use ($testUrl, $deal) {
            $data = $request->data();
            return $request->url() === $testUrl
                && count($data) === 2
                && $data['job_number'] === $deal->job_number;
        });
    }

    /**
     * Test manual sync from jobs route.
     */
    public function test_manual_job_webhook_sync_route(): void
    {
        $webhookUrl = 'https://workflow.test/manual';
        Config::set('services.workflow.webhook_url', $webhookUrl);
        Http::fake([
            $webhookUrl => Http::response(['received' => true], 200),
        ]);

        $user = User::factory()->create([
            'role' => 'Super Admin'
        ]);

        $deal = Deal::create([
            'title' => 'Manual Sync Deal',
            'stage' => 'Working on pitch',
            'revenue' => 120000,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('jobs.send-webhook', $deal->id));

        $response->assertSessionHas('success');

        Http::assertSent(function ($request) use ($webhookUrl, $deal) {
            $data = $request->data();
            return $request->url() === $webhookUrl
                && $data['job_number'] === $deal->job_number;
        });
    }

    /**
     * Test that if webhook URL is not configured, operations proceed silently without errors.
     */
    public function test_unconfigured_webhook_does_not_break_deal_creation(): void
    {
        Config::set('services.workflow.webhook_url', null);

        $user = User::factory()->create();

        $deal = Deal::create([
            'title' => 'Deal without Webhook Configured',
            'stage' => 'Working on pitch',
            'user_id' => $user->id,
            'revenue' => 50000,
        ]);

        $this->assertNotNull($deal->job_number);
        Http::assertNothingSent();
    }
}
