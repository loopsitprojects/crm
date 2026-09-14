<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealJobNumberCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test Eloquent model creation auto-generates job_number for the 5 target stages.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('targetStagesProvider')]
    public function test_creating_deal_in_target_stage_generates_job_number(string $stage): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Acme Corp',
            'company_name' => 'Acme Corp',
            'brand' => 'Acme',
            'email' => 'contact@acme.com',
            'phone' => '1234567890',
            'status' => 'active'
        ]);

        $deal = Deal::create([
            'title' => "Deal in {$stage}",
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'customer_name' => $customer->name,
            'revenue' => 100000,
            'contribution' => 80000,
            'currency' => 'LKR',
            'pipeline' => 'Sales Pipeline',
            'stage' => $stage,
            'close_date' => now()->toDateString(),
        ]);

        $year = date('Y');
        $idPad = str_pad($deal->id, 4, '0', STR_PAD_LEFT);
        $expectedJobNumber = "LOOPS/{$year}/{$idPad}";

        $this->assertNotNull($deal->job_number);
        $this->assertEquals($expectedJobNumber, $deal->job_number);

        // Verify in database
        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'job_number' => $expectedJobNumber,
        ]);
    }

    /**
     * Test Eloquent model creation does NOT generate job_number for non-target stages.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonTargetStagesProvider')]
    public function test_creating_deal_in_non_target_stage_does_not_generate_job_number(string $stage): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Acme Corp',
            'company_name' => 'Acme Corp',
            'brand' => 'Acme',
            'email' => 'contact@acme.com',
            'phone' => '1234567890',
            'status' => 'active'
        ]);

        $deal = Deal::create([
            'title' => "Deal in {$stage}",
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'customer_name' => $customer->name,
            'revenue' => 100000,
            'contribution' => 80000,
            'currency' => 'LKR',
            'pipeline' => 'Sales Pipeline',
            'stage' => $stage,
            'close_date' => now()->toDateString(),
        ]);

        $this->assertNull($deal->job_number);
        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'job_number' => null,
        ]);
    }

    /**
     * Test HTTP store action auto-generates job_number when creating deal through DealController.
     */
    public function test_deal_controller_store_generates_job_number_for_target_stages(): void
    {
        $user = User::factory()->create([
            'role' => 'Manager',
            'department' => 'Creative'
        ]);

        $customer = Customer::create([
            'name' => 'Test Brand Co',
            'company_name' => 'Test Brand Co',
            'brand' => 'Test Brand',
            'email' => 'test@brand.com',
            'phone' => '0771234567',
            'status' => 'active'
        ]);

        $targetStages = ['Working on pitch', 'Pitched', 'Objection handling', 'Finalizing terms', 'Closed Won'];

        foreach ($targetStages as $index => $stage) {
            $response = $this->actingAs($user)->post(route('deals.store'), [
                'title' => "Pipeline Deal {$index} - {$stage}",
                'customer_id' => $customer->id,
                'pipeline' => 'Sales Pipeline',
                'stage' => $stage,
                'revenue' => 50000,
                'contribution' => 40000,
                'currency' => 'LKR',
                'close_date' => now()->addDays(30)->toDateString(),
                'priority' => 'Medium',
                'type' => 'New Business',
            ]);

            $response->assertSessionHas('success');

            $deal = Deal::where('title', "Pipeline Deal {$index} - {$stage}")->first();
            $this->assertNotNull($deal);

            $year = date('Y');
            $idPad = str_pad($deal->id, 4, '0', STR_PAD_LEFT);
            $expectedJobNumber = "LOOPS/{$year}/{$idPad}";

            $this->assertEquals($expectedJobNumber, $deal->job_number);
        }
    }

    /**
     * Test HTTP store action does NOT generate job_number for non-target stages.
     */
    public function test_deal_controller_store_does_not_generate_job_number_for_non_target_stages(): void
    {
        $user = User::factory()->create([
            'role' => 'Manager',
            'department' => 'Creative'
        ]);

        $customer = Customer::create([
            'name' => 'Non Target Customer',
            'company_name' => 'Non Target Customer',
            'brand' => 'Non Target',
            'email' => 'nontarget@brand.com',
            'phone' => '0771234568',
            'status' => 'active'
        ]);

        $nonTargetStages = ['Planned to Meet', 'Introductory meeting', 'Brief Stage', 'Rejected'];

        foreach ($nonTargetStages as $index => $stage) {
            $payload = [
                'title' => "Non Target Deal {$index} - {$stage}",
                'customer_id' => $customer->id,
                'pipeline' => 'Sales Pipeline',
                'stage' => $stage,
                'revenue' => 25000,
                'contribution' => 20000,
                'currency' => 'LKR',
                'close_date' => now()->addDays(14)->toDateString(),
                'priority' => 'Low',
                'type' => 'New Business',
            ];

            if ($stage === 'Rejected') {
                $payload['rejection_reason'] = 'Client budget constraints';
            }

            $response = $this->actingAs($user)->post(route('deals.store'), $payload);

            $response->assertSessionHas('success');

            $deal = Deal::where('title', "Non Target Deal {$index} - {$stage}")->first();
            $this->assertNotNull($deal);
            $this->assertNull($deal->job_number);
        }
    }

    public static function targetStagesProvider(): array
    {
        return [
            ['Working on pitch'],
            ['Pitched'],
            ['Objection handling'],
            ['Finalizing terms'],
            ['Closed Won'],
        ];
    }

    public static function nonTargetStagesProvider(): array
    {
        return [
            ['Planned to Meet'],
            ['Introductory meeting'],
            ['Brief Stage'],
            ['Rejected'],
        ];
    }
}
