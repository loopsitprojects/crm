<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialPerformanceAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $itAdmin;
    private User $management;
    private User $financeAdmin;
    private User $hod;
    private User $manager;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itAdmin = User::factory()->create([
            'name' => 'IT Admin User',
            'email' => 'itadmin@loops.com',
            'role' => 'IT Admin',
        ]);

        $this->management = User::factory()->create([
            'name' => 'Management User',
            'email' => 'mgmt@loops.com',
            'role' => 'Management',
        ]);

        $this->financeAdmin = User::factory()->create([
            'name' => 'Finance Admin User',
            'email' => 'finance@loops.com',
            'role' => 'Finance Admin',
        ]);

        $this->hod = User::factory()->create([
            'name' => 'HOD User',
            'email' => 'hod@loops.com',
            'role' => 'HOD',
            'department' => 'AM',
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@loops.com',
            'role' => 'Manager',
            'department' => 'AM',
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@loops.com',
            'role' => 'Staff',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('financial-performance.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_staff_is_blocked(): void
    {
        $response = $this->actingAs($this->staff)->get(route('financial-performance.index'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_manager_is_blocked(): void
    {
        $response = $this->actingAs($this->manager)->get(route('financial-performance.index'));
        $response->assertForbidden();
    }

    public function test_hod_is_blocked(): void
    {
        $response = $this->actingAs($this->hod)->get(route('financial-performance.index'));
        $response->assertForbidden();
    }

    public function test_finance_admin_is_strictly_blocked(): void
    {
        $response = $this->actingAs($this->financeAdmin)->get(route('financial-performance.index'));
        $response->assertForbidden();
    }

    public function test_it_admin_can_access(): void
    {
        $response = $this->actingAs($this->itAdmin)->get(route('financial-performance.index'));
        $response->assertOk();
        $response->assertSee('Financial Performance');
        $response->assertSee('Loops — Team Performance Dashboard');
    }

    public function test_management_can_access(): void
    {
        $response = $this->actingAs($this->management)->get(route('financial-performance.index'));
        $response->assertOk();
        $response->assertSee('Financial Performance');
        $response->assertSee('Loops — Team Performance Dashboard');
    }

    public function test_api_data_endpoint_permissions(): void
    {
        // Blocked for Finance Admin
        $this->actingAs($this->financeAdmin)
            ->getJson(route('financial-performance.data'))
            ->assertForbidden();

        // Allowed for IT Admin
        $response = $this->actingAs($this->itAdmin)
            ->getJson(route('financial-performance.data'));
        $response->assertOk();
        $response->assertJsonStructure([
            'as_of',
            'departments',
            'sales_units',
            'pipeline',
            'transactions',
        ]);
    }

    public function test_sidebar_link_only_visible_to_it_admin_and_management(): void
    {
        // IT Admin sees it
        $this->actingAs($this->itAdmin)
            ->get(route('dashboard'))
            ->assertSee(route('financial-performance.index'));

        // Management sees it
        $this->actingAs($this->management)
            ->get(route('dashboard'))
            ->assertSee(route('financial-performance.index'));

        // Finance Admin does NOT see it
        $this->actingAs($this->financeAdmin)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));

        // Manager does NOT see it
        $this->actingAs($this->manager)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));

        // Staff does NOT see it
        $this->actingAs($this->staff)
            ->get(route('dashboard'))
            ->assertDontSee(route('financial-performance.index'));
    }
}
